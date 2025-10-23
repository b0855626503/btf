<?php

namespace Gametech\Payment\Http\Controllers;

use App\Events\RealTimeMessage;
use Gametech\Payment\Libraries\CloudPay;
use Carbon\Carbon;
use Gametech\Core\Repositories\CheckCaseRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankAccountRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CloudPayController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberRepository;

    protected $bankRepository;

    protected $bankAccountRepository;

    protected $bankPaymentRepository;

    public function __construct(
        CheckCaseRepository   $repository,
        MemberRepository      $memberRepository,
        BankAccountRepository $bankAccountRepository,
        BankPaymentRepository $bankPaymentRepository,
        BankRepository        $bankRepository
    )
    {
        $this->_config = request('_config');

        $this->repository = $repository;

        $this->memberRepository = $memberRepository;

        $this->bankRepository = $bankRepository;

        $this->bankAccountRepository = $bankAccountRepository;

        $this->bankPaymentRepository = $bankPaymentRepository;
    }

    public function index($id)
    {

        $data = $this->repository->findOneWhere(['detail' => $id]);
        $member = $this->memberRepository->findOneWhere(['user_name' => $data->username]);

        return view('topup.cloudpay', compact('data', 'member'));
    }


    public function deposit(Request $request)
    {
        $api = new CloudPay;
        $request->validate([
            'amount' => 'required|numeric',
        ]);

        $member = auth()->guard('customer')->user();


        Log::channel('cloudpay_deposit_create')->info('เริ่มสร้างรายการฝาก', [
            'debug' => 'start',
        ]);

        $bank_account = $this->bankAccountRepository->findOneWhere(['banks' => 104, 'bank_type' => 1, 'enable' => 'Y', 'status_auto' => 'Y']);

        if (!$bank_account) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.fail');

            return response()->json($return);
        }

        $amount = (float)$request->input('amount');
        $amount = number_format($amount, 2, '.', '');

        $min_deposit = config('cloudpay.min_deposit', 100);
        if ($amount < $min_deposit) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.min_deposit', ['amount' => $min_deposit]);

            return response()->json($return);
        }


        $mer_no = config('cloudpay.merchant_no');
        $mer_order_no = 'CP_DEP-' . str_pad($member->code, 6, '0', STR_PAD_LEFT) . '-' . date('YmdHis');
        $pname = $member->name;
        $pemail = $member->tel . '@gmail.com';
        $phone = $member->tel;
        $order_amount = $amount;
        $ccy_no = 'IDR';
        $busi_code = '100402';
        $acc_no = $member->acc_no;
        $acc_bank = $api->Banks($member->bank_code);
        $notify_url = route('api.cloudpay.deposit.callback');

        if ($acc_bank === false) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.wrong_bank');

            return response()->json($return);

        }

        $param = [
            'mer_no' => trim($mer_no),
            'phone' => trim($phone),
            'pname' => trim($pname),
            'order_amount' => trim($order_amount),
            'mer_order_no' => trim($mer_order_no),
            'ccy_no' => trim($ccy_no),
            'pemail' => trim($pemail),
            'busi_code' => trim($busi_code),
            'accNo' => trim($acc_no),
            'accType' => trim($acc_bank),
            'notifyUrl' => trim($notify_url),
            'pageUrl' => trim(url('/')),
        ];

        $params = $api->hash_RSA_encrypted($param);
        $param['sign'] = $params['sign'];
