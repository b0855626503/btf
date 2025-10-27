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
    )
    {
        $this->_config = request('_config');
        $this->middleware('admin');

        $this->repository = $repository;
        $this->memberCreditLogRepository = $memberCreditLogRepo;
        $this->providers = $providers;
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
        $id = $request->input('id');
        $data = $this->repository->with('memberBank.bank')->find($id);

        if (empty($data)) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        return $this->sendResponse($data, 'Complete');
    }

    public function edit(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
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
    public function create(Request $request, WithdrawOrchestrator $flow)
    {

        $admin = $this->user();
        // validate ตามแบบฟอร์ม
        $validated = $request->validate([
            'user_name' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'bankm' => ['required'],
            'remark_admin' => ['nullable', 'string'],
        ]);


        if ($validated['amount'] < 1) return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);

        $nowDate = now()->format('Y-m-d');
        $nowTime = now()->format('H:i:s');

        $member = MemberWebProxy::where('user', $validated['user_name'])->with('me')->first();

        // mapping ฟิลด์สำหรับตาราง withdraws (อิงที่คุณใช้)
        $payload = [
            'member_code' => $member->me->code ?? $member->code ?? null,
            'member_user' => $validated['user_name'],
            'amount' => (float)$validated['amount'],
            'bankm' => $validated['bankm'],
            'date_record' => trim(($validated['date_bank'] ?? $nowDate)),
            'timedept' => trim(($validated['time_bank'] ?? $nowTime)),
            'webcode' => $member->web_code,
            'status' => 0,              // รอขั้น check
            'transection_type' => 1,
            'status_withdraw' => 'W',            // waiting
            'enable' => 'Y',
            'user_create' => $admin->user_name,
            'ck_step1' => $admin->code,    // ผู้สร้าง
            'ip' => $request->ip(),
        ];


        $created = $flow->create($payload, $admin);

        if (!$created) {
            return $this->sendError('สร้างรายการแจ้งถอน ไม่สำเร็จ', 200);
        }

        return $this->sendSuccess('สร้างรายการแจ้งถอนเรียบร้อย (ตัดเครดิต/เติมตามขั้นตอน)');

    }

    public function update(Request $request, WithdrawOrchestrator $flow)
    {
        $admin = $this->user();
        $id = (int)$request->input('id');

        $res = $flow->post($id, $admin);
        if (!($res['success'] ?? false)) {
            return $this->sendError($res['msg'] ?? 'มีปัญหาบางประการ ในการทำรายการ', 200);
        }
        return response()->json([
            'success' => true,
            'message' => $res['msg'] ?? 'เติมเงิน สำเร็จ',
        ]);

    }


    public function clear(Request $request)
    {
        $config = core()->getConfigData();
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        if ($chk->emp_approve > 0) {
            return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
        }

        $datanew = [
            'refer_code' => $id,
            'refer_table' => 'withdraws',
            'remark' => 'คืนยอดจากการถอน',
            'kind' => 'ROLLBACK',
            'amount' => $chk->amount,
            'method' => 'D',
            'member_code' => $chk->member_code,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name . ' ' . $this->user()->surname,
        ];

        $response = $config->multigame_open == 'Y'
            ? $this->memberCreditLogRepository->setWallet($datanew)
            : $this->memberCreditLogRepository->setWalletSingle($datanew);

        if ($response) {
            $data['ip_admin'] = $request->ip();
            $data['remark_admin'] = $remark;
            $data['status'] = 2;
            $data['emp_approve'] = $this->id();
            $data['user_update'] = $user;
            $data['date_approve'] = now()->toDateTimeString();
            $this->repository->update($data, $id);
        }

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroy(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['enable'] = 'N';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadBank()
    {
        $responses = app('Gametech\Payment\Repositories\BankAccountRepository')
            ->getAccountOutAll()
            ->toArray();

//        dd($responses);

        $responses = collect($responses)->map(function ($items) {
            $item = (object)$items;
//            dd($item);
            return [
                'value' => $item->code,
                'text' => strtoupper($item->bank) . ' - ' . $item->accountno . ' [' . $item->accountname . '] - '.$item->balance
            ];

        });


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
                'member' => $member,
                'balance' => [
                    'success' => false,
                    'credit' => null,
                    'message' => 'ไม่พบข้อมูล Agent ของสมาชิก',
                    'group_bot' => null,
                ],
            ], 'พบข้อมูลสมาชิก (ไม่มี Agent)');
        }

        $groupBot = (string)($website->group_bot ?? '');

        // คีย์แคชย่อย ลดการยิง API ถี่ ๆ (TTL 10 วินาที)
        $cacheKey = "userbalance:{$groupBot}:{$member->user}";
        $balancePayload = Cache::remember($cacheKey, 10, function () use ($member, $website, $groupBot) {
            try {
                $provider = $this->providers->resolve($groupBot);
                $ctx = new BalanceContext(
                    username: $member->user,
                    website: $website,
                    timeoutSec: (int)config('integrations.providers.timeouts', 15),
                    retryTimes: (int)config('integrations.providers.retries.times', 2),
                    retrySleepMs: (int)config('integrations.providers.retries.sleep_ms', 300),
                );

                $res = $provider->balance($ctx);

                return [
                    'success' => (bool)$res->success,
                    'credit' => $res->credit,
                    'message' => $res->msg ?: ($res->success ? 'OK' : 'ไม่รองรับการดึงยอด'),
                    'group_bot' => $groupBot,
                    'raw' => $res->raw,
                ];
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'credit' => null,
                    'message' => 'ดึงยอดล้มเหลว: ' . $e->getMessage(),
                    'group_bot' => $groupBot,
                ];
            }
        });

        return $this->sendResponse([
            'member' => $member,
            'balance' => $balancePayload,
        ], 'พบข้อมูลสมาชิก + ยอดคงเหลือล่าสุดจาก provider');
    }

    // Controller
    public function approve(Request $request, WithdrawOrchestrator $flow)
    {
        $admin = $this->user();
        $id = (int)$request->input('id');

        // (ออปชัน) รวมวัน+เวลาเป็น timestamp เดียว ฝั่งฟอร์มสามารถส่ง transfer_at มาเลยก็ได้
        $transferAt = $request->input('transfer_at');
        if (!$transferAt) {
            $d = $request->input('date_bank'); // 'YYYY-MM-DD'
            $t = $request->input('time_bank'); // 'HH:mm'
            if ($d && $t) {
                $transferAt = \Carbon\Carbon::parse("$d $t:00")->toDateTimeString();
            }
        }

        $dto = [
            'account_code' => $request->input('account_code'),   // บัญชีที่ใช้ดำเนินการ
            'fee' => (float)$request->input('fee', 0),
            'transfer_at' => $transferAt,
            'date_bank' => $d,
            'time_bank' => $t,
        ];

        $res = $flow->confirm($id, $admin, $dto);
        if (!($res['success'] ?? false)) {
            return $this->sendError($res['msg'] ?? 'ยืนยันล้มเหลว', 200);
        }
        return $this->sendSuccess('อนุมัติรายการ แจ้งถอน เรียบร้อยแล้ว');
    }

}
