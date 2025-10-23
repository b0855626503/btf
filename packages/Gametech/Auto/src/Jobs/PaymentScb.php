<?php

namespace Gametech\Auto\Jobs;

use App\Libraries\Scb;
use App\Libraries\ScbOut;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;


class PaymentScb implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $failOnTimeout = true;
    public $uniqueFor = 60;

    public $timeout = 40;

    public $tries = 0;

    public $maxExceptions = 3;

    public $retryAfter = 0;

    protected $id;


    public function __construct($id)
    {
        $this->id = $id;
    }

    public function tags()
    {
        return ['render', 'scb:' . $this->id];
    }

    public function uniqueId()
    {
        return $this->id;
    }

//    public function middleware()
//    {
//        return [(new WithoutOverlapping($this->id))->expireAfter(30)];
//    }

    public function handle()
    {
        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $update = true;

        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('scb', $mobile_number);
        if (!$bank) {
            return 0;
        }

        if ($bank->local == 'Y') {
            return $this->ScbLocal();
        } else {
            return $this->ScbApi();
        }

    }

    public function ScbLocal()
    {
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $update = true;


        $datenow = now()->toDateTimeString();

        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOne('scb', $mobile_number);
        if (!$bank) {
            return 0;
        }

//        $path = storage_path('logs/scb/debug_' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
//        file_put_contents($path, print_r('local', true), FILE_APPEND);


        $bank_number = $bank['acc_no'];
        $bank_username = $bank['user_name'];
        $bank_password = $bank['user_pass'];

        $em = new Scb($bank_username, $bank_password, $bank_number);

        $rawbalance = $em->getBalance();
        $balance = $em->extract_int($rawbalance);

        $bank->balance = $balance;

        $bank->checktime = $datenow;
        $bank->save();

//        $balance = $em->extract_int(($rawbalance == false ));

        $collect = $em->getTransection();
        $collect = json_decode($collect, true);

        if (count($collect) > 0) {
            $DATE = '';
            $TIME = '';
            $outputs = [];
            foreach ($collect as $item) {
                $Account = $item['Account'];
                $type = $item['type'];

                if ($type == 'in') {
                    if (!strstr($Account, 'SCB')) {
                        $DEPOSIT_CLIENTID = substr($Account, strpos($Account, 'X') + 1, strlen($Account) - strpos($Account, 'X'));

                    } else {

                        $DEPOSIT_CLIENTID = substr($Account, strpos($Account, 'x') + 1, 4);
                    }


                    $DATE = str_replace('/', '-', substr($item['date'], 0, 10));
                    $TIME = substr($item['date'], -8);
                    $DEPOSIT_DATE = date('Y-m-d', strtotime($DATE)) . ' ' . $TIME;
                    $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$item["amount"], 2));


                    $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
                    $txhash = md5($txhashPlaintext);

                    $diff = core()->DateDiff($DEPOSIT_DATE);
                    if ($diff > 1) continue;

                    $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
                    $newpayment->account_code = $bank->code;
                    $newpayment->bank = 'scb_' . $bank->acc_no;
                    $newpayment->bankstatus = 1;
                    $newpayment->bankname = 'SCB';
                    $newpayment->bank_time = $DEPOSIT_DATE;
                    $newpayment->time = $DEPOSIT_DATE;
                    $newpayment->atranferer = $DEPOSIT_CLIENTID;
                    $newpayment->channel = $item['way'];
                    $newpayment->value = $DEPOSIT_AMOUNT;
                    $newpayment->tx_hash = $txhash;
                    $newpayment->detail = $item['Account'];
                    $newpayment->create_by = 'SYSAUTO';
                    $newpayment->ip_topup = '';
                    $newpayment->save();


                } elseif ($type == 'out') {

                    $DEPOSIT_CLIENTID = '';


                    $DATE = str_replace('/', '-', substr($item['date'], 0, 10));
                    $TIME = substr($item['date'], -8);
                    $DEPOSIT_DATE = date('Y-m-d', strtotime($DATE)) . ' ' . $TIME;
                    $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$item["amount"], 2));


                    $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
                    $txhash = md5($txhashPlaintext);

