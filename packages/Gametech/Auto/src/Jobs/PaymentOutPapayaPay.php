<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\LuckyPay;
use App\Libraries\PapayaPay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentOutPapayaPay implements ShouldQueue
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
        $bbl = new PapayaPay();
        $return['complete'] = false;
        $return['success'] = 'NORMAL';
        $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';

        if (config('app.user_url') === '') {
            $baseurl = 'https://' . (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        } else {
            $baseurl = 'https://' . config('app.user_url') . '.' . (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        }


        $id = $this->id;
        $balance_start = 0;
        $balance_stop = 0;

        $datenow = now()->toDateTimeString();

        $order = app('Gametech\Payment\Repositories\WithdrawRepository')->find($id);
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutOne($order->account_code);
        if (!$bank) {
            $return['complete'] = true;
            return $return;
        }
//        if($order->emp_approve > 0){
//            $return['success'] = 'NOTWAIT';
//            $return['msg'] = 'รายการนี้ มีทีมงานกดดำเนินการแล้ว';
//            return $return;
//        }
        if ($order->status_withdraw != 'W') {
            $return['success'] = 'NOTWAIT';
            $return['msg'] = 'รายการนี้ กำลังอยู่ระหว่างประมวลผล';
            return $return;
        }
        $uuid = 'PAY' . str_pad($order->code, 7, "0", STR_PAD_LEFT) . 'X' . date('His');
//        $uuid = Str::uuid()->__toString();
        $transactionId = $bbl->check_uuid($uuid, $order->code);
//        if (!$order->txid) {
//            $order->txid = $transactionId;
//        }
        $order->txid = $transactionId;
        $order->status_withdraw = 'A';
        $order->save();

        $transactionId = $order->txid;

        $member = app('Gametech\Member\Repositories\MemberRepository')->find($order->member_code);


        $amount = ($order['amount'] - $order['fee']);
        $bank_trans = $bbl->Banks($member->bank_code);
        if ($bank_trans == '500') {
            $return['success'] = 'FAIL_AUTO';
            $return['msg'] = 'บัญชีธนาคารของสมาชิก ไม่รองรับการโอน ในขณะนี้';
            return $return;
        }

        $time = time();
        $param = [
            'currencyCode' => 'THB',
            'accountNumber' => $member->acc_no,
            'accountName' => $member->name,
            'bankCode' => $bank_trans,
            'amount' => $amount,
            'fundOutPaymentReference' => $transactionId
        ];

        $url = config('app.papayapay_url') . '/api/v2/create-fundout';
        $chk = $bbl->create_payout($url, $param);

        if ($chk['success'] === true) {
            $order->status = 1;
            $order->status_withdraw = 'C';
            $order->remark_admin = $order->remark_admin . ' ทำรายการถอนเงินออกจาก PapayaPay โอนเข้าบัญชี ' . $member->name . ' เลขที่บัญชี ' . $member->acc_no . ' ธนาคาร ' . $member->bank->shortcode . ' จำนวน ' . $amount;
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
