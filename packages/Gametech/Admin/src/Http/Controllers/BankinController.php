<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\BankinDataTable;
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;

// ✅ ใช้ Orchestrator (ฝาก)
use Gametech\Integrations\Services\DepositOrchestrator;

// ✅ สำหรับถอน (ชั่วคราว)
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\Contracts\ApproveContext;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankinController extends AppBaseController
{
    protected $_config;
    protected $repository;
    protected $memberRepository;

    public function __construct(
        BankPaymentRepository $repository,
        MemberRepository      $memberRepository
    )
    {
        $this->_config = request('_config');
        $this->middleware('admin');
        $this->repository = $repository;
        $this->memberRepository = $memberRepository;
    }

    public function index(BankinDataTable $bankinDataTable)
    {
        return $bankinDataTable->render($this->_config['view']);
    }

    public function clear(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);
        if (!$chk) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);

        $data = [
            'ip_admin' => $request->ip(),
            'remark_admin' => $remark,
            'status' => 2,
            'emp_topup' => $this->user()->code,
            'user_update' => $user,
            'date_approve' => now()->toDateTimeString(),
        ];
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroy(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);

        $data = [
            'enable' => 'N',
            'user_update' => $user,
        ];
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');
        $item = $this->repository->find($id);
        $data = $item?->only(['value', 'bank', 'tranferer']) + [
                'time' => $item?->time
                    ? \Carbon\Carbon::createFromTimestamp((int)$item->time)->format('d/m/y H:i:s')
                    : null,
            ];

        if (empty($data)) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        return $this->sendResponse($data, 'Complete');
    }

    public function loadUser(Request $request)
    {
        $id = $request->input('id');
        $data = MemberWebProxy::where('user', $id)->with('me')->first();

        if (empty($data)) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        return $this->sendResponse($data, 'พบข้อมูลสมาชิก');
    }

    /**
     * เดิมใช้แก้ tranferer + set checking → เปลี่ยนมาเรียก Orchestrator.confirm()
     */
    public function update($id, Request $request, DepositOrchestrator $flow)
    {
        $admin = $this->user();
        $data = json_decode($request['data'] ?? '{}', true);

        $username = $data['tranferer'] ?? null;
        if (!$username) return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);

        $res = $flow->confirm((int)$id, $username, $admin);
        if (!($res['success'] ?? false)) {
            return $this->sendError($res['msg'] ?? 'ยืนยันล้มเหลว', 200);
        }
        return $this->sendSuccess('บันทึกข้อมูลไอดีลูกค้า เรียบร้อยแล้ว');
    }

    /**
     * Manual approve/post
     * - deposit → ใช้ Orchestrator.post() (บังคับวิ่งตาม policy)
     * - withdraw → คงของเดิม (ยิง provider ตรง) — แนะนำย้ายไป WithdrawOrchestrator รอบถัดไป
     */
    public function approve(Request $request, DepositOrchestrator $flow)
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

    /**
     * เพิ่มรายการฝากแบบ manual: ใช้ Orchestrator
     * - ไม่ auto-post เอง นอกจาก policy อนุญาต และผ่าน maybeAutoPost()
     */
    public function refill(Request $request, DepositOrchestrator $flow)
    {
        $user = $this->user();

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'account_code' => 'required|string',
            'date_bank' => 'required|date_format:Y-m-d',
            'time_bank' => 'required|date_format:H:i',
        ]);

        $id = $request->input('id');
        $amount = (float)$request->input('amount');
        $account = $request->input('account_code');
        $banks = explode('_', $account);
        $bank = $banks[0] ?? '';

        if ($amount < 1) return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);

        $banktime = Carbon::createFromFormat('Y-m-d H:i', $request->date_bank . ' ' . $request->time_bank);

        // 1) สร้างผ่าน Orchestrator
        $payment = $flow->create([
            'bank' => $account,
            'bankstatus' => 1,
            'bankname' => strtoupper($bank),
            'channel' => 'MANUAL',
            'value' => $amount,
            'tranferer' => $id, // ถ้าไม่รู้ user ให้ส่งเป็น '' แล้วไป confirm ทีหลัง
            'detail' => 'เพิ่มรายการฝากโดย Staff : ' . $user->name . ' ' . $user->surname,
            'source' => 'manual',
            'source_ref' => null,
            'time' => $banktime->timestamp
        ], $user);

        // 2) ถ้าระบุ user มาแล้ว → confirm ให้เลย
        if (!empty($payment->tranferer)) {
//            $flow->confirm($payment->id, null, $user);
        }

        // 3) ลอง auto-post ตาม policy (ถ้าไม่เปิด จะคืน null)
        $auto = $flow->maybeAutoPost($payment, $user);

        if ($auto && ($auto['success'] ?? false)) {
            return $this->sendSuccess('สร้างและเติมเงินสำเร็จ (auto-post ตาม policy)');
        }
        return $this->sendSuccess('สร้างรายการฝากเรียบร้อย (รอยืนยัน/เติมตามขั้นตอน)');
    }

    public function cancel(Request $request)
    {
        $id = $request->input('id');
        $chk = $this->repository->find($id);
        if (!$chk) return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);

        $data = [
            'tranferer' => '',
            'check_user' => '',
            'checking' => 'N',
        ];
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }
}