//                    $diff = core()->DateDiff($DEPOSIT_DATE);
//                    if($diff > 1 || $diff < 0) continue;

                    $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
                    $newpayment->account_code = $bank->code;
                    $newpayment->bank = 'scb_' . $bank->acc_no;
                    $newpayment->bankstatus = 2;
                    $newpayment->bankname = 'SCB';
                    $newpayment->bank_time = $DEPOSIT_DATE;
                    $newpayment->time = $DEPOSIT_DATE;
                    $newpayment->atranferer = $DEPOSIT_CLIENTID;
                    $newpayment->channel = $item['way'];
                    $newpayment->value = $DEPOSIT_AMOUNT;
                    $newpayment->tx_hash = $txhash;
                    $newpayment->detail = $item['Account'];
                    $newpayment->create_by = 'SYSAUTO';
                    $newpayment->ip_topup = '';
                    $newpayment->save();


                }
            }
        }

        return 0;
    }

    public function ScbApi()
    {
        $remark = '';
        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $path = storage_path('logs/scb/getinfo' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($mobile_number, true));
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('scb', $mobile_number);
        if (!$bank) {
            return 0;
        }

        $api = new ScbOut();

        $accname = str_replace("-", "", $bank->acc_no);

        $response = $api->BankCurl($mobile_number,'getbalance');

        if ($response['status'] === true && $response['code'] == 1000) {
            $balance = $response['data']['availableBalance'];
            $remark = 'เชคยอดเงิน : '.$response['msg'];
            $bank->balance = str_replace(',', '', number_format((float)$balance, 2));

        } else {
            $remark = 'เชคยอดเงิน : '.$response['msg'];
        }

        $response = $api->BankCurl($mobile_number,'transection');
        $path = storage_path('logs/scb/gettransaction_' . $accname . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($response, true));

        if ($response['status'] === true && $response['code'] == 1000) {
            $lists = $response['data'];
            if (count($lists) > 0) {

                foreach ($lists as $list) {

                    if ($list['type'] === 'ฝากเงิน') {

                        $DEPOSIT_CLIENTID = $list['details']['number'];

                        $DEPOSIT_DATE = $list['date_time'];

                        $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$list["value"], 2));


                        $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
                        $txhash = md5($txhashPlaintext);

                        $diff = core()->DateDiff($DEPOSIT_DATE);
                        if ($diff > 1 || $diff < 0) continue;

                        $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
                        $newpayment->account_code = $bank->code;
                        $newpayment->bank = 'scb_' . $bank->acc_no;
                        $newpayment->bankstatus = 1;
                        $newpayment->bankname = 'SCB';
                        $newpayment->bank_time = $DEPOSIT_DATE;
                        $newpayment->time = $DEPOSIT_DATE;
                        $newpayment->atranferer = $DEPOSIT_CLIENTID;
                        $newpayment->channel = 'API';
                        $newpayment->value = $DEPOSIT_AMOUNT;
                        $newpayment->tx_hash = $txhash;
                        $newpayment->detail = $list['full_detail'];
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->ip_topup = '';
                        $newpayment->save();

                    } else {

                        $DEPOSIT_CLIENTID = $list['details']['number'];

                        $DEPOSIT_DATE = $list['date_time'];

                        $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$list["value"], 2));
                        $DEPOSIT_AMOUNT = abs($DEPOSIT_AMOUNT);

                        $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
                        $txhash = md5($txhashPlaintext);

                        $diff = core()->DateDiff($DEPOSIT_DATE);
                        if ($diff > 1 || $diff < 0) continue;

                        $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
                        $newpayment->account_code = $bank->code;
                        $newpayment->bank = 'scb_' . $bank->acc_no;
                        $newpayment->bankstatus = 2;
                        $newpayment->bankname = 'SCB';
                        $newpayment->bank_time = $DEPOSIT_DATE;
                        $newpayment->time = $DEPOSIT_DATE;
                        $newpayment->atranferer = '';
                        $newpayment->channel = 'API';
                        $newpayment->value = -$DEPOSIT_AMOUNT;
                        $newpayment->tx_hash = $txhash;
                        $newpayment->detail = $list['full_detail'];
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->ip_topup = '';
                        $newpayment->save();

                    }

                }
            }

            $bank->api_refresh = $remark.' / เชครายการ : '.$response['msg'];
            $bank->checktime = $datenow;
            $bank->save();
            return 0;
        } else {
            $bank->api_refresh = $remark.' / เชครายการ : '.$response['msg'];
            $bank->checktime = $datenow;
            $bank->save();
            return 0;
        }

