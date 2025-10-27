<?php

namespace Gametech\Integrations\Services;

use Carbon\Carbon;
use Gametech\Auto\Jobs\PaymentOutKingPay;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Integrations\AclAuthorizer;
use Gametech\Integrations\Contracts\ApproveContext;
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\Support\ConfigStore;
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Payment\Repositories\BankAccountRepository;
use Gametech\Payment\Repositories\WithdrawRepository;

// หากคุณใช้สัญญา เปลี่ยนเป็น Contracts\WithdrawRepository
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Gametech\Payment\Models\Withdraw;
use Illuminate\Support\Str;
use Throwable;

class WithdrawOrchestrator
{
    public function __construct(
        private ProviderManager       $providers,
        private AclAuthorizer         $acl,
        private WithdrawRepository    $withdrawRepository,
        private ConfigStore           $configStore,
        private BankAccountRepository $bankAccountRepo,   // ⬅️ เพิ่มตัวนี้
    )
    {
    }

    /**
     * โหลดนโยบายของ "ถอน"
     * แหล่งที่ 1: table configs (name_en='ops.withdraw', column 'content')
     * แหล่งที่ 2: config files integrations.access / integrations.flows
     * รวมกับ defaults และทำให้ค่าจำเป็นไม่ว่าง
     */
    private function policy(): array
    {
        $defaults = [
            'flow' => 'three_step', // 'two_step'|'three_step'
            'auto_post' => false,
            'permissions' => [
                'create' => 'withdraw.create',
                'check' => 'withdraw.update',
                'approve' => 'withdraw.approve',
                'post' => 'withdraw.update', // ใช้คีย์เดียวกับ approve (ตามดีไซน์ของคุณ)
            ],
        ];

        // 1) dynamic (DB)
        $cfg = $this->configStore->getJson('ops.withdraw', 'content');
        if (!is_array($cfg)) {
            $cfg = [];
        }

        // 2) fallback จากไฟล์ config
        $fileAccess = config('integrations.access.withdraw', []);
        $fileFlow = config('integrations.flows.withdraw', null);

        if (is_array($fileAccess)) {
            $cfg = array_replace_recursive($fileAccess, $cfg);
        }
        if (is_string($fileFlow) && !isset($cfg['flow'])) {
            $cfg['flow'] = $fileFlow;
        }

        // รวมกับ defaults
        $out = array_replace_recursive($defaults, $cfg);

        // อุด permissions ที่อาจหาย
        if (!isset($out['permissions']) || !is_array($out['permissions'])) {
            $out['permissions'] = $defaults['permissions'];
        }
        foreach (['create', 'check', 'approve', 'post'] as $k) {
            if (empty($out['permissions'][$k])) {
                $out['permissions'][$k] = $defaults['permissions'][$k];
            }
        }

        // validate flow + enforce กติกา
        if (!in_array($out['flow'], ['two_step', 'three_step'], true)) {
            $out['flow'] = 'three_step';
        }
        if ($out['flow'] === 'three_step') {
            $out['auto_post'] = false; // บังคับเดินครบขั้น
        }

        return $out;
    }

    /**
     * อนุมัติ/โอนให้ลูกค้า (step: approve [+ post])
     * หมายเหตุ: สำหรับสายถอน คุณตั้งใจให้ post ใช้ permission เดียวกับ approve
     */
    public function approve(int $withdrawId, object $actor): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['check']);

        /** @var Withdraw|null $p */
        $p = $this->withdrawRepository->find($withdrawId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status !== 0 || $p->ck_withdraw !== 'Y') {
            return $this->fail('รายการ ไม่สามารถตัดเครดิตได้');
        }

        // ตรงนี้แค่ mark/บันทึกข้อความ (ถ้าธุรกิจต้องการ field เพิ่ม สามารถขยายได้)