//        dd($param);

        $url = config('cloudpay.api_url_deposit') . '/ty/orderPay';
        $response = $api->create($url, $param);
        if ($response['success'] === true) {
            $this->repository->create([
                'method' => 1,
                'bank_code' => $bank_account->banks,
                'txid' => $mer_order_no,
                'amount' => $amount,
                'payamount' => $amount,
                'username' => $member->user_name,
                'name' => $member->name,
                'detail' => $response['data']['err_code'],
                'url' => $response['data']['order_data'],
//                'qrcode' => $response['data']['qrcode'],
                'status' => $response['data']['status'],
                'user_create' => $member->name,
                'user_update' => $member->name,
            ]);

            $return['url'] = $response['url'];
            $return['msg'] = __('app.topup.create');
//            $return['code'] = $response['code'];
            $return['success'] = true;


        }
        $return['msg'] = $response['msg'];

        return response()->json($return);

    }

    public function deposit_callback(Request $request)
    {
        $datenow = now()->toDateTimeString();
        $message = $request->all();

        Log::channel('cloudpay_deposit_callback')->info('Callback การฝาก', $message);

        $refId = $message['mer_order_no'];
        $transactionId = $message['order_no'];
        $amount = $message['order_amount'];
        $payAmount = $message['pay_amount'] ?? $amount;
        $status = $message['status'];
        $username = $message['extendParams']['username'] ?? '';

//        UpdateBalancecloudpay::dispatch()->onQueue('topup');

        $case = $this->repository->findOneWhere(['txid' => $refId]);
        if ($case) {

            $this->repository->update([
                'status' => $status,
            ], $case->code);
        }


        if ($status === 'SUCCESS') {

            $member = $case->username;
            $bank_account = $this->bankAccountRepository->findOneWhere([
                'banks' => 104, 'bank_type' => 1, 'enable' => 'Y', 'status_auto' => 'Y',
            ]);


            $bank = $this->bankRepository->find($bank_account->banks);
            $detail = ' REF ID : ' . $transactionId;
            $hash = md5($bank_account->code . $amount . $detail);

            $data = [
                'bank' => strtolower($bank->shortcode . '_' . $bank_account->acc_no),
                'detail' => $detail . ' จำนวน ' . $amount,
                'account_code' => $bank_account->code,
                'autocheck' => 'W',
                'bankstatus' => 1,
                'bank_name' => $bank->shortcode,
                'bank_time' => $datenow,
                'channel' => 'QR',
                'value' => $amount,
                'tx_hash' => $hash,
                'txid' => $refId,
                'status' => 0,
                'ip_admin' => request()->ip(),
                'member_topup' => $member->code,
                'remark_admin' => '',
                'emp_topup' => 0,
                'user_create' => 'รอระบบเติมอัตโนมัติ ทำรายการฝากเงินโดย CloudPay QR',
                'create_by' => 'SYSAUTO',
            ];

            $check = $this->bankPaymentRepository->findOneWhere(['txid' => $refId]);
            if (!$check) {
                $this->bankPaymentRepository->create($data);
            }

            return response()->json(['code' => 0, 'msg' => 'success']);

        }

        return response()->json(['code' => 0, 'msg' => 'success']);

    }

    public function withdraw_callback(Request $request)
    {
        $config = core()->getConfigData();
        $datenow = now()->toDateTimeString();
        $message = $request->all();

        Log::channel('cloudpay_withdraw_callback')->info('Callback การฝาก', $message);

        $refId = $message['mer_order_no'];
        $transactionId = $message['order_no'];
        $amount = $message['order_amount'];
        $payAmount = $message['order_amount'] ?? $amount;
        $status = $message['status'];
//        $username = $message['extendParams']['username'] ?? '';

        $case = $this->repository->findOneWhere(['txid' => $refId]);
        if ($case) {

            $this->repository->update([
                'status' => $status,
            ], $case->code);
        }


//        UpdateBalancecloudpay::dispatch()->onQueue('topup');

        if ($config->seamless == 'Y') {
            $data = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->findOneWhere(['txid' => $refId]);
        } else {
            $data = app('Gametech\Payment\Repositories\WithdrawRepository')->findOneWhere(['txid' => $refId]);
        }

        if ($status === 'SUCCESS') {

            $data->remark_admin = '[ Ref No : ' . $transactionId . ' ] โอนให้ลุกค้าแล้ว ';
            $data->status_withdraw = 'C';
            $data->save();
            broadcast(new RealTimeMessage('CloudPay โอนเงินให้ลูกค้าแล้ว ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' รายการแจ้งถอนที่ ' . $data->code));

        } else {

            if ($config->seamless == 'Y') {
                $datanew = [
                    'refer_code' => $data->code,
                    'refer_table' => 'withdraws',
                    'remark' => 'คืนยอดจากการถอน ' . $refId,
                    'kind' => 'ROLLBACK',
                    'amount' => $amount,
                    'amount_balance' => $data->amount_balance,
                    'withdraw_limit' => $data->amount_limit,
                    'withdraw_limit_amount' => $data->amount_limit_rate,
                    'method' => 'D',
                    'member_code' => $data->member_code,
                    'emp_code' => 0,
                    'emp_name' => 'SYSTEM',
                ];
                $response = app('Gametech\Member\Repositories\MemberCreditLogRepository')->setWalletSeamlessWithdraw($datanew);
            } else {
                $datanew = [
                    'refer_code' => $data->code,
                    'refer_table' => 'withdraws',
                    'kind' => 'ROLLBACK',
                    'remark' => 'คืนยอดจากการแจ้งถอน ' . $refId,
                    'amount' => $amount,
                    'method' => 'D',
                    'member_code' => $data->member_code,
                    'emp_code' => 0,
                    'emp_name' => 'SYSTEM',
                ];
                $response = app('Gametech\Member\Repositories\MemberCreditLogRepository')->setWallet($datanew);

            }
            if ($response) {
                broadcast(new RealTimeMessage('CloudPay Payment โอนเงินไม่สำเร็จ ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' Ref ID ' . $refId . ' ระบบคืนยอดให้ลูกค้าแล้ว'));
                $data->remark_admin = '[ Ref ID :' . $transactionId . ' ] โอนไม่สำเร็จ และ ระบบคืนยอดแล้ว';
            } else {
                broadcast(new RealTimeMessage('CloudPay Payment โอนไม่สำเร็จ และระบบคืนยอดไม่ได้  ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' Ref ID ' . $refId));
                $data->remark_admin = '[ Ref ID :' . $transactionId . ' ] โอนไม่สำเร็จ โปรดคืนยอดให้ลูกค้าเอง ระบบคืนไม่ได้';
            }

            //            $data->remark_admin = '[Order No :'.$transactionId.'] ผิดพลาดไม่สามารถดำเนินการได้ - '.$data->remark_admin;
            $data->status_withdraw = 'R';
            $data->status = 2;
            $data->save();

        }

        return response()->json(['code' => 0, 'msg' => 'success']);

    }

    public function expire($txid)
    {
        $repo = $this->repository;

        $case = $repo->findOneWhere(['detail' => $txid]);
        if ($case && $case->status !== 'PAID') {
            $repo->update([
                'status' => 'EXPIRED',
            ], $case->code);
        }

        return response()->json(['success' => true]);
    }

    public function checkStatus($txid)
    {
        $case = $this->repository->findOneWhere(['detail' => $txid]);

        if (!$case) {
            return response()->json(['success' => false, 'status' => 'NOT_FOUND']);
        }

        return response()->json([
            'success' => true,
            'status' => $case->status, // เช่น 'PAID', 'EXPIRED', 'PENDING'
        ]);
    }

    public function qrDownloaded($txid)
    {
        $case = $this->repository->findOneWhere(['detail' => $txid]);

        if (!$case) {
            return response()->json(['success' => false, 'status' => 'NOT_FOUND']);
        }

        $case->downloaded += 1;
        $case->save();

        return response()->json(['success' => true]);
    }
}
