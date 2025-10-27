<?php

namespace Gametech\Admin\Http\Controllers;


use App\Exports\MembersExport;
use App\Exports\UsersExport;
use Gametech\Admin\DataTables\MemberDataTable;
use Gametech\Auto\Jobs\NotifyUserOfCompletedExport;
use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberBankProxy;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberDiamondLogRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberRemarkRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Models\BankPaymentProxy;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PragmaRX\Google2FA\Google2FA;


class MemberController extends AppBaseController
{
    private $_config;

    private $gameRepository;

    private $bankPaymentRepository;

    private $gameUserRepository;

    private $memberRepository;

    private $memberCreditLogRepository;

    private $memberPointLogRepository;

    private $memberDiamondLogRepository;

    private $memberRemarkRepository;

    /**
     * MemberController constructor.
     * @param GameUserRepository $gameUserRepo
     * @param GameRepository $gameRepo
     * @param MemberRepository $memberRepo
     * @param MemberCreditLogRepository $memberCreditLogRepo
     * @param MemberPointLogRepository $memberPointLogRepo
     * @param MemberDiamondLogRepository $memberDiamondLogRepo
     * @param BankPaymentRepository $bankPaymentRepo
     */
    public function __construct
    (
        GameUserRepository         $gameUserRepo,
        GameRepository             $gameRepo,
        MemberRepository           $memberRepo,
        MemberCreditLogRepository  $memberCreditLogRepo,
        MemberPointLogRepository   $memberPointLogRepo,
        MemberDiamondLogRepository $memberDiamondLogRepo,
        BankPaymentRepository      $bankPaymentRepo,
        MemberRemarkRepository     $memberRemarkRepo
    )

    {


        $this->_config = request('_config');

//        $this->middleware('admin');
        $this->middleware(['auth', 'admin']);

        $this->gameUserRepository = $gameUserRepo;

        $this->gameRepository = $gameRepo;

        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->memberPointLogRepository = $memberPointLogRepo;

        $this->bankPaymentRepository = $bankPaymentRepo;

        $this->memberDiamondLogRepository = $memberDiamondLogRepo;

        $this->memberRemarkRepository = $memberRemarkRepo;
    }


    public function index(MemberDataTable $memberDataTable)
    {
//        request()->session()->invalidate();
//
//        request()->session()->regenerateToken();


        return $memberDataTable->render($this->_config['view']);
    }


