<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\WithdrawDataTable;
use Gametech\Auto\Jobs\PaymentOutKbank;
use Gametech\Auto\Jobs\PaymentOutLuckyPay;
use Gametech\Auto\Jobs\PaymentOutPapayaPay;
use Gametech\Auto\Jobs\PaymentOutPomPay;
use Gametech\Auto\Jobs\PaymentOutScb;
use Gametech\Integrations\Contracts\ApproveContext;
use Gametech\Integrations\Services\DepositOrchestrator;
use Gametech\Integrations\Services\WithdrawOrchestrator;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Payment\Repositories\WithdrawRepository;
use Illuminate\Http\Request;

// ✅ เพิ่มเติม
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\Contracts\BalanceContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class WithdrawController extends AppBaseController
{
    /** @var mixed */
    protected $_config;

    /** @var \Gametech\Payment\Repositories\WithdrawRepository */
    protected $repository;

    /** @var \Gametech\Member\Repositories\MemberCreditLogRepository */
    protected $memberCreditLogRepository;

    // ใช้ใน loadUser()
    protected ProviderManager $providers;

    public function __construct(
        WithdrawRepository        $repository,
        MemberCreditLogRepository $memberCreditLogRepo,
        ProviderManager           $providers
    ) {
        $this->_config = request('_config');
        $this->middleware('admin');

        $this->repository                = $repository;
        $this->memberCreditLogRepository = $memberCreditLogRepo;
        $this->providers                 = $providers;
    }

    /* ---------------- Permission helper (อ่านจาก config) ---------------- */

    /**
     * ดึงชื่อ permission ของ withdraw ตาม action (create|check|approve|post)
     */
    protected function permissionName(string $action): ?string
    {
        $map = config('permissions.withdraw', []);
        return $map[$action] ?? null;
    }

    /**
     * assert ว่าผู้ใช้มีสิทธิ์ตาม permission map
     * - รองรับทั้ง Spatie Permission ($user->can())
     * - รองรับ Gate::allows($ability) ถ้ามีการ define gate ชื่อเดียวกับ permission
     * - ถ้าไม่ผ่าน -> ตอบ 403 JSON
     */
    protected function assertPermission(Request $request, string $action)
    {
        $ability = $this->permissionName($action);
        if (!$ability) {
            // ถ้าไม่ได้ตั้งค่าในคอนฟิก ถือว่า “ไม่ล็อกเพิ่ม” (ยังมี middleware('admin') คุมอยู่)
            return null;
        }

        $user = $request->user();

        // Spatie Permission / Laravel can()
        if (method_exists($user, 'can') && $user->can($ability)) {
            return null;
        }

        // Laravel Gate (กรณีประกาศ Gate::define($ability) ไว้)
        if (Gate::has($ability) && Gate::allows($ability, $user)) {
            return null;
        }

        // ไม่ผ่านทั้งสองอย่าง → 403
        abort(response()->json([
            'success' => false,
            'message' => 'Forbidden: ไม่มีสิทธิ์ (' . $ability . ')',
        ], 403));
    }

    /* ---------------- Controller methods เดิม + เพิ่ม create() ---------------- */

    public function index(WithdrawDataTable $withdrawDataTable)
    {
        return $withdrawDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id   = $request->input('id');
        $item = $this->repository->find($id);
        $data = $item?->only(['amount', 'member_user']);

        if (empty($data)) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        return $this->sendResponse($data, 'Complete');
    }

    public function edit(Request $request)
    {
        $user   = $this->user()->name . ' ' . $this->user()->surname;
        $id     = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    /**
     * ✅ สร้างรายการถอนใหม่ (step: create)
     * - หน้า Vue: withdrawSubmit() ควรยิงมาที่ route ของเมธอดนี้
     * - เช็คสิทธิ์ตาม config('permissions.withdraw.create')
     */
    public function create(Request $request)
    {
        // เช็คสิทธิ์จากคอนฟิก (withdraw.create)
        $this->assertPermission($request, 'create');

        // validate ตามแบบฟอร์ม
        $validated = $request->validate([
            'user_name'    => ['required', 'string'],
            'amount'       => ['required', 'numeric', 'min:1'],
            'bankm'        => ['required'],
            'date_bank'    => ['nullable', 'date'],
            'time_bank'    => ['nullable', 'string'],
            'remark_admin' => ['nullable', 'string'],
        ]);

        // map user_name -> member (ยึด logic loadUser)
        $member = MemberWebProxy::where('user', $validated['user_name'])->with('me')->first();
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบสมาชิกตาม User ID ที่ระบุ',
            ], 200);
        }

        // บิสสิเนสกฎง่าย ๆ (ออปชัน): ตรวจ limits จาก config
        $limits = (array) (config('withdraw_default.limits') ?? ['min' => 1, 'max' => 200000]);
        $min = (float) ($limits['min'] ?? 1);
        $max = (float) ($limits['max'] ?? 200000);
        if ($validated['amount'] < $min || $validated['amount'] > $max) {
            return response()->json([
                'success' => false,
                'message' => "จำนวนเงินต้องอยู่ระหว่าง {$min} - {$max}",
            ], 200);
        }

        $nowDate = now()->format('Y-m-d');
        $nowTime = now()->format('H:i');

        // mapping ฟิลด์สำหรับตาราง withdraws (อิงที่คุณใช้)
        $payload = [
            'member_code'      => $member->me->code ?? $member->code ?? null,
            'member_user'      => $validated['user_name'],
            'amount'           => (float) $validated['amount'],
            'bankm'            => $validated['bankm'],
            'date_bank'        => $validated['date_bank'] ?: $nowDate,
            'time_bank'        => $validated['time_bank'] ?: $nowTime,
            'date_record'      => trim(($validated['date_bank'] ?? $nowDate) . ' ' . ($validated['time_bank'] ?? $nowTime)),
            'webcode'          => $member->web_code,
            'status'           => 0,              // รอขั้น check
            'transection_type' => 1,
            'status_withdraw'  => 'W',            // waiting
            'enable'           => 'Y',
            'remark_admin'     => $validated['remark_admin'] ?? '',
            'user_update'      => $this->user()->name . ' ' . $this->user()->surname,
            'ck_step1'         => $this->user()->user_name,    // ผู้สร้าง
            'ip_admin'         => $request->ip(),
        ];

        $created = $this->repository->create($payload);

        if (!$created) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถสร้างรายการถอนได้',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'สร้างรายการถอนเรียบร้อย',
            'ref'     => $created->code ?? $created->id ?? null,
        ]);
    }

    /**
     * อนุมัติถอน (step: check) — โฟลว์เดิมของคุณ
     * หมายเหตุ: ถ้าจะล็อกสิทธิ์ขั้นนี้ ให้เรียก $this->assertPermission($request, 'check');
     */
    public function update_($id, Request $request)
    {
        // ถ้าต้องการล็อกสิทธิ์ในขั้น check:
        // $this->assertPermission($request, 'check');

        $ip      = $request->ip();
        $user    = $this->user()->name . ' ' . $this->user()->surname;
        $datenow = now()->toDateTimeString();

        $data = json_decode($request['data'], true);

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendSuccess('ไม่พบข้อมูลดังกล่าว');
        }

        $status_wd = ($chk->status_withdraw ?? null);
        if (!is_null($status_wd)) {
            // กันทำซ้ำ
            if ($chk->emp_approve > 0 || $chk->status_withdraw != 'W') {
                return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
            }

            $data['member_code'] = $chk->member_code;
            $data['amount']      = $chk->amount;
            $data['emp_approve'] = $this->id();
            $data['ip_admin']    = $ip;
            $data['user_update'] = $user;
            $data['date_approve']= $datenow;
            $this->repository->update($data, $id);

            // ผลโอนออกออโต้ (คงโค้ดเดิมของคุณไว้)
            $return = null;

            if (($data['account_code'] ?? 0) != 0) {
                $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutOne($data['account_code']);

                if (isset($bank)) {
                    $bank_code = $bank->bank->code;

                    if ($bank_code == 2) {
                        $return = PaymentOutKbank::dispatchNow($id);
                    } elseif ($bank_code == 4) {
                        $return = PaymentOutScb::dispatchNow($id);
                    } elseif ($bank_code == 99) {
                        $return = PaymentOutPomPay::dispatchNow($id);
                    } elseif ($bank_code == 101) {
                        $return = PaymentOutLuckyPay::dispatchNow($id);
                    } elseif ($bank_code == 102) {
                        $return = PaymentOutPapayaPay::dispatchNow($id);
                    }
                }
            }

            // Fallback
            if (empty($return) || !is_array($return)) {
                $return = [
                    'success'  => 'NORMAL',
                    'complete' => true,
                    'msg'      => 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)',
                ];
            }

            switch ($return['success']) {
                case 'NORMAL':
                    $datanew['status'] = 1;
                    $this->repository->update($datanew, $id);
                    break;

                case 'NOMONEY':
                case 'FAIL_AUTO':
                    $datanew['txid']            = '';
                    $datanew['account_code']    = 0;
                    $datanew['status_withdraw'] = 'W';
                    $datanew['status']          = 0;
                    $datanew['emp_approve']     = 0;
                    $datanew['ip_admin']        = '';
                    $this->repository->update($datanew, $id);
                    break;

                case 'COMPLETE':
                case 'NOTWAIT':
                case 'MONEY':
                    // no-op
                    break;
            }

            if (!empty($return['complete'])) {
                // บันทึก log เครดิต
                $member    = app('Gametech\Member\Repositories\MemberRepository')->find($chk->member_code);
                $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $chk->member_code);

                $this->memberCreditLogRepository->create([
                    'ip'                       => $ip,
                    'credit_type'              => 'D',
                    'balance_before'           => $member->balance,
                    'balance_after'            => $member->balance,
                    'credit'                   => 0,
                    'total'                    => $chk->amount,
                    'credit_bonus'             => 0,
                    'credit_total'             => 0,
                    'credit_before'            => $member->balance,
                    'credit_after'             => $member->balance,
                    'pro_code'                 => 0,
                    'bank_code'                => $chk->bankm_code,
                    'auto'                     => 'N',
                    'enable'                   => 'Y',
                    'user_create'              => 'System Auto',
                    'user_update'              => 'System Auto',
                    'refer_code'               => $id,
                    'refer_table'              => 'withdraws',
                    'remark'                   => 'เครดิตที่หักออกจากระบบ ' . $chk->balance . ' / จะได้รับยอดเงินผ่านเลขที่บัญชี : ' . $member->acc_no,
                    'kind'                     => 'CONFIRM_WD',
                    'amount'                   => $chk->amount,
                    'amount_balance'           => $game_user->amount_balance,
                    'withdraw_limit'           => $game_user->withdraw_limit,
                    'withdraw_limit_amount'    => $game_user->withdraw_limit_amount,
                    'method'                   => 'D',
                    'member_code'              => $chk->member_code,
                    'user_name'                => $member->user_name,
                    'emp_code'                 => $this->id(),
                    'emp_name'                 => $this->user()->name . ' ' . $this->user()->surname,
                ]);
            }

            return $this->sendSuccess($return['msg']);
        } else {
            if ($chk->emp_approve > 0) {
                return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
            }

            $data['emp_approve'] = $this->id();
            $data['status']      = 1;
            $data['ip_admin']    = $ip;
            $data['user_update'] = $user;
            $data['date_approve']= $datenow;
            $this->repository->update($data, $id);

            return $this->sendSuccess('ดำเนินการสำเร็จแล้ว');
        }
    }

    public function update(Request $request, WithdrawOrchestrator $flow, ProviderManager $providers)
    {
        $admin = $this->user();
        $id    = (int) $request->input('id');
        $op    = strtolower($request->input('op', 'deposit')); // 'deposit' | 'withdraw'

        if ($op === 'deposit') {
            $res = $flow->post($id, $admin);
            if (!($res['success'] ?? false)) {
                return $this->sendError($res['msg'] ?? 'มีปัญหาบางประการ ในการทำรายการ', 200);
            }
            return response()->json([
                'success' => true,
                'message' => $res['msg'] ?? 'เติมเงิน สำเร็จ',
            ]);
        }

        // ----- withdraw (ชั่วคราว) -----
        $chk = $this->repository->find($id);
        if (!$chk) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        if ($chk->topupstatus === 'Y' || (int)$chk->status === 1) {
            return $this->sendError('รายการนี้ ได้ดำเนินการแล้ว');
        }

        $amount = (float) $chk->value;
        if ($amount <= 0) return $this->sendError('จำนวนเงินไม่ถูกต้อง', 200);

        $updated = DB::table($chk->getTable())
            ->where('id', $chk->id)
            ->where(function($q){ $q->whereNull('topupstatus')->orWhere('topupstatus','!=','Y'); })
            ->update(['topupstatus' => 'Y']);
        if ($updated === 0) {
            return $this->sendError('รายการนี้ถูกดำเนินการโดยผู้อื่นแล้ว', 200);
        }

        try {
            $result = DB::transaction(function () use ($admin, $chk, $amount, $providers) {
                // ✅ ย้ายเข้า scope transaction เพื่อแก้บั๊กตัวแปร
                $member = MemberWebProxy::where('user', $chk->tranferer)->first();
                if (!$member) {
                    throw new \RuntimeException('ไม่พบข้อมูลสมาชิก');
                }

                $website = \Gametech\Core\Models\WebsiteProxy::where('code', $member->web_code)->lockForUpdate()->first();
                if (!$website) {
                    throw new \RuntimeException('ไม่พบข้อมูล Agent');
                }

                $provider = $providers->resolve((string)($website->group_bot ?? ''));

                $ctx = new ApproveContext(
                    op: 'withdraw',
                    mode: 'manual',
                    username: $chk->tranferer,
                    amount: $amount,
                    website: $website,
                    timeoutSec: (int)config('integrations.providers.timeouts', 15),
                    retryTimes: (int)config('integrations.providers.retries.times', 2),
                    retrySleepMs: (int)config('integrations.providers.retries.sleep_ms', 300),
                    traceId: (string) \Illuminate\Support\Str::uuid(),
                );

                $providerRes = $provider->approve($ctx);
                if (!$providerRes->success) {
                    throw new \RuntimeException($providerRes->msg ?: 'ดำเนินการไม่สำเร็จ');
                }

                $webBefore = (float)$website->balance;
                $webAfter  = $webBefore + $amount; // ถอน → agent ได้ยอดคืน (ตามกติกาธุรกิจ)

                $chk->fill([
                    'webcode'     => $member->web_code,
                    'status'      => 1,
                    'oldcredit'   => $providerRes->old_credit,
                    'score'       => $amount,
                    'aftercredit' => $providerRes->after_credit,
                    'webbefore'   => $webBefore,
                    'webafter'    => $webAfter,
                    'user_id'     => $admin->user_name,
                    'topupstatus' => 'Y',
                    'date_topup'  => Carbon::now()->toDateTimeString(),
                ]);
                $chk->save();

                $website->balance = $webAfter;
                $website->save();

                return [
                    'success' => true,
                    'msg'     => 'ถอนเงิน สำเร็จ',
                ];
            }, 1);

            return response()->json([
                'success' => true,
                'message' => $result['msg'] ?? 'ถอนเงิน สำเร็จ',
            ]);

        } catch (\Throwable $e) {
            DB::table($chk->getTable())->where('id', $chk->id)->update(['topupstatus' => 'N']);
            Log::error('Withdraw failed', [
                'id'        => $chk->id,
                'user'      => $chk->tranferer,
                'error'     => $e->getMessage(),
            ]);
            return $this->sendError($e->getMessage() ?: 'มีปัญหาบางประการ ในการทำรายการ', 200);
        }
    }


    public function clear(Request $request)
    {
        $config = core()->getConfigData();
        $user   = $this->user()->name . ' ' . $this->user()->surname;
        $id     = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        if ($chk->emp_approve > 0) {
            return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
        }

        $datanew = [
            'refer_code'  => $id,
            'refer_table' => 'withdraws',
            'remark'      => 'คืนยอดจากการถอน',
            'kind'        => 'ROLLBACK',
            'amount'      => $chk->amount,
            'method'      => 'D',
            'member_code' => $chk->member_code,
            'emp_code'    => $this->id(),
            'emp_name'    => $this->user()->name . ' ' . $this->user()->surname,
        ];

        $response = $config->multigame_open == 'Y'
            ? $this->memberCreditLogRepository->setWallet($datanew)
            : $this->memberCreditLogRepository->setWalletSingle($datanew);

        if ($response) {
            $data['ip_admin']     = $request->ip();
            $data['remark_admin'] = $remark;
            $data['status']       = 2;
            $data['emp_approve']  = $this->id();
            $data['user_update']  = $user;
            $data['date_approve'] = now()->toDateTimeString();
            $this->repository->update($data, $id);
        }

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroy(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id   = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['enable']      = 'N';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function fixSubmit(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id   = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['emp_approve']     = 0;
        $data['status_withdraw'] = 'W';
        $data['user_update']     = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadBank()
    {
        $banks = [
            'value' => '0',
            'text'  => 'ไม่ระบุบัญชี',
        ];

        $responses = app('Gametech\Payment\Repositories\BankAccountRepository')
            ->getAccountOutAll()
            ->toArray();

        $responses = collect($responses)->map(function ($items) {
            $item = (object) $items;
            return [
                'value' => $item->code,
                'text'  => $item->bank['name_th'] . ' [' . $item->acc_no . '] ' . $item->acc_name,
            ];
        })->prepend($banks);

        $result['banks'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }

    public function loadUser(Request $request)
    {
        $id = $request->input('id');

        // ดึง member + ความสัมพันธ์ที่ต้องการ (เหมือนเดิม)
        $member = MemberWebProxy::where('user', $id)->with('me')->first();
        if (empty($member)) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        // หา website/agent เพื่อรู้ group_bot
        $website = WebsiteProxy::where('code', $member->web_code)->first();
        if (!$website) {
            // คืนข้อมูลสมาชิกตามเดิม แต่แจ้งว่าไม่พบ agent
            return $this->sendResponse([
                'member'  => $member,
                'balance' => [
                    'success'   => false,
                    'credit'    => null,
                    'message'   => 'ไม่พบข้อมูล Agent ของสมาชิก',
                    'group_bot' => null,
                ],
            ], 'พบข้อมูลสมาชิก (ไม่มี Agent)');
        }

        $groupBot = (string) ($website->group_bot ?? '');

        // คีย์แคชย่อย ลดการยิง API ถี่ ๆ (TTL 10 วินาที)
        $cacheKey = "userbalance:{$groupBot}:{$member->user}";
        $balancePayload = Cache::remember($cacheKey, 10, function () use ($member, $website, $groupBot) {
            try {
                $provider = $this->providers->resolve($groupBot);
                $ctx = new BalanceContext(
                    username:     $member->user,
                    website:      $website,
                    timeoutSec:   (int) config('integrations.providers.timeouts', 15),
                    retryTimes:   (int) config('integrations.providers.retries.times', 2),
                    retrySleepMs: (int) config('integrations.providers.retries.sleep_ms', 300),
                );

                $res = $provider->balance($ctx);

                return [
                    'success'   => (bool) $res->success,
                    'credit'    => $res->credit,
                    'message'   => $res->msg ?: ($res->success ? 'OK' : 'ไม่รองรับการดึงยอด'),
                    'group_bot' => $groupBot,
                    'raw'       => $res->raw,
                ];
            } catch (\Throwable $e) {
                return [
                    'success'   => false,
                    'credit'    => null,
                    'message'   => 'ดึงยอดล้มเหลว: ' . $e->getMessage(),
                    'group_bot' => $groupBot,
                ];
            }
        });

        return $this->sendResponse([
            'member'  => $member,
            'balance' => $balancePayload,
        ], 'พบข้อมูลสมาชิก + ยอดคงเหลือล่าสุดจาก provider');
    }
}