//        if ($response->successful()) {
//
//            $response = $response->body();
//            $response = $this->removeBOM($response);
//            $response = json_decode($response, true);
//
//            $path = storage_path('logs/scb/getbalance_' . $accname . '_' . now()->format('Y_m_d') . '.log');
//            file_put_contents($path, print_r($response, true));
//
//            if ($response['status'] === true && $response['code'] == 1000) {
//                $balance = $response['data']['availableBalance'];
//                $remark = 'เชคยอดเงิน : '.$response['msg'];
//                $bank->balance = str_replace(',', '', number_format((float)$balance, 2));
////                $bank->checktime = $datenow;
////                $bank->save();
//            } else {
//                $remark = 'เชคยอดเงิน : '.$response['msg'];
////                $bank->checktime = $datenow;
////                $bank->save();
////                return 0;
//            }
//
//
//            $url = 'https://scb.z7z.work/' . $mobile_number . '/transection';
//
//            $response = rescue(function () use ($url) {
//                return Http::timeout(30)->withHeaders([
//                    'access-key' => '53cb498c-8516-420f-bd65-90754e19bfbf'
//                ])->post($url);
//
//            }, function ($e) {
//                return $e->response;
//            });
//
//            if ($response->failed()) {
//                $bank->api_refresh = 'เชื่อมต่อ Api ไม่ได้';
//                $bank->checktime = $datenow;
//                $bank->save();
//                return 0;
//            }
//
//            if ($response->successful()) {
////                $response = $response->json();
//
//                $response = $response->body();
//                $response = $this->removeBOM($response);
//                $response = json_decode($response, true);
//
//                $path = storage_path('logs/scb/gettransaction_' . $accname . '_' . now()->format('Y_m_d') . '.log');
//                file_put_contents($path, print_r($response, true));
//
//                if ($response['status'] === true && $response['code'] == 1000) {
//                    $lists = $response['data'];
////                    sort($lists);
//
//                    if (count($lists) > 0) {
//
//                        foreach ($lists as $list) {
//
//                            if ($list['type'] === 'ฝากเงิน') {
//
//                                $DEPOSIT_CLIENTID = $list['details']['number'];
//
//                                $DEPOSIT_DATE = $list['date_time'];
//
//                                $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$list["value"], 2));
//
//
//                                $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
//                                $txhash = md5($txhashPlaintext);
//
//                                $diff = core()->DateDiff($DEPOSIT_DATE);
//                                if ($diff > 1 || $diff < 0) continue;
//
//                                $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
//                                $newpayment->account_code = $bank->code;
//                                $newpayment->bank = 'scb_' . $bank->acc_no;
//                                $newpayment->bankstatus = 1;
//                                $newpayment->bankname = 'SCB';
//                                $newpayment->bank_time = $DEPOSIT_DATE;
//                                $newpayment->time = $DEPOSIT_DATE;
//                                $newpayment->atranferer = $DEPOSIT_CLIENTID;
//                                $newpayment->channel = 'API';
//                                $newpayment->value = $DEPOSIT_AMOUNT;
//                                $newpayment->tx_hash = $txhash;
//                                $newpayment->detail = $list['full_detail'];
//                                $newpayment->create_by = 'SYSAUTO';
//                                $newpayment->ip_topup = '';
//                                $newpayment->save();
//
//                            } else {
//
//                                $DEPOSIT_CLIENTID = $list['details']['number'];
//
//                                $DEPOSIT_DATE = $list['date_time'];
//
//                                $DEPOSIT_AMOUNT = str_replace(',', '', number_format((float)$list["value"], 2));
//                                $DEPOSIT_AMOUNT = abs($DEPOSIT_AMOUNT);
//
//                                $txhashPlaintext = $DEPOSIT_DATE . $DEPOSIT_CLIENTID . $DEPOSIT_AMOUNT;
//                                $txhash = md5($txhashPlaintext);
//
//                                $diff = core()->DateDiff($DEPOSIT_DATE);
//                                if ($diff > 1 || $diff < 0) continue;
//
//                                $newpayment = BankPayment::firstOrNew(['tx_hash' => $txhash, 'account_code' => $bank->code]);
//                                $newpayment->account_code = $bank->code;
//                                $newpayment->bank = 'scb_' . $bank->acc_no;
//                                $newpayment->bankstatus = 2;
//                                $newpayment->bankname = 'SCB';
//                                $newpayment->bank_time = $DEPOSIT_DATE;
//                                $newpayment->time = $DEPOSIT_DATE;
//                                $newpayment->atranferer = '';
//                                $newpayment->channel = 'API';
//                                $newpayment->value = -$DEPOSIT_AMOUNT;
//                                $newpayment->tx_hash = $txhash;
//                                $newpayment->detail = $list['full_detail'];
//                                $newpayment->create_by = 'SYSAUTO';
//                                $newpayment->ip_topup = '';
//                                $newpayment->save();
//
//                            }
//
//                        }
//                    }
//
//                    $bank->api_refresh = $remark.' / เชครายการ : '.$response['msg'];
//                    $bank->checktime = $datenow;
//                    $bank->save();
//                    return 0;
//                } else {
//                    $bank->api_refresh = $remark.' / เชครายการ : '.$response['msg'];
//                    $bank->checktime = $datenow;
//                    $bank->save();
//                    return 0;
//                }
//            }
//        }
    }

    public function removeBOM($data)
    {
        if (0 === strpos(bin2hex($data), 'efbbbf')) {
            return substr($data, 3);
        }
        return $data;
    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