//        $p->msg = 'approved_by: ' . ($actor->user_name ?? 'system');
        $p->save();

        return $this->ok('ทำการตัดเครดิต ออกจากไอดีแล้ว');
    }

    public function post(int $withdrawId, object $actor): array
    {
        // โหลด policy/permission
        $policy = $this->policy();
        $flow = $policy['flow'] ?? 'two_step';

        $this->acl->must($actor, $policy['permissions']['post']);

        /** @var Withdraw|null $p */
        $p = $this->withdrawRepository->find($withdrawId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status === 1) {
            return $this->fail('รายการนี้ทำสำเร็จไปแล้ว');
        }
        if ($p->ck_withdraw === 'Y') {
            return $this->fail('มีคนกำลังทำรายการนี้อยู่');
        }

        // ตรวจความพร้อมแบบรวม
        $ready = $this->checkReady($p, $flow);
        if (!$ready['ok']) {
            return $this->fail($ready['msg']);
        }

        // CAS: กันซ้ำด้วย flag topupstatus = Y
        $updated = DB::table($p->getTable())
            ->where('code', $p->code)
            ->where('ck_withdraw', 'N')
            ->update(['ck_withdraw' => 'Y']);

//        dd($updated);

        if ($updated === 0) {
            return $this->fail('มีคนอื่นเริ่มทำก่อนหน้า');
        }

        try {
            $result = DB::transaction(function () use ($p, $actor) {

                $member = MemberWebProxy::where('user', $p->member_user)->first();
                if (!$member) {
                    throw new \RuntimeException('ไม่พบสมาชิก');
                }

                $website = WebsiteProxy::where('code', $member->web_code)->first();
                if (!$website) {
                    throw new \RuntimeException('ไม่พบ Agent/Website');
                }

                $amount = (float)$p->amount;
                if ($amount <= 0) {
                    throw new \RuntimeException('จำนวนเงินไม่ถูกต้อง');
                }

                // เรียก provider (ฝาก)
                $provider = $this->providers->resolve((string)($website->group_bot ?? ''));
                $ctx = new ApproveContext(
                    op: 'withdraw',
                    mode: 'manual',
                    username: $p->member_user,
                    amount: $amount,
                    website: $website,
                    timeoutSec: (int)config('integrations.providers.timeouts', 15),
                    retryTimes: (int)config('integrations.providers.retries.times', 2),
                    retrySleepMs: (int)config('integrations.providers.retries.sleep_ms', 300),
                    traceId: (string)Str::uuid(),
                );

                $res = $provider->approve($ctx);
                if (!$res->success) {
                    throw new \RuntimeException($res->msg ?: 'ถอนเงินล้มเหลว');
                }

                // อัปเดตยอดฝั่งเรา
                $webBefore = (float)$website->balance;
                $webAfter = $webBefore - $amount;
                $website->balance = $webAfter;
                $website->save();

                $p->fill([
                    'oldcredit' => $res->old_credit,
                    'aftercredit' => $res->after_credit,
                    'webbefore' => $webBefore,
                    'webafter' => $webAfter,
                    'ck_user' => $actor->user_name ?? 'system',
                    'ck_withdraw' => 'Y',
                    'ck_date' => now()->toDateTimeString(),
                ]);
                $p->save();

                return $this->ok('ตัดเครดิต ของลูกค้าเรียบร้อยแล้ว', [
                    'old' => $res->old_credit,
                    'after' => $res->after_credit,
                ]);
            }, 1);

            return $result;

        } catch (\Throwable $e) {
            // rollback flag กันค้าง
            DB::table($p->getTable())->where('code', $p->code)->update(['ck_withdraw' => 'N']);
            Log::error('Deposit post failed', [
                'payment_id' => $p->code,
                'err' => $e->getMessage(),
            ]);
            return $this->fail($e->getMessage() ?: 'มีปัญหาบางประการ');
        }
    }

    public function create(array $payload, object $actor): Withdraw
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['create']);

        $data = $payload;

        if ($data['amount'] < 1) {
            throw new \InvalidArgumentException('ยอดเงินไม่ถูกต้อง');
        }

