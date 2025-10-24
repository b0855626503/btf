<?php

namespace Gametech\Integrations\Services;

use Gametech\Integrations\ProviderManager;
use Gametech\Payment\Repositories\WithdrawRepository; // ถ้าใช้สัญญา ให้สลับเป็น Contracts\WithdrawRepository
use Gametech\Member\Models\MemberWebProxy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class WithdrawOrchestrator
{
    protected WithdrawRepository $repository;
    protected ProviderManager $providers;

    public function __construct(
        WithdrawRepository $repository,
        ProviderManager $providers
    ) {
        // fallback กันกรณีมีการ new เอง (ควรให้ container ทำ)
        $this->repository = $repository ?? app(WithdrawRepository::class);
        $this->providers  = $providers  ?? app(ProviderManager::class);
    }

    /**
     * สร้าง "รายการถอนใหม่" จาก payload หน้าบ้านแอดมิน
     *
     * ข้อมูลที่คาดหวัง:
     * - user_name (User ID), amount, bankm, date_bank, time_bank, remark_admin (optional)
     * - meta: ['admin_name' => '', 'admin_username' => '', 'ip' => '', 'webcode' => '']
     *
     * @param  array $payload  ข้อมูลจากฟอร์ม
     * @param  array $meta     ข้อมูลเพิ่มเติมของ admin/request
     * @return array{success:bool,message:string,ref?:mixed,model?:mixed}
     */
    public function create(array $payload, array $meta = []): array
    {
        try {
            // 1) หา member จาก user_name (ตามที่ controller ใช้อยู่จริง)
            $member = MemberWebProxy::where('user', $payload['user_name'] ?? '')->with('me')->first();
            if (!$member) {
                return [
                    'success' => false,
                    'message' => 'ไม่พบสมาชิกตาม User ID ที่ระบุ',
                ];
            }

            // 2) กติกาทางธุรกิจเบื้องต้น
            $amount = (float) ($payload['amount'] ?? 0);
            if ($amount < 1) {
                return ['success' => false, 'message' => 'จำนวนเงินไม่ถูกต้อง'];
            }

            // (ออปชัน) กันมีรายการค้างอยู่แล้วต่อคน (ถ้าต้องการ)
            // if ($this->repository->query()->where('member_code', $member->me->code)->where('status_withdraw', 'W')->exists()) {
            //     return ['success' => false, 'message' => 'มีรายการรออยู่แล้ว'];
            // }

            // 3) จัด payload ให้เข้ากับ schema จริงของคุณ
            $nowDate = now()->format('Y-m-d');
            $nowTime = now()->format('H:i');

            $data = [
                'member_code'       => $member->me->code ?? $member->code ?? null,
                'member_user'       => $payload['user_name'],
                'amount'            => $amount,
                'bankm'             => $payload['bankm'],
                'date_bank'         => $payload['date_bank'] ?: $nowDate,
                'time_bank'         => $payload['time_bank'] ?: $nowTime,
                'date_record'       => trim(($payload['date_bank'] ?: $nowDate) . ' ' . ($payload['time_bank'] ?: $nowTime)),
                'webcode'           => $meta['webcode'] ?? ($member->web_code ?? null),
                'status'            => 0,
                'transection_type'  => 1,
                'status_withdraw'   => 'W',
                'enable'            => 'Y',
                'user_update'       => $meta['admin_name'] ?? null,
                'ck_step1'          => $meta['admin_username'] ?? null,
                'ip_admin'          => $meta['ip'] ?? null,
                'remark_admin'      => $payload['remark_admin'] ?? '',
            ];

            // 4) สร้าง record ผ่าน repository
            $created = $this->repository->create($data);

            if (!$created) {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถสร้างรายการถอนได้',
                ];
            }

            return [
                'success' => true,
                'message' => 'สร้างรายการถอนเรียบร้อย',
                'ref'     => $created->code ?? $created->id ?? null,
                'model'   => $created,
            ];
        } catch (Throwable $e) {
            Log::error('Withdraw create failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return [
                'success' => false,
                'message' => 'สร้างรายการถอนไม่สำเร็จ: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * อนุมัติถอน (ของเดิม)
     */
    public function approve($idOrCode, array $meta = []): array
    {
        $withdraw = $this->repository->query()
            ->where('id', $idOrCode)
            ->orWhere('code', $idOrCode)
            ->first();

        if (!$withdraw) {
            return [
                'success' => false,
                'message' => 'ไม่พบรายการถอน',
                'old'     => null,
                'after'   => null,
            ];
        }

        if (method_exists($withdraw, 'canApprove') && !$withdraw->canApprove()) {
            return [
                'success' => false,
                'message' => 'สถานะปัจจุบันไม่สามารถอนุมัติได้',
                'old'     => null,
                'after'   => null,
                'ref'     => $withdraw->code ?? $withdraw->id,
            ];
        }

        $oldCredit = $withdraw->member->credit ?? null;

        try {
            // เลือก provider (ถ้าคุณใช้ทำ auto payout ที่ชั้น service)
            $providerKey = $withdraw->provider ?? $withdraw->bank_code ?? $withdraw->channel ?? null;
            $provider    = $this->providers->withdraw($providerKey);

            // รองรับชื่อเมธอดต่างค่าย
            if (method_exists($provider, 'withdraw')) {
                $providerRes = $provider->withdraw($withdraw);
            } elseif (method_exists($provider, 'payout')) {
                $providerRes = $provider->payout($withdraw);
            } elseif (method_exists($provider, 'transferOut')) {
                $providerRes = $provider->transferOut($withdraw);
            } else {
                return [
                    'success' => false,
                    'message' => 'ผู้ให้บริการถอน ไม่รองรับเมธอดที่กำหนด',
                    'old'     => $oldCredit,
                    'after'   => $withdraw->member->fresh()->credit ?? null,
                    'ref'     => $withdraw->code ?? $withdraw->id,
                ];
            }

            // อัปเดตสถานะผ่าน repository ถ้ามี helper
            if (method_exists($this->repository, 'markApproved')) {
                $this->repository->markApproved($withdraw, [
                    'actor'   => $meta['actor'] ?? null,
                    'remark'  => $meta['remark'] ?? null,
                    'payload' => $providerRes ?? null,
                ]);
            } else {
                $withdraw->status = $withdraw->status ?? 'approved';
                if (isset($withdraw->approved_at)) $withdraw->approved_at = now();
                if (isset($withdraw->approved_by) && isset($meta['actor'])) $withdraw->approved_by = $meta['actor'];
                $withdraw->save();
            }

            $afterCredit = $withdraw->member->fresh()->credit ?? null;

            return [
                'success' => true,
                'message' => Arr::get($providerRes ?? [], 'message', 'ถอนเงินสำเร็จ'),
                'old'     => $oldCredit,
                'after'   => $afterCredit,
                'ref'     => $withdraw->code ?? $withdraw->id,
            ];
        } catch (Throwable $e) {
            Log::error('Withdraw approve failed', [
                'ref'   => $withdraw->code ?? $withdraw->id,
                'error' => $e->getMessage(),
            ]);

            if (method_exists($this->repository, 'markFailed')) {
                $this->repository->markFailed($withdraw, ['reason' => $e->getMessage()]);
            }

            return [
                'success' => false,
                'message' => 'ถอนเงินไม่สำเร็จ: ' . $e->getMessage(),
                'old'     => $oldCredit,
                'after'   => $withdraw->member->fresh()->credit ?? null,
                'ref'     => $withdraw->code ?? $withdraw->id,
            ];
        }
    }

    /**
     * ปฏิเสธถอน (ของเดิม)
     */
    public function reject($idOrCode, ?string $reason = null): array
    {
        $withdraw = $this->repository->query()
            ->where('id', $idOrCode)
            ->orWhere('code', $idOrCode)
            ->first();

        if (!$withdraw) {
            return [
                'success' => false,
                'message' => 'ไม่พบรายการถอน',
            ];
        }

        try {
            if (method_exists($this->repository, 'markRejected')) {
                $this->repository->markRejected($withdraw, ['reason' => $reason]);
            } else {
                $withdraw->status = $withdraw->status ?? 'rejected';
                if (isset($withdraw->rejected_at)) $withdraw->rejected_at = now();
                if (isset($withdraw->reject_reason) && $reason) $withdraw->reject_reason = $reason;
                $withdraw->save();
            }

            return [
                'success' => true,
                'message' => 'ปฏิเสธรายการถอนแล้ว',
                'ref'     => $withdraw->code ?? $withdraw->id,
            ];
        } catch (Throwable $e) {
            Log::error('Withdraw reject failed', [
                'ref'   => $withdraw->code ?? $withdraw->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'ปฏิเสธไม่สำเร็จ: ' . $e->getMessage(),
                'ref'     => $withdraw->code ?? $withdraw->id,
            ];
        }
    }
}
