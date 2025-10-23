<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\SulifuPay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentOutSeamlessSulifuPay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 5;

    public $retryAfter = 0;

    public $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        $api = new SulifuPay;
        $return['complete'] = false;
        $return['success'] = 'NORMAL';
        $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';

        $baseurl = 'https://'.config('app.admin_url').'.'.(is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));

        $id = $this->id;
        $balance_start = 0;
        $balance_stop = 0;

        $datenow = now()->toDateTimeString();

        $order = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->find($id);
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutOne($order->account_code);
        if (! $bank) {
            $return['complete'] = true;

            return $return;
        }
        //        if ($order->amount < 200) {
        //            $return['success'] = 'FAIL_AUTO';
        //            $return['msg'] = 'ยอดเงินถอนออโต้ ขั้นต่ำ 200 บาท';
        //
        //            return $return;
        //        }
        if ($order->status_withdraw != 'W') {
            $return['success'] = 'NOTWAIT';
            $return['msg'] = 'รายการนี้ กำลังอยู่ระหว่างประมวลผล';

            return $return;
        }

        $order_id = 'WRD-'.str_pad($order->code, 6, '0', STR_PAD_LEFT).'-'.date('YmdHis');

        //        $subMerId = config('app.ezpay_subid');
        //        $uuid = 'WDR-'.$subMerId.'-'.date('is').str_pad($order->code, 4, '0', STR_PAD_LEFT);
        //        $transactionId = $api->check_uuid2($uuid, $order->code, $subMerId);

        //        if (!$order->txid) {
        //            $order->txid = $transactionId;
        //        }
        $order->txid = $order_id;
        //        $order->status_withdraw = 'A';
        $order->save();

        $transactionId = $order->txid;

        $member = app('Gametech\Member\Repositories\MemberRepository')->find($order->member_code);

        $amount = (float) ($order['amount'] - $order['fee']);
        //        $amount = number_format($amount, 2, '.', '');
        //        $amount = number_format($amount, 0);

        $bank_trans = $api->Banks($member->bank_code);
        if ($bank_trans === false) {
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = 'บัญชีธนาคารของสมาชิก ไม่รองรับการโอน ในขณะนี้';

            return $return;
        }
        $merNo = config('payment.merchant_no');
        $cType = 'Payout'; // QR Code type
        $bankCode = $bank_trans;
        $notifyUrl = route('api.payment.withdraw.callback');
        $apikey = config('payment.api_key');
        $orderAmount = (float) $amount;
        $sign = md5($merNo.$order_id.$bankCode.$orderAmount.$apikey);
        $time = time();
        $param = [
            'merNo' => trim($merNo),
            'tradeNo' => trim($order_id),
            'cType' => trim($cType),
            'bankCode' => trim($bankCode),
            'orderAmount' => $orderAmount,
            'bankCardNo' => trim($member->acc_no),
            'accountName' => trim($member->name),
            'openProvince' => trim(1),
            'openCity' => trim(1),
            'notifyUrl' => trim($notifyUrl),
            'playerId' => trim($member->user_name),
            'playerEmail' => trim($member->user_name.'@gmail.com'),
            'sign' => trim($sign),

        ];

        $url = config('payment.api_url').'/payout/createOrder';
        $response = $api->create_withdraw($url, $param);

        if ($response['success'] === true) {
            $order->status = 1;
            $order->status_withdraw = 'A';
            $order->remark_admin = $order->remark_admin.' กำลัง ทำรายการถอนเงินออกจาก WildPay โอนเข้าบัญชี '.$member->name.' เลขที่บัญชี '.$member->acc_no.' ธนาคาร '.$member->bank->shortcode.' จำนวน '.$amount;
            $order->save();
            $return['complete'] = true;
            $return['success'] = 'COMPLETE';
            $return['msg'] = $response['msg'];
        } else {
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = $response['msg'];
        }

        return $return;
    }
}