    public function edit(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $member = $this->memberRepository->find($id);
        if (!$member) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $member = $this->memberRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function editsub(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $member = $this->gameUserRepository->find($id);
        if (!$member) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $member = $this->gameUserRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $chk = $this->memberRepository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->memberRepository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function refill(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $google2fa = new Google2FA();

        $return['success'] = false;

        $datenow = now()->toDateTimeString();
        $date = now()->toDateString();
        $fulluser = $user->name . ' ' . $user->surname;
        $ip = $request->ip();

        $config = core()->getConfigData();

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'account_code' => 'required|string',
            'date_bank' => 'required|date_format:Y-m-d',
            'time_bank' => 'required|date_format:H:i',
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');

        $account = $request->input('account_code');
        $banks = explode('_', $account);
        $bank = $banks[0];

        $banktime = Carbon::createFromFormat('Y-m-d H:i:s', $request->date_bank . ' ' . $request->time_bank);

        $member = $this->memberRepository->findByField('user', $id)->first();

//        $bank_account = app('Gametech\Payment\Repositories\BankAccountRepository')->findByField(''$account);

//        $bank = app('Gametech\Payment\Repositories\BankRepository')->find($bank_account->banks);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);
        }

        $detail = 'เพิ่มรายการฝากเงินโดย Staff : ' . $fulluser;


        $hash = md5($account . $datenow . $amount . $detail);


        $checktime = strtotime(date('Y-m-d H:i:s'));
        $newpayment = BankPaymentProxy::firstOrNew(['tx_hash' => $hash, 'bank' => $account]);
        $newpayment->bank = $account;
        $newpayment->bankstatus = 1;
        $newpayment->bankname = strtoupper($bank);
        $newpayment->checktime = $checktime;
        $newpayment->time = $banktime;
        $newpayment->channel = 'MANUAL';
        $newpayment->value = $amount;
        $newpayment->tx_hash = $hash;
        $newpayment->detail = 'เพิ่มรายการฝากให้กับ User : ' . $id;
        $newpayment->atranferer = '';
        $newpayment->tranferer = $id;
        $newpayment->check_user = $user->user_name;
        $newpayment->checking = 'Y';
        $newpayment->checkstatus = 'Y';
        $newpayment->save();

//        $data = [
//            'bank' => strtolower($bank->shortcode . '_' . $bank_account->acc_no),
//            'detail' => $detail,
//            'account_code' => $account,
//            'autocheck' => 'W',
//            'bankstatus' => 1,
//            'bank_name' => $bank->shortcode,
//            'bank_time' => $datenow,
//            'channel' => 'MANUAL',
//            'value' => $amount,
//            'tx_hash' => $hash,
//            'status' => 0,
//            'ip_admin' => $ip,
//            'member_topup' => $id,
//            'remark_admin' => '',
//            'emp_topup' => $this->id(),
//            'user_create' => 'รอระบบเติมอัตโนมัติ ทำรายการฝากเงินโดย Staff : ' . $fulluser,
//            'create_by' => 'SYSAUTO'
//        ];
        $data = [];
        $response = $this->bankPaymentRepository->create($data);
        if ($response->code) {
            return $this->sendSuccess('ดำเนินการ ทำรายการฝากเงิน เรียบร้อยแล้ว');
        } else {
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง', 200);
        }


    }

    public function setWallet(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $google2fa = new Google2FA();

        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');
//        $secret = $request->input('one_time_password');
//
//        if($user->superadmin == 'N' && $user->google2fa_enable) {
//
//            $valid = $google2fa->verifyKey($user->google2fa_secret, $secret);
//            if (!$valid) {
//                return $this->sendError('รหัสยืนยันไม่ถูกต้อง', 200);
//            }
//        }

        $types = ['D' => 'เพิ่ม ยอดเงินคงเหลือ', 'W' => 'ลด ยอดเงินคงเหลือ'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 0) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);
        } elseif ($amount > $config['maxsetcredit']) {
            return $this->sendError('ไม่สามารถทำรายการเกินครั้งละ ' . core()->currency($config['maxsetcredit']), 200);
        } elseif ($method == 'W' && ($member->balance - $amount) < 0) {
            return $this->sendError('ยอดเงินหลังทำรายการ ไม่สามารถติดลบได้', 200);
        }

        $data = [
            'refer_code' => $id,
            'refer_table' => 'members',
            'kind' => 'SETWALLET',
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name . ' ' . $this->user()->surname
        ];

