<?php

namespace Gametech\Payment\Http\Controllers;

use App\Events\RealTimeMessage;
use Exception;
use Gametech\Payment\Helpers\WebhookHelper;
use Gametech\Payment\Libraries\WellPay;
use Carbon\Carbon;
use Gametech\Auto\Jobs\UpdateBalanceWellPay;
use Gametech\Core\Repositories\CheckCaseRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankAccountRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WellPayController extends AppBaseController
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

        return view('topup.box.wellpay', compact('data', 'member'));
    }

    public function deposit(Request $request)
    {
        $api = new Wellpay;
        $request->validate([
            'amount' => 'required|numeric',
        ]);

        $member = auth()->guard('customer')->user();

        Log::channel('wellpay_deposit_create')->info('เริ่มสร้างรายการฝาก', [
            'debug' => 'start',
        ]);

        $bank_account = $this->bankAccountRepository->findOneWhere([
            'banks' => 305, 'bank_type' => 1, 'enable' => 'Y', 'status_auto' => 'Y',
        ]);

        if (!$bank_account) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.fail');

            return response()->json($return);
        }

        $amount = (float)$request->input('amount');
        $amount = number_format($amount, 2, '.', '');

        $min_deposit = config('wellpay.min_deposit', 100);
        if ($amount < $min_deposit) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.min_deposit', ['amount' => $min_deposit]);

            return response()->json($return);
        }


        $transactionId = 'WDEP-' . str_pad($member->code, 6, '0', STR_PAD_LEFT) . '-' . date('YmdHis');
        $clientId = config('wellpay.client_id');
        $merchantId = config('wellpay.merchant_no');
        $bankAccountNumber = $member->acc_no;

        $bankName = $api->Banks($member->bank_code);

        if ($bankName === false) {
            $return['success'] = false;
            $return['msg'] = __('app.topup.wrong_bank');

            return response()->json($return);
        }

        $name = $member->name;
        $callbackUrl = route('api.wellpay.deposit.callback');
        $type = 'QR';
        $timeout = 5;
        $timestamp = now()->timestamp;
        $signature = $api->JwT($timestamp);


        UpdateBalanceWellPay::dispatch()->onQueue('topup');

        $param = [
            'clientId' => trim($clientId),
            'amount' => (float)$amount,
            'merchantId' => trim($merchantId),
            'transactionId' => trim($transactionId),
            'bankAccountNumber' => trim($bankAccountNumber),
            'bankName' => trim($bankName),
            'name' => trim($name),
            'callbackUrl' => trim($callbackUrl),
            'type' => trim($type),
            'timeout' => trim($timeout),
            'signature' => trim($signature),
            'timestamp' => $timestamp,
        ];

        $url = config('wellpay.api_url') . '/api/v1/deposit/create';
        $response = $api->create($url, $param);
        if ($response['success'] === true) {
            $this->repository->create([
                'bank_code' => $bank_account->code,
                'txid' => $transactionId,
                'amount' => $amount,
                'payamount' => $response['data']['depositAmount'],
                'username' => $member->user_name,
                'name' => $member->name,
                'detail' => $response['data']['referenceId'],
                //                'url' => $response['data']['qrcode'],
                'qrcode' => $response['data']['qrcode'],
                'status' => $response['data']['status'],
                'expired_date' => Carbon::parse($response['data']['expireDate'])->setTimezone('Asia/Bangkok'),
                'user_create' => $member->name,
                'user_update' => $member->name,
            ]);

            $return['url'] = route('api.wellpay.index', ['id' => $response['data']['referenceId']]);
            $return['msg'] = __('app.topup.create');
            $return['code'] = $response['code'];
            $return['success'] = true;
            return response()->json($return);
        }

        $return['msg'] = $response['msg'];

        return response()->json($return);

    }

    public function deposit_callback(Request $request)
    {
        $datenow = now()->toDateTimeString();
        $message = $request->all();

        Log::channel('wellpay_deposit_callback')->info('Callback การฝาก', $message);


        $refId = $message['referenceId'];
        $transactionId = $message['transactionId'];
        $status = $message['status'];


        $apiKey = config('wellpay.api_key');
        $secretKey = config('wellpay.secret_key');


        UpdateBalanceWellPay::dispatch()->onQueue('topup');

//        $username = $message['extendParams']['username'] ?? '';

        if (WebhookHelper::verifyWebhook($message, $apiKey, $secretKey)) {

            $case = $this->repository->findOneWhere(['txid' => $transactionId]);
            if ($case) {

                $this->repository->update([
                    'status' => $status,
                ], $case->code);
            }


            if ($status === 'completed') {

                $amount = $case->amount ?? $message['amount'];
                $payAmount =  $amount;

                $mcode = explode('-', $transactionId);
                $code = $mcode[1] ?? '';
                $code = $code * 1;
                $member = $this->memberRepository->findOneWhere(['code' => $code]);
                $bank_account = $this->bankAccountRepository->findOneWhere([
                    'banks' => 305, 'bank_type' => 1, 'enable' => 'Y', 'status_auto' => 'Y',
                ]);

                $bank = $this->bankRepository->find($bank_account->banks);
                $detail = ' REF ID : ' . $refId . ' (' . $transactionId . ' )';
                $hash = md5($bank_account->code . $datenow . $amount . $detail);

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
                    'user_create' => 'รอระบบเติมอัตโนมัติ ทำรายการฝากเงินโดย wellpay QR',
                    'create_by' => 'SYSAUTO',
                ];

                $check = $this->bankPaymentRepository->findOneWhere(['txid' => $transactionId]);
                if (!$check) {
                    $this->bankPaymentRepository->create($data);
                }

            }

            return response()->json(['status' => 'success'], 200);

        } else {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

    }

    public function withdraw_callback(Request $request)
    {
        $config = core()->getConfigData();
        $datenow = now()->toDateTimeString();
        $message = $request->all();

        Log::channel('wellpay_withdraw_callback')->info('Callback การฝาก', $message);

        $refId = $message['referenceId'];
        $transactionId = $message['transactionId'];
//			$amount = $message['amount'];
//			$payAmount = $message['payAmount'] ?? $amount;
        $status = $message['status'];
//			$username = $message['extendParams']['username'] ?? '';


        UpdateBalanceWellPay::dispatch()->onQueue('topup');


        $case = $this->repository->findOneWhere(['txid' => $transactionId]);
        if ($case) {

            $this->repository->update([
                'status' => $status,
            ], $case->code);
        }


        if ($config->seamless == 'Y') {
            $data = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->findOneWhere(['txid' => $transactionId]);
        } else {
            $data = app('Gametech\Payment\Repositories\WithdrawRepository')->findOneWhere(['txid' => $transactionId]);
        }

        $amount = $data['amount'];

        if ($status === 'completed') {

            $data->remark_admin = '[ Ref No :' . $transactionId . ' ] โอนให้ลุกค้าแล้ว ';
            $data->status_withdraw = 'C';
            $data->save();
            broadcast(new RealTimeMessage('wellpay ' . $transactionId . ' โอนเงินให้ลูกค้าแล้ว ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' รายการแจ้งถอนที่ ' . $data->code));

        } elseif ($status === 'failed' || $status === 'rejected') {

            if ($config->seamless == 'Y') {
                $datanew = [
                    'refer_code' => $data->code,
                    'refer_table' => 'withdraws',
                    'remark' => 'คืนยอดจากการถอน ' . $transactionId,
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
                    'remark' => 'คืนยอดจากการแจ้งถอน ' . $transactionId,
                    'amount' => $amount,
                    'method' => 'D',
                    'member_code' => $data->member_code,
                    'emp_code' => 0,
                    'emp_name' => 'SYSTEM',
                ];
                $response = app('Gametech\Member\Repositories\MemberCreditLogRepository')->setWallet($datanew);

            }
            if ($response) {
                broadcast(new RealTimeMessage('wellpay Payment โอนเงินไม่สำเร็จ ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' Ref ID ' . $refId . ' ระบบคืนยอดให้ลูกค้าแล้ว'));
                $data->remark_admin = '[ Ref ID :' . $transactionId . ' ] โอนไม่สำเร็จ และ ระบบคืนยอดแล้ว';
            } else {
                broadcast(new RealTimeMessage('wellpay Payment โอนไม่สำเร็จ และระบบคืนยอดไม่ได้  ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' Ref ID ' . $refId));
                $data->remark_admin = '[ Ref ID :' . $transactionId . ' ] โอนไม่สำเร็จ โปรดคืนยอดให้ลูกค้าเอง ระบบคืนไม่ได้';
            }

            //            $data->remark_admin = '[Order No :'.$transactionId.'] ผิดพลาดไม่สามารถดำเนินการได้ - '.$data->remark_admin;
            $data->status_withdraw = 'R';
            $data->status = 2;
            $data->save();

        } else {
            broadcast(new RealTimeMessage('wellpay ' . $transactionId . ' สถานะ ' . ucfirst($status) . ' รายการถอน ID : ' . $data->member_user . ' จำนวนเงิน ' . $amount . ' รายการแจ้งถอนที่ ' . $data->code));
        }

        return response()->json(['code' => 0, 'msg' => 'success']);

    }

    public function expire($txid)
    {
        $repo = $this->repository;

        $case = $repo->findOneWhere(['detail' => $txid]);
        if ($case && $case->status !== 'completed') {
            $repo->update([
                'status' => 'expired',
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
