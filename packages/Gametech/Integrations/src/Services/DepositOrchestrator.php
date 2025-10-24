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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DepositOrchestrator
{
    public function __construct(
        private ProviderManager       $providers,
        private AclAuthorizer         $acl,
        private BankPaymentRepository $bankPayments,
        private ConfigStore           $configStore,
    )
    {
    }

    /**
     * โหลดนโยบาย (defaults + override จาก table `configs` โดยใช้ name_en='ops.deposit', JSON อยู่ใน column `content`)
     */
    private function policy(): array
    {
        $defaults = [
            'flow'       => 'three_step', // 'two_step'|'three_step'
            'auto_post'  => false,
            'permissions'=> [
                'create'  => 'deposit.create',
                'check'   => 'deposit.check',
                'approve' => 'deposit.approve',
                'post'    => 'deposit.post.head', // ผู้อนุมัติขั้นสุดท้าย (หัวหน้า)
            ],
        ];

        // 1) ดึงค่า dynamic (DB)
        $cfg = $this->configStore->getJson('ops.deposit', 'content');
        if (!is_array($cfg)) {
            $cfg = [];
        }

        // 2) ดึงค่า fallback จากไฟล์ config
        $fileAccess = config('integrations.access.deposit', []);
        $fileFlow   = config('integrations.flows.deposit', null);

        // รวมไฟล์ → แล้วให้ DB ทับไฟล์ (DB = แหล่งความจริงล่าสุด)
        if (is_array($fileAccess)) {
            $cfg = array_replace_recursive($fileAccess, $cfg);
        }
        if (is_string($fileFlow) && !isset($cfg['flow'])) {
            $cfg['flow'] = $fileFlow;
        }

        // 3) รวมกับ defaults และอุดคีย์ที่หาย
        $out = array_replace_recursive($defaults, $cfg);

        if (!isset($out['permissions']) || !is_array($out['permissions'])) {
            $out['permissions'] = $defaults['permissions'];
        }
        foreach (['create', 'check', 'approve', 'post'] as $k) {
            if (empty($out['permissions'][$k])) {
                $out['permissions'][$k] = $defaults['permissions'][$k];
            }
        }

        // 4) บังคับกติกา flow ให้ถูกต้อง
        if (!in_array($out['flow'], ['two_step', 'three_step'], true)) {
            $out['flow'] = 'three_step';
        }
        if ($out['flow'] === 'three_step') {
            $out['auto_post'] = false; // ต้องกดตามขั้นตอนเสมอ
        }

        return $out;
    }

    /**
     * สร้างรายการฝาก (ยังไม่เติม ยกเว้นภายหลังเรียก maybeAutoPost แล้ว policy อนุญาต)
     */
    public function create(array $payload, object $actor): BankPayment
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['create']);

        $now = Carbon::now();

        $data = [
            'bank' => $payload['bank'] ?? null,
            'bankname' => $payload['bankname'] ?? null,
            'channel' => $payload['channel'] ?? 'API',
            'value' => (float)($payload['value'] ?? 0),

            // อาจว่าง แล้วไป confirm ภายหลัง
            'tranferer' => $payload['tranferer'] ?? '',
            'detail' => $payload['detail'] ?? '',

            // สถานะเริ่มต้น
            'checking' => 'N',
            'checkstatus' => 'N',
            'bankstatus' => 1,
            'status' => 0,
            'topupstatus' => 'N',

            // optional fields
            'source' => $payload['source'] ?? null,
            'source_ref' => $payload['source_ref'] ?? null,

            // audit
            'create_by' => trim(($actor->name ?? 'system') . ' ' . ($actor->surname ?? '')),

            'checktime' => strtotime(date('Y-m-d H:i:s')) ?? $now,
            'time' => isset($payload['time']) ? (int)$payload['time'] : time(),
        ];

        if ($data['value'] < 0.01) {
            throw new \InvalidArgumentException('ยอดเงินไม่ถูกต้อง');
        }

        /** @var BankPayment $payment */
        $payment = DB::transaction(function () use ($data, $now) {
            // ---- build tx_hash (md5 of bank + amount + username + timestamp) ----
            $bank = (string)($data['bank'] ?? '');
            $amount = number_format((float)$data['value'], 2, '.', '');
            $username = (string)($data['tranferer'] ?? '');
            $timestamp = core()->formatDate($data['time'] ?? $now.'Y-m-d H:i:s');

            $seed = implode('|', [$bank, $amount, $username, $timestamp]);
            $txHash = md5($seed);

            // กันกรณีชนกันแบบ edge-case
            if (\Gametech\Payment\Models\BankPayment::query()->where('tx_hash', $txHash)->exists()) {
                $txHash = md5($seed . '|' . microtime(true));
            }

            $data['tx_hash'] = $txHash;

            return $this->bankPayments->create($data);
        });

        return $payment;
    }


    /**
     * ยืนยันรายการ (กรณีสเต็ป 2): กรอก/ยืนยัน user + mark checking
     */
    public function confirm(int $paymentId, ?string $username, object $actor): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['check']);

        /** @var BankPayment|null $p */
        $p = $this->bankPayments->find($paymentId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status !== 0) {
            return $this->fail('สถานะไม่พร้อมสำหรับการยืนยัน');
        }

        if ($username !== null && $username !== '') {
            $p->tranferer = $username;
        }

        $p->checking = 'Y';
        $p->checktime = strtotime(date('Y-m-d H:i:s'));
        $p->checkstatus = 'Y';
        $p->check_user = $actor->user_name ?? 'system';
        $p->msg = 'success';
        $p->save();

        return $this->ok('ยืนยันรายการแล้ว');
    }

    /**
     * อนุมัติ (กลางทางใน three_step): แยกขั้นจาก post
     */
    public function approve(int $paymentId, object $actor): array
    {
        $policy = $this->policy();
        $this->acl->must($actor, $policy['permissions']['approve']);

        /** @var BankPayment|null $p */
        $p = $this->bankPayments->find($paymentId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status !== 0 || $p->checkstatus !== 'Y') {
            return $this->fail('ต้องยืนยันรายการก่อน หรือสถานะไม่ถูกต้อง');
        }

        // ตรงนี้แค่ mark/บันทึกข้อความ (ถ้าธุรกิจต้องการ field เพิ่ม สามารถขยายได้)
        $p->msg = 'approved_by: ' . ($actor->user_name ?? 'system');
        $p->save();

        return $this->ok('อนุมัติรายการแล้ว');
    }

    /**
     * ขั้นสุดท้าย: เติมเข้าไอดี (respect policy + CAS กันซ้ำ)
     * - ยิง ProviderManager ตาม group_bot
     * - อัปเดตยอด Website และเขียนผลลง bank_payment
     */
    public function post(int $paymentId, object $actor): array
    {
        // โหลด policy/permission
        $policy = $this->policy();
        $perm = $policy['permissions'] ?? [];
        $flow = $policy['flow'] ?? 'two_step';

        $this->acl->must($actor, $policy['permissions']['post']);

        /** @var BankPayment|null $p */
        $p = $this->bankPayments->find($paymentId);
        if (!$p) {
            return $this->fail('ไม่พบรายการ');
        }
        if ((int)$p->status === 1) {
            return $this->fail('รายการนี้ทำสำเร็จไปแล้ว');
        }
        if ($p->topupstatus === 'Y') {
            return $this->fail('มีคนกำลังทำรายการนี้อยู่');
        }

        // ตรวจความพร้อมแบบรวม
        $ready = $this->checkReady($p, $flow);
        if (!$ready['ok']) {
            return $this->fail($ready['msg']);
        }

        // CAS: กันซ้ำด้วย flag topupstatus = Y
        $updated = DB::table($p->getTable())
            ->where('id', $p->id)
            ->where(function ($q) {
                $q->whereNull('topupstatus')->orWhere('topupstatus', '!=', 'Y');
            })
            ->update(['topupstatus' => 'Y']);

        if ($updated === 0) {
            return $this->fail('มีคนอื่นเริ่มทำก่อนหน้า');
        }

        try {
            $result = DB::transaction(function () use ($p, $actor) {

                $member = MemberWebProxy::where('user', $p->tranferer)->first();
                if (!$member) {
                    throw new \RuntimeException('ไม่พบสมาชิก');
                }

                $website = WebsiteProxy::where('code', $member->web_code)->first();
                if (!$website) {
                    throw new \RuntimeException('ไม่พบ Agent/Website');
                }

                $amount = (float)$p->value;
                if ($amount <= 0) {
                    throw new \RuntimeException('จำนวนเงินไม่ถูกต้อง');
                }

                // เรียก provider (ฝาก)
                $provider = $this->providers->resolve((string)($website->group_bot ?? ''));
                $ctx = new ApproveContext(
                    op: 'deposit',
                    mode: 'manual',
                    username: $p->tranferer,
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
                    'webcode' => $member->web_code,
                    'status' => 1,
                    'oldcredit' => $res->old_credit,
                    'score' => $amount,
                    'aftercredit' => $res->after_credit,
                    'webbefore' => $webBefore,
                    'webafter' => $webAfter,
                    'user_id' => $actor->user_name ?? 'system',
                    'topupstatus' => 'Y',
                    'date_topup' => now()->toDateTimeString(),
                ]);
                $p->save();

                return $this->ok('เติมเงินสำเร็จ', [
                    'old' => $res->old_credit,
                    'after' => $res->after_credit,
                ]);
            }, 1);

            return $result;

        } catch (\Throwable $e) {
            // rollback flag กันค้าง
            DB::table($p->getTable())->where('id', $p->id)->update(['topupstatus' => 'N']);
            Log::error('Deposit post failed', [
                'payment_id' => $p->id,
                'err' => $e->getMessage(),
            ]);
            return $this->fail($e->getMessage() ?: 'มีปัญหาบางประการ');
        }
    }

    /**
     * รวม pre-check ให้ครบในจุดเดียว
     */
    private function checkReady(BankPayment $p, string $flow): array
    {
        // ต้องยืนยัน username ก่อนเสมอ
        if (!$p->tranferer) {
            return ['ok' => false, 'msg' => 'ต้องยืนยันไอดีลูกค้าก่อน'];
        }

        // ถ้าเป็น three_step ต้องผ่าน confirm (checking/checkstatus) มาก่อน
        if ($flow === 'three_step') {
            if ($p->checking !== 'Y' || $p->checkstatus !== 'Y') {
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

    /**
     * Auto post เมื่อ policy อนุญาต (ควรเรียกหลัง create)
     * - คืน array ถ้ายิงจริง
     * - คืน null ถ้า policy ไม่อนุญาตให้ auto
     */
    public function maybeAutoPost(BankPayment $payment, object $actor): ?array
    {
        $policy = $this->policy();
        if (empty($policy['auto_post'])) {
            return null; // ไม่ auto
        }

        // ผู้เรียกต้องมีสิทธิ์ post ด้วย
        $this->acl->must($actor, $policy['permissions']['post']);

        // ถ้าจะเคร่ง ให้ผ่าน confirm แล้วค่อย auto
        if ($payment->checkstatus !== 'Y') {
            return ['success' => false, 'msg' => 'ยังไม่ผ่านการยืนยัน ไม่สามารถ auto post'];
        }

        return $this->post($payment->id, $actor);
    }
}
