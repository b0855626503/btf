<?php

namespace Gametech\Integrations\Services;

use Carbon\Carbon;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Integrations\AclAuthorizer;
use Gametech\Integrations\Contracts\ApproveContext;
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\Support\ConfigStore;
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Payment\Models\BankPayment;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\WithdrawRepository; // หากคุณใช้สัญญา เปลี่ยนเป็น Contracts\WithdrawRepository
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
        private WithdrawRepository $repository,
        private ConfigStore           $configStore,
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
            'flow'       => 'three_step', // 'two_step'|'three_step'
            'auto_post'  => false,
            'permissions'=> [
                'create'  => 'withdraw.create',
                'check'   => 'withdraw.update',
                'approve' => 'withdraw.approve',
                'post'    => 'withdraw.approve', // ใช้คีย์เดียวกับ approve (ตามดีไซน์ของคุณ)
            ],
        ];

        // 1) dynamic (DB)
        $cfg = $this->configStore->getJson('ops.withdraw', 'content');
        if (!is_array($cfg)) {
            $cfg = [];
        }

        // 2) fallback จากไฟล์ config
        $fileAccess = config('integrations.access.withdraw', []);
        $fileFlow   = config('integrations.flows.withdraw', null);

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
     * กันการข้ามสเต็ปตาม flow ที่กำหนด
     */
    private function assertFlowStep(string $currentStep, array $state, array $policy): void
    {
        // คุณสามารถปรับกติกาเช็คสถานะจริง ๆ ของโมเดล/เรคคอร์ดได้ที่นี่
        // ตัวอย่างเชิงสัญลักษณ์ (ถ้าระบบคุณเก็บ state เช่น 'created', 'checked', 'approved' ที่ record):
        // - กำลัง "check" ต้องผ่าน "create" แล้ว
        // - กำลัง "approve" ต้องผ่าน "check" แล้ว (สำหรับ three_step)
        // หมายเหตุ: กติกานี้เป็นกรอบทั่วไป — ปรับให้ตรงกับ status ของตาราง withdraw จริง
        if ($policy['flow'] === 'three_step') {
            if ($currentStep === 'check' && empty($state['created'])) {
                throw new \RuntimeException('ผิดลำดับขั้นตอน: ยังไม่ได้สร้างรายการ');
            }
            if ($currentStep === 'approve' && (empty($state['created']) || empty($state['checked']))) {
                throw new \RuntimeException('ผิดลำดับขั้นตอน: ยังไม่ตรวจสอบรายการ');
            }
        } else {
            // two_step: อาจยุบเหลือ create → approve
            if ($currentStep === 'approve' && empty($state['created'])) {
                throw new \RuntimeException('ผิดลำดับขั้นตอน: ยังไม่ได้สร้างรายการ');
            }
        }
    }

    /**
     * สร้างคำขอถอน (step: create/request)
     * $payload: ['user_name','amount','bankm','date_bank','time_bank', ...]
     * $meta   : ['webcode'=>int, ...]
     */
    public function request(array $payload, array $meta = [], $actor = null): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['create']);

        try {
            // 1) หา member จาก user_name
            $member = MemberWebProxy::where('user', $payload['user_name'] ?? '')->with('me')->first();
            if (!$member) {
                return ['success' => false, 'message' => 'ไม่พบสมาชิกตาม User ID ที่ระบุ'];
            }

            // 2) ตรวจจำนวนเงิน
            $amount = (float)($payload['amount'] ?? 0);
            if ($amount < 1) {
                return ['success' => false, 'message' => 'จำนวนเงินไม่ถูกต้อง'];
            }

            // 3) สร้าง payload สำหรับ repository
            $now  = Carbon::now();
            $date = $payload['date_bank'] ?? $now->format('Y-m-d');
            $time = $payload['time_bank'] ?? $now->format('H:i');

            $data = [
                'member_code'  => $member->me->code ?? $member->code ?? null,
                'member_user'  => $payload['user_name'],
                'amount'       => $amount,
                'bankm'        => $payload['bankm'] ?? null,
                'date_bank'    => $date,
                'time_bank'    => $time,
                'date_record'  => trim("$date $time"),
                'webcode'      => $meta['webcode'] ?? ($member->me->webcode ?? null),
                'status'       => 'created', // แนะนำให้ใช้สถานะนี้เพื่อช่วย assertFlowStep
                'remark_admin' => $payload['remark_admin'] ?? '',
                'channel'      => $payload['channel'] ?? 'MANUAL',
            ];

            // 4) บันทึก
            // TODO: map to your repository methods
            $withdraw = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'สร้างคำขอถอนสำเร็จ',
                'ref'     => $withdraw->code ?? $withdraw->id ?? null,
                'data'    => $withdraw,
            ];
        } catch (Throwable $e) {
            Log::error('Withdraw request failed', [
                'payload' => $payload,
                'meta'    => $meta,
                'error'   => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'สร้างคำขอถอนไม่สำเร็จ: ' . $e->getMessage()];
        }
    }

    /**
     * ตรวจรายการ/ตัดเครดิตออกจากค่าย (step: check)
     * $payload: ช่องให้แนบหลักฐาน/หมายเหตุที่จำเป็น ฯลฯ
     */
    public function check($withdraw, array $payload = [], array $meta = [], $actor = null): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['check']);

        try {
            $state = [
                'created' => !empty($withdraw->status) && in_array($withdraw->status, ['created', 'checked', 'approved'], true),
                'checked' => !empty($withdraw->status) && in_array($withdraw->status, ['checked', 'approved'], true),
            ];
            $this->assertFlowStep('check', $state, $policy);

            // ตัดเครดิตออกจากค่ายเกม/ระบบ wallet ตาม provider ที่ผูก
            // หมายเหตุ: รายละเอียดจริงขึ้นกับ ProviderManager ในระบบคุณ
            // ตัวอย่างโครง (ให้คง log ไว้)
            Log::info('Withdraw check begin', ['ref' => $withdraw->code ?? $withdraw->id]);

            // TODO: map to your repository methods
            $updated = $this->repository->markChecked($withdraw, [
                'status'       => 'checked',
                'remark_admin' => $payload['remark_admin'] ?? $withdraw->remark_admin ?? '',
            ]);

            return [
                'success' => true,
                'message' => 'ตรวจรายการถอนสำเร็จ',
                'ref'     => $withdraw->code ?? $withdraw->id ?? null,
                'data'    => $updated ?? $withdraw,
            ];
        } catch (Throwable $e) {
            Log::error('Withdraw check failed', [
                'ref'   => $withdraw->code ?? $withdraw->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'ตรวจรายการถอนไม่สำเร็จ: ' . $e->getMessage()];
        }
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
        $p = $this->repository>find($withdrawId);
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
        $perm = $policy['permissions'] ?? [];
        $flow = $policy['flow'] ?? 'two_step';

        $this->acl->must($actor, $policy['permissions']['check']);

        /** @var Withdraw|null $p */
        $p = $this->repository->find($withdrawId);
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
                    throw new \RuntimeException($res->msg ?: 'เติมเงินล้มเหลว');
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
                    'ck_user' => $actor->name ?? 'system',
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

            return $this->repository->create($data);

        });

        return $withdraw;
    }

    public function confirm(int $withdrawId, object $actor): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['approve']);

        /** @var Withdraw|null $p */
        $p = $this->repository->find($withdrawId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status !== 0) {
            return $this->fail('รายการนี้ ดำเนินการเสร็จสิ้นทุกชั้นตอนแล้ว');
        }


        $p->bankout = 'Y';
        $p->checktime = strtotime(date('Y-m-d H:i:s'));
        $p->checkstatus = 'Y';
        $p->check_user = $actor->user_name ?? 'system';
        $p->msg = 'success';
        $p->save();

        return $this->ok('ยืนยันรายการแล้ว');
    }

    private function checkReady(Withdraw $p, string $flow): array
    {
        // ต้องยืนยัน username ก่อนเสมอ

        // ถ้าเป็น three_step ต้องผ่าน confirm (checking/checkstatus) มาก่อน
        if ($flow === 'three_step') {
            if ($p->ck_withdraw !== 'Y') {
                return ['ok' => false, 'msg' => 'รายการยังไม่ผ่านการยืนยัน'];
            }
        }

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
