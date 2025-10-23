<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\Ktb;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;


class PaymentKtb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 1;

    public $maxExceptions = 5;

    public $retryAfter = 130;

    protected $id;


    public function __construct($id)
    {
        $this->id = $id;
    }


    public function handle()
    {
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $update = true;


        $datenow = now()->toDateTimeString();

        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOne('ktb', $mobile_number);


        $bank_number = $bank['acc_no'];
        $bank_username = $bank['user_name'];
        $bank_password = $bank['user_pass'];
        $accountTokenNo = $bank['acctoken'];
        $userTokenId = $bank['usertoken'];

        $ktb = new Ktb();
        $gettran = $ktb->gettransaction($accountTokenNo, $userTokenId);
        $resobj = json_decode($gettran, true);
        $balance = $resobj['balance'];
        $collect = $resobj['data'];

        if ($balance >= 0) {
            $bank->balance = $balance;
        }

        $bank->checktime = $datenow;
        $bank->save();

        if (!is_null($collect)) {

            foreach ($collect as $item) {

                $txhash = MD5($item['transDate'].$item['transCmt'].$item['transAmt']);

                $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
                $newpayment->account_code = $bank->code;
                $newpayment->bank = 'ktb_' . $bank->acc_no;
                $newpayment->bankstatus = 1;
                $newpayment->bankname = 'KTB';
                $newpayment->bank_time = $item['transDate'];
                $newpayment->atranferer = $item['transCmt'];
                $newpayment->value = $item['transAmt'];
                $newpayment->tx_hash = $txhash;
                $newpayment->detail = $item['transRefId'];
                $newpayment->create_by = 'SYSAUTO';
                $newpayment->ip_topup = '';
                $newpayment->save();


            }
        }

    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