//        dd($data);

        /** @var Withdraw $payment */
        $withdraw = DB::transaction(function () use ($data) {

            return $this->withdrawRepository->create($data);

        });

        return $withdraw;
    }

    // Orchestrator
    public function confirm(int $withdrawId, object $actor, array $dto = []): array
    {
        $this->acl->must($actor, $this->policy()['permissions']['approve']);

        return DB::transaction(function () use ($withdrawId, $actor, $dto) {

            $p = $this->withdrawRepository->findForUpdate($withdrawId);
            if (!$p) {
                return $this->fail('ไม่พบรายการ');
            }
            if ((int)$p->status === 1) {
                return $this->fail('รายการนี้ ดำเนินการเสร็จสิ้นทุกขั้นตอนแล้ว');
            }

            // --- เริ่มจากเซ็ตค่าเริ่มต้น ป้องกัน undefined ---
            $acc = null;
            $return = ['success' => 'NORMAL', 'msg' => '' ]; // ดีฟอลต์กันเคสไม่มีการตั้งค่า
//            Log::channel('cashback')->info('dtc' , [ 'dtc' => $dto ]);
            // (ออปชัน) map account ที่ใช้ดำเนินการ
            if (isset($dto['account_code'])) {
                $acc = $this->bankAccountRepo->findActiveByCode($dto['account_code'], $p->webcode ?? null);
//                Log::channel('cashback')->info('acc' , [ 'acc' => $acc ]);
                // ลบ dd() ออก — ห้ามหยุด flow ในโปรดักชัน
                // dd($acc);

                if (!$acc) {
                    return $this->fail('บัญชีที่ใช้ดำเนินการไม่ถูกต้อง');
                }

                // ตั้งค่าฟิลด์ตาม schema ที่คุณมีจริง
                $p->bank = $acc->code ?? $dto['account_code'];

                // สร้างข้อความ bankout อย่างระมัดระวัง (เช็คว่ามี relation/field ไหม)
                $bankName = $acc->bank;
                $accNo    = $acc->accountno;
                $p->bankout = trim($bankName . ' ' . $accNo);

            }

            // (ออปชัน) เวลาโอนจริง
            $transferAt = $dto['transfer_at'] ?? null;
            if (!$transferAt && !empty($dto['date_bank']) && !empty($dto['time_bank'])) {
                $transferAt = "{$dto['date_bank']} {$dto['time_bank']}:00";
            }

            // เก็บรูปแบบเดิมของตารางคุณ (หากต้องการ timestamp)
            // ถ้าอยากเก็บแยกคอลัมน์ก็ใช้สองฟิลด์ด้านล่างแทน
            // $p->date_bank = $transferAt ? Carbon::parse($transferAt)->timestamp : now()->timestamp;

            if (array_key_exists('fee', $dto)) {
                $p->fee = max(0, (float)$dto['fee']);
            }

            // เก็บค่าจากฟอร์มแบบแยกคอลัมน์ (ตามที่คุณใช้อยู่)
            $p->date_bank = $dto['date_bank'] ?? $p->date_bank;
            $p->time_bank = $dto['time_bank'] ?? $p->time_bank;

            $p->ckb_date   = now()->toDateTimeString();
            $p->ck_balance = 'Y';
            $p->ck_step2   = $actor->code;
            $p->ckb_user   = $actor->user_name ?? 'system';
//            $p->msg        = 'success';
            // อย่าเพิ่งตั้งเป็น 1 ถ้ายังต้องวิ่ง payment; ตั้งตอนตัดสินใจ flow
//            $p->status     = 1;

            // --- ตัดสินใจเส้นทาง Payment / Manual ---
            $isPayment = ($acc && (($acc->payment ?? 'N') === 'Y' || ($acc->status_auto ?? 'N') === 'Y'));

            if ($isPayment) {
                // Payment Gateway path
                $p->status = 9;              // processing
                $p->status_withdraw = 'A';   // processing
                $p->save();

                // เคสเฉพาะ bankid = 201 เรียก job ตรงนี้
                if (($acc->bankid ?? null) === 304) {
                    // สังเกต: dispatchNow อาจคืนค่าไม่ใช่ array; ใส่ตัวกันไว้
                    $res = PaymentOutKingPay::dispatchNow($withdrawId);
                    if (is_array($res)) {
                        $return = $res;
                    } else {
                        // กัน null/ผิดรูปแบบ
                        $return = ['success' => 'FAIL_AUTO', 'msg' => 'ผลลัพธ์จาก PaymentOutKingPay ไม่ถูกต้อง'];
                    }
                }

                // ตีความผลลัพธ์อย่างระวัง (ใช้ค่า default ที่ตั้งไว้ด้านบนด้วย)
                switch ($return['success']) {
                    case 'NORMAL':
                        // โอนไปเรียบร้อยตามปกติ
                        $p->status = 1;
                        $p->status_withdraw = 'W';
                        $p->save();
                        break;

                    case 'NOMONEY':
                    case 'FAIL_AUTO':
                        // rollback สถานะแบบที่คุณต้องการ
                        $p->ck_step2   = 0;
                        $p->ckb_user   = '';
                        $p->ck_balance = 'N';
                        $p->txid       = '';
                        $p->bank       = 0;
                        $p->bankout    = '';
                        $p->status     = 0;
                        $p->status_withdraw = 'W';
                        $p->save();
                        break;

                    case 'COMPLETE':
                    case 'NOTWAIT':
                    case 'MONEY':
                        // ถ้าต้องการเคสพิเศษ เพิ่ม logic ตรงนี้
                        break;
                }

                return $this->ok($return['msg'] ?? 'ส่งคำสั่ง Payment แล้ว');

            } else {
                // Manual path
                $p->status = 1;
                $p->save();
                return $this->ok('อนุมัติรายการเรียบร้อยแล้ว');
            }
        });
    }

    private function checkReady(Withdraw $p, string $flow): array
    {
        // ต้องยืนยัน username ก่อนเสมอ

        // ถ้าเป็น three_step ต้องผ่าน confirm (checking/checkstatus) มาก่อน
//        if ($flow === 'three_step') {
//            if ($p->ck_withdraw !== 'Y') {
//                return ['ok' => false, 'msg' => 'รายการยังไม่ผ่านการยืนยัน'];
//            }
//        }

        // ต้องยังไม่สำเร็จ
        if ((int)$p->status === 1) {
            return ['ok' => false, 'msg' => 'ทำรายการเสร็จแล้ว'];
        }

        return ['ok' => true, 'msg' => 'OK'];
    }

    private function ok(string $msg, array $ex = []): array
    {
        return ['success' => true, 'msg' => $msg] + $ex;
    }

    private function fail(string $msg, array $ex = []): array
    {
        return ['success' => false, 'msg' => $msg] + $ex;
    }
}
