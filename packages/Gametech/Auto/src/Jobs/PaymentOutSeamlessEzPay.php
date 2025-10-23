<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\EzPay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentOutSeamlessEzPay implements ShouldQueue
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
        $api = new EzPay();
        $return['complete'] = false;
        $return['success'] = 'NORMAL';
        $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';

        $baseurl = 'https://' . config('app.admin_url') . '.' . (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));


        $id = $this->id;
        $balance_start = 0;
        $balance_stop = 0;

        $datenow = now()->toDateTimeString();

        $order = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->find($id);
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutOne($order->account_code);
        if (!$bank) {
            $return['complete'] = true;
            return $return;
        }
        if($order->amount < 100){
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = 'ยอดเงินถอนออโต้ ขั้นต่ำ 100 บาท';
            return $return;
        }
        if ($order->status_withdraw != 'W') {
            $return['success'] = 'NOTWAIT';
            $return['msg'] = 'รายการนี้ กำลังอยู่ระหว่างประมวลผล';
            return $return;
        }

        $subMerId = config('app.ezpay_subid');
        $uuid = 'WDR-'.$subMerId.'-'.date('is').str_pad($order->code, 4, "0", STR_PAD_LEFT);
        $transactionId = $api->check_uuid2($uuid, $order->code,$subMerId);

//        if (!$order->txid) {
//            $order->txid = $transactionId;
//        }
        $order->txid = $transactionId;
        $order->status_withdraw = 'A';
        $order->save();

        $transactionId = $order->txid;

        $member = app('Gametech\Member\Repositories\MemberRepository')->find($order->member_code);


        $amount = (float)($order['amount'] - $order['fee']);
        $amount = number_format($amount, 2, '.', '');
        $bank_trans = $api->Banks($member->bank_code);
        if ($bank_trans === '500') {
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = 'บัญชีธนาคารของสมาชิก ไม่รองรับการโอน ในขณะนี้';
            return $return;
        }

        $time = time();
        $param = [
            'refId' => trim($transactionId),
            'custBankAcct' => trim($member->acc_no),
            'custBankCode' => trim($bank_trans),
            'amount' => (float)$amount,
            'callbackUrl' => trim($baseurl.'/ezpay/payout_callback'),
            'merId' => trim(config('app.ezpay_merid')),
            'subMerId' => trim($subMerId)
        ];

        $url = config('app.ezpay_apiurl').'/withdraw';
        $chk = $api->create_payout($url, $param);

        if ($chk['success'] === true) {
            $order->status = 1;
            $order->status_withdraw = 'A';
            $order->remark_admin = $order->remark_admin . ' กำลัง ทำรายการถอนเงินออกจาก EzPay โอนเข้าบัญชี ' . $member->name . ' เลขที่บัญชี ' . $member->acc_no . ' ธนาคาร ' . $member->bank->shortcode . ' จำนวน ' . $amount;
            $order->save();
            $return['complete'] = true;
            $return['success'] = 'COMPLETE';
            $return['msg'] = $chk['message'];
        } else {
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = $chk['message'];
        }


        return $return;
    }
}