        if ($config->seamless == 'Y') {

            $response = $this->memberCreditLogRepository->setWalletSeamless($data);

        } else {

            if ($config->multigame_open == 'Y') {
                $response = $this->memberCreditLogRepository->setWallet($data);
            } else {
                $response = $this->memberCreditLogRepository->setWalletSingle($data);
            }

        }
        if ($response) {
            return $this->sendSuccess('ดำเนินการ ' . $types[$method] . ' เรียบร้อยแล้ว');
        } else {
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง', 200);
        }


    }

    public function setPoint(Request $request)
    {
        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');

        $types = ['D' => 'เพิ่ม Point', 'W' => 'ลด Point'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);
        } elseif ($method == 'W' && ($member->point_deposit - $amount) < 0) {
            return $this->sendError('ยอดเงินหลังทำรายการ ไม่สามารถติดลบได้', 200);
        }

        $data = [
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name . ' ' . $this->user()->surname
        ];

        $response = $this->memberPointLogRepository->setPoint($data);
        if ($response) {
            return $this->sendSuccess('ดำเนินการ ' . $types[$method] . ' เรียบร้อยแล้ว');
        } else {
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง', 200);
        }


    }

    public function setDiamond(Request $request)
    {
        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');

        $types = ['D' => 'เพิ่ม Diamond', 'W' => 'ลด Diamond'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอด Diamond ไม่ถูกต้อง', 200);
        } elseif ($method == 'W' && ($member->diamond - $amount) < 0) {
            return $this->sendError('ยอด Diamond หลังทำรายการ ไม่สามารถติดลบได้', 200);
        }

        $data = [
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name . ' ' . $this->user()->surname
        ];

        $response = $this->memberDiamondLogRepository->setDiamond($data);
        if ($response) {
            return $this->sendSuccess('ดำเนินการ ' . $types[$method] . ' เรียบร้อยแล้ว');
        } else {
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง', 200);
        }


    }


    public function gameLog(Request $request)
    {
        $id = $request->input('id');
        $method = $request->input('method');
        $header = '';
        $member = $this->memberRepository->findByField('user', $id)->first();
        $responses = [];

        switch ($method) {
            case 'gameuser':
                $header = 'ข้อมูลบัญชีเกม';
                $responses = $this->gameuser($id);
                break;

            case 'transfer':
                $header = 'ข้อมูลการโยก 50 รายการล่าสุด';
                $responses = $this->gametransfer($id);
                break;

            case 'deposit':
                $header = 'ข้อมูลการฝากเงิน 5 รายการล่าสุด';
                $responses = $this->gamedeposit($id);
                break;

            case 'withdraw':
                $header = 'ข้อมูลการถอน 50 รายการล่าสุด';
                $responses = $this->gamewithdraw($id);
                break;

            case 'setwallet':
                $header = 'ข้อมูลการ Set Wallet 50 รายการล่าสุด';
                $responses = $this->gamesetwallet($id);
                break;

            case 'setpoint':
                $header = 'ข้อมูลการ Set Point 50 รายการล่าสุด';
                $responses = $this->gamesetpoint($id);
                break;

            case 'setdiamond':
                $header = 'ข้อมูลการ Set Diamond 50 รายการล่าสุด';
                $responses = $this->gamesetdiamond($id);
                break;
        }


        $result['name'] = $member->me?->name . '(' . $header . ')';
        $result['list'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }

    public function gameuser($id)
    {

        $games = collect($this->gameRepository->getGameUserById($id, false)->toArray())->whereNotNull('game_user');

        $games = $games->map(function ($items) {
            $item = (object)$items;
            return [
                'status' => '<span class="text-danger">db</span>',
                'game_code' => $item->code,
                'game' => $item->name,
                'member_code' => $item->game_user['member_code'],
                'user_name' => $item->game_user['user_name'],
                'user_pass' => $item->game_user['user_pass'],
                'balance' => $item->game_user['balance'],
                'promotion' => (!is_null($item->game_user['promotion']) ? $item->game_user['promotion']['name_th'] : '-'),
                'turn' => ($item->game_user['pro_code'] > 0 ? $item->game_user['turnpro'] : '-'),
                'amount_balance' => ($item->game_user['pro_code'] > 0 ? $item->game_user['amount_balance'] : '-'),
                'withdraw_limit' => ($item->game_user['pro_code'] > 0 ? ($item->game_user['withdraw_limit'] > 0 ? $item->game_user['withdraw_limit'] : '-') : '-'),
                'action' => '<button class="btn btn-xs icon-only ' . ($item->game_user['enable'] == 'Y' ? 'btn-warning' : 'btn-danger') . '" onclick="editdatasub(' . $item->game_user['code'] . "," . "'" . core()->flip($item->game_user['enable']) . "'" . "," . "'enable'" . ')">' . ($item->game_user['enable'] == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-trash"></i>') . '</button>',
                'changepass' => '<button class="btn btn-xs icon-only btn-info" onclick="changegamepass(' . $item->game_user['code'] . ')"><i class="fa fa-edit"></i></button>',
            ];


        });

        return $games->values()->all();
    }

    public function gamesetwallet($id)
    {

        $responses = collect($this->memberCreditLogRepository->orderBy('date_create', 'desc')->findWhere(['member_code' => $id, 'kind' => 'SETWALLET', 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i'),
                'credit_amount' => $item->total,
                'credit_before' => $item->balance_before,
                'credit_balance' => $item->balance_after,
                'remark' => $item->remark,
                'credit_type' => $item->credit_type == 'D' ? '<span class="text-success">เพิ่ม Wallet</span>' : '<span class="text-danger">ลด Wallet</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamesetpoint($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberPointLogRepository')->orderBy('date_create', 'desc')->findWhere(['member_code' => $id, 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i'),
                'credit_amount' => $item->point_amount,
                'credit_before' => $item->point_before,
                'credit_balance' => $item->point_balance,
                'remark' => $item->remark,
                'credit_type' => $item->point_type == 'D' ? '<span class="text-success">เพิ่ม Point</span>' : '<span class="text-danger">ลด Point</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamesetdiamond($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberDiamondLogRepository')->orderBy('date_create', 'desc')->findWhere(['member_code' => $id, 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i'),
                'credit_amount' => $item->diamond_amount,
                'credit_before' => $item->diamond_before,
                'credit_balance' => $item->diamond_balance,
                'remark' => $item->remark,
                'credit_type' => $item->diamond_type == 'D' ? '<span class="text-success">เพิ่ม Diamond</span>' : '<span class="text-danger">ลด Diamond</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gametransfer($id)
    {

        $responses = collect($this->memberRepository->loadBill($id, '', '')->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'id' => '#BL' . Str::of($item->code)->padLeft(8, 0),
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i'),
                'amount' => $item->amount,
                'game_name' => $item->game['name'],
                'transfer' => $item->transfer_type == 1 ? '<span class="text-success">โยกเข้าเกม</span>' : '<span class="text-danger">โยกออกเกม</span>',
                'status' => $item->enable == 'Y' ? '<span class="text-danger">สำเร็จ</span>' : '<span class="text-warning">ไม่สำเร็จ</span>'
            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamedeposit($id)
    {


        $responses = collect($this->memberRepository->loadDeposit($id, '', '')->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;

            // ค่าพื้นฐาน
            $user = $item->create_by;
            $statusText = '<span class="px-2 py-1 rounded bg-secondary text-white">ไม่ทราบสถานะ</span>';

            $pill = fn($bg, $text) => "<span class='d-inline-block px-2 py-1 pill w-100 text-nowrap fw-semibold text-white' style='background:$bg;'>$text</span>";


            // === แยกเงื่อนไขตาม flow ที่ให้มา ===
            if ($item->status == 0 && $item->checkstatus === 'N') {
                $user = $item->create_by.'<br>[ แจ้งฝาก ]';
                $statusText = $pill('darkred', 'รอตรวจสอบ');
            } elseif ($item->status == 0 && $item->checkstatus === 'Y' && $item->topupstatus === 'N') {
                $user = $item->check_user.'<br>[ ตรวจสอบ ]';
                $statusText = $pill('#17a2b8', 'รอเติมเงิน'); // ฟ้า
            } elseif ($item->status == 1) {
                $user = $item->user_id.'<br>[ เติมเงิน ]';
                $statusText = $pill('#28a745', 'เติมเงินแล้ว'); // เขียว
            }

            return [
                'time'       => core()->formatDate($item->date_create, 'd/m/y H:i:s'),
                'time_topup' => core()->formatDate($item->date_topup, 'd/m/y H:i'),
                'amount'     => $item->value,
                'bank'       => $item->bank,
                'user_id'    => $user,
                'status'     => $statusText,
            ];

        });

        return $responses->values()->all();
    }

    public function gamewithdraw($id)
    {
        $responses = collect($this->memberRepository->loadWithdraw($id, '', '')->toArray());

//        $responses = collect($this->memberRepository->loadWithdraw($id, '', '')->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;

            $user = $item->user_create;
            $statusText = '<span class="px-2 py-1 rounded bg-secondary text-white">ไม่ทราบสถานะ</span>';

            $pill = fn($bg, $text) => "<span class='d-inline-block px-2 py-1 pill w-100 text-nowrap fw-semibold text-white' style='background:$bg;'>$text</span>";


            // === แยกเงื่อนไขตาม flow ที่ให้มา ===
            if ($item->status == 0 && $item->ck_withdraw === 'N') {
                $user = $item->user_create.'<br>[ แจ้งถอน ]';
                $statusText = $pill('darkred', 'รอตัดเครดิต');
            } elseif ($item->status == 0 && $item->ck_withdraw === 'Y' && $item->ck_balance === 'N') {
                $user = $item->ck_user.'<br>[ ตัดเครดิต ]';
                $statusText = $pill('#17a2b8', 'รอโอนเงิน'); // ฟ้า
            } elseif ($item->status == 1) {
                $user = $item->ckb_user.'<br>[ โอนเงิน ]';
                $statusText = $pill('#28a745', 'โอนแล้ว'); // เขียว
            }

            return [
                'time' => core()->formatDate($item->date_create, 'd/m/y H:i:s'),
                'amount' => $item->amount,
                'user_id' => $user,
                'status' => $statusText
            ];

        });

        return $responses->values()->all();
    }

    public function loadRefer()
    {
        $banks = [
            'value' => '',
            'text' => 'โปรดเลือก แหล่งอ้างอิง'
        ];

        $responses = collect(app('Gametech\Core\Repositories\ReferRepository')->findWhere(['enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'value' => $item->code,
                'text' => $item->name
            ];

        })->prepend($banks);


        $result['banks'] = $responses;
        return $this->sendResponseNew($result, 'complete');
    }

    public function loadConfig()
    {
       $config = core()->getConfigData();
       $data = [
           'userid' => ($config->member+1)
       ];
        return $this->sendResponse($data, 'complete');
    }

    public function loadBank()
    {
        $banks = [
            'value' => '',
            'text' => 'ธนาคาร'
        ];

        $responses = collect(app('Gametech\Payment\Repositories\BankRepository')->all()->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            return [
                'value' => $item->code,
                'text' => $item->name_th
            ];

        })->prepend($banks);


        $result['banks'] = $responses;
        return $this->sendResponseNew($result, 'complete');
    }

    public function loadBankAccount()
    {
        $banks = [
            'value' => '',
            'text' => 'เลือกช่องทางที่ฝาก'
        ];

//        $responses = app('Gametech\Payment\Repositories\BankRepository')->getBankInAccountAll()->toArray();
        $responses = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInAllNew()->toArray();

//        dd($responses);

        $responses = collect($responses)->map(function ($items) {
            $item = (object)$items;
//            dd($item);
            return [
                'value' => $item->code,
                'text' => strtoupper($item->bank) . ' - ' . $item->accountno . ' [' . $item->accountname . ']'
            ];

        })->prepend($banks);

//dd($responses);

        $result['banks'] = $responses;
        return $this->sendResponseNew($result, 'complete');
    }

    public function loadBankAccountUser(Request $request)
    {
        $id = $request->input('id');

        $member = $this->memberRepository->findByField('user', $id)->first();

        $banks = [
            'value' => '',
            'text' => 'เลือกธนาคารที่ต้องการ ใช้ดำเนินการ'
        ];

//        $responses = app('Gametech\Payment\Repositories\BankRepository')->getBankInAccountAll()->toArray();
        $responses = MemberBankProxy::where('member_code',$member->member_code)->where('enable','Y')->with('bank')->get();
//        dd($responses);

        $responses = collect($responses)->map(function ($items) {
            $item = (object)$items;
//            dd($item);
            return [
                'value' => $item->code,
                'text' => ($item->bank?->name_th) . ' [ ' . $item->account_no . ' ] ' . $item->account_name
            ];

        })->prepend($banks);

//dd($responses);

        $result['banks'] = $responses;
        return $this->sendResponseNew($result, 'complete');
    }

    public function create(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $data = json_decode($request['data'], true);
        $chk = $this->memberRepository->findOneWhere(['user_name' => $data['user_name']]);
        if ($chk) {
            return $this->sendError('ข้อมูลมีในระบบแล้ว', 200);
        }

        $agent = $data['agent'];
        unset($data['agent']);

        $bank_code = $data['bank_code'];
        if ($bank_code != 18) {
            $acc_no = Str::of($data['acc_no'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
            $data['acc_no'] = $acc_no;
        }


        if ($bank_code == 18) {
            $acc_check = '';
        } else if ($bank_code == 4) {
            $acc_check = substr($acc_no, -4);
        } else {
            $acc_check = substr($acc_no, -6);
        }

        if ($bank_code == 18) {
            $acc_bay = '';
        } else {
            $acc_bay = substr($acc_no, -7);
        }


        $data['confirm'] = 'Y';
        $data['date_regis'] = now()->toDateString();
        $data['acc_check'] = $acc_check;
        $data['acc_bay'] = $acc_bay;

        if (isset($data['wallet_id'])) {
            $data['wallet_id'] = $data['tel'];
        }

        $data['name'] = $data['firstname'] . ' ' . $data['lastname'];
        $data['password'] = Hash::make($data['user_pass']);

        $data['user_create'] = $user;
        $data['user_update'] = $user;


        $wallet_id = trim($data['wallet_id']);

        $validator = Validator::make($data, [
            'acc_no' => [
                'required',
                'digits_between:1,14',
                Rule::unique('members', 'acc_no')->where(function ($query) use ($bank_code) {
                    return $query->where('bank_code', $bank_code);
                })
            ],
            'wallet_id' => [
                'required',
                Rule::unique('members', 'wallet_id')->where(function ($query) use ($wallet_id) {
                    return $query->where('wallet_id', $wallet_id);
                })
            ],
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'tel' => 'required|numeric|unique:members,tel',
            'user_name' => 'required|string|different:tel|unique:members,user_name|max:20|regex:/^[a-z][a-z0-9]*$/',
            'bank_code' => 'required|numeric'
        ]);


        if ($validator->fails()) {
            $errors = $validator->errors();

            $message = implode(', ', $errors->all());
            return $this->sendError($message, 200);

        }

        $response = $this->memberRepository->create($data);
        if (!$response->code) {
            return $this->sendError('ไม่สามารถบันทุึกข้อมูลได้', 200);
        }

        if ($agent == 'Y') {
            $member = $this->memberRepository->find($response->code);
            $res = $this->gameUserRepository->addGameUser(1, $member->code, $member, false);
            if ($res['success'] !== true) {
                $this->memberRepository->delete($response->code);
                return $this->sendError('ไม่สามารถเพิ่มข้อมูลที่ Agent ได้', 200);
            }


        }

        $datasub['member_code'] = $response->code;
        $datasub['game_code'] = 1;
        $datasub['user_name'] = $data['user_name'];
        $datasub['user_pass'] = $data['user_pass'];

        $this->gameUserRepository->create($datasub);


        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update($id, Request $request)
    {

        $google2fa = new Google2FA();

        $acc_no = '';
        $user = $this->user()->name . ' ' . $this->user()->surname;

        $data = json_decode($request['data'], true);

//        $secret = $data['one_time_password'];

        if ($this->user()->superadmin == 'N') {

//            $valid = $google2fa->verifyKey($this->user()->google2fa_secret, $secret);
//            if (!$valid) {
//                return $this->sendError('รหัสยืนยันไม่ถูกต้อง', 200);
//            }
        }

//        unset($data['one_time_password']);


        $bank_code = $data['bank_code'];
        if ($bank_code != 18) {
            $acc_no = Str::of($data['acc_no'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
            $data['acc_no'] = $acc_no;
        }


        if ($bank_code == 18) {
            $acc_check = '';
        } else if ($bank_code == 4) {
            $acc_check = substr($acc_no, -4);
        } else {
            $acc_check = substr($acc_no, -6);
        }

        if ($bank_code == 18) {
            $acc_bay = '';
        } else {
            $acc_bay = substr($acc_no, -7);
        }


        $data['acc_check'] = $acc_check;
        $data['acc_bay'] = $acc_bay;

        $validator = Validator::make($data, [
            'acc_no' => [
                'required',
                'digits_between:1,15',
                Rule::unique('members', 'acc_no')->ignore($id, 'code')->where(function ($query) use ($bank_code) {
                    return $query->where('bank_code', $bank_code);
                })
            ],
            'tel' => [
                'required',
                'digits:10',
                Rule::unique('members', 'tel')->ignore($id, 'code')
            ]

        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $this->sendError($errors->messages(), 200);
        }


        $chk = $this->memberRepository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        if ($data['wallet_id'] == '') {
            $data['wallet_id'] = $chk->tel;
        }

        if ($data['user_pass']) {
            $data['password'] = Hash::make($data['user_pass']);
        } else {
            unset($data['user_pass']);
        }
        $data['firstname'] = strip_tags($data['firstname']);
        $data['lastname'] = strip_tags($data['lastname']);
        $data['tel'] = strip_tags($data['tel']);
        $data['wallet_id'] = strip_tags($data['wallet_id']);

        $data['name'] = $data['firstname'] . ' ' . $data['lastname'];
        $data['user_update'] = $user;
        $this->memberRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->memberRepository->find($id);
        if (!$data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function remark(Request $request)
    {
        $id = $id = $request->input('id');
        $responses = collect($this->memberRemarkRepository->loadRemark($id));

        $responses = $responses->map(function ($items) {
            $item = (object)$items;

            return [
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i:s'),
                'remark' => $item->remark,
                'emp_code' => (is_null($item->emp) ? '' : $item->emp->user_name),
                'action' => '<button type="button" class="btn btn-warning btn-xs icon-only" onclick="delSub(' . $item->code . ')"><i class="fa fa-times"></i></button>'

            ];

        });

        $result['list'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }

    public function createsub(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $data = $request->input('data');


        $data['member_code'] = $id;
        $data['emp_code'] = $this->id();
        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $this->memberRemarkRepository->create($data);


        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function destroysub(Request $request)
    {
        $id = $request->input('id');


        $chk = $this->memberRemarkRepository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->memberRemarkRepository->delete($id);


        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }


    public function balance(Request $request)
    {
        $id = $request->input('game_code');
        $member_code = $request->input('member_code');

        $item_list = $this->gameUserRepository->getOneUser($member_code, $id, true);
        if ($item_list['success'] != true) {

        }

        $item_list = $item_list['data'];
        $item = $item_list;


        $game = [
            'status' => '<span class="text-success">game</span>',
            'game_id' => $item['game']['id'],
            'game' => $item['game']['name'],
            'member_code' => $item['member_code'],
            'user_name' => $item['user_name'],
            'user_pass' => $item['user_pass'],
            'balance' => $item['balance'],
            'promotion' => (!is_null($item['promotion']) ? $item['promotion']['name_th'] : '-'),
            'turn' => ($item['pro_code'] > 0 ? $item['turnpro'] : '-'),
            'amount_balance' => ($item['pro_code'] > 0 ? $item['amount_balance'] : '-'),
            'withdraw_limit' => ($item['pro_code'] > 0 ? ($item['withdraw_limit'] > 0 ? $item['withdraw_limit'] : '-') : '-'),
            'action' => '<button class="btn btn-xs icon-only ' . ($item['enable'] == 'Y' ? 'btn-warning' : 'btn-danger') . '" onclick="editdatasub(' . $item['code'] . "," . "'" . core()->flip($item['enable']) . "'" . "," . "'enable'" . ')">' . ($item['enable'] == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-trash"></i>') . '</button>',
            'changepass' => '<button class="btn btn-xs icon-only btn-info" onclick="changegamepass(' . $item['code'] . ')"><i class="fa fa-edit"></i></button>',

        ];


        $result['list'] = $game;
        return $this->sendResponseNew($result, 'ดำเนินการเสร็จสิ้น');

    }

    public function changegamepass(Request $request)
    {
        $id = $request->input('id');
        $password = $request->input('password');
        $game_user = $this->gameUserRepository->find($id);
        if (!$game_user) {
            $result['success'] = false;
            $result['reload'] = false;
            $result['msg'] = 'ไม่พบข้อมูลรหัสเกมของ สมาชิก';

            return $this->sendResponseNew($result, 'ไม่พบข้อมูลรหัสเกมของ สมาชิก');

        }

        $game = $this->gameRepository->find($game_user->game_code);
        if (!$game) {
            $result['success'] = false;
            $result['reload'] = false;
            $result['msg'] = 'ไม่พบข้อมูลเกม';

            return $this->sendResponseNew($result, 'ไม่พบข้อมูลเกม');

        }

        $member = $this->memberRepository->find($game_user->member_code);
        if (!$member) {
            $result['success'] = false;
            $result['reload'] = false;
            $result['msg'] = 'ไม่พบข้อมูลสมาชิก';

            return $this->sendResponseNew($result, 'ไม่พบข้อมูลสมาชิก');

        }

        $user = collect($member)->toArray();
        $user_pass = $password;

        $result = $this->gameUserRepository->changeGamePass($game->code, $game_user['code'], [
            'user_pass' => $user_pass,
            'user_name' => $game_user['user_name'],
            'name' => $user['name'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'gender' => $user['gender'],
            'birth_day' => $user['birth_day'],
            'date_regis' => $user['date_regis'],
        ]);

        if ($result['success'] == true) {

            $result['reload'] = true;

        } else {
            $result['reload'] = false;
        }


        return $this->sendResponseNew($result, $result['msg']);

    }


    public function export(Request $request)
    {
        $user = Auth::guard('admin')->user();
        (new MembersExport)->queue('sheet', 'public')->chain([
            new NotifyUserOfCompletedExport($user, 'membersss.xlsx'),
        ]);;

        return back()->withSuccess('Export started!');
//        $filename = str::random(20).".xlsx";
//        (new MembersExport)->queue($filename, 'local')->chain([
//            new NotifyUserOfCompletedExport($this->user(),$filename ),
//        ]);
//
//        return json_encode([
//            'message' => "You will receive email with download link once export is complete."
//        ]);

//        return Excel::store(new MembersExport, function(MembersExport $export) {
//            return true;
//        });
//        new (new MembersExport)->store('members.xlsx');
//        return Excel::store(new MembersExport, 'member_' . date('Y-m-d') . '.xlsx','public',\Maatwebsite\Excel\Excel::XLSX);
    }

}
