<?php

namespace Gametech\Auto\Jobs;


use App\Libraries\Kbank;
use App\Libraries\KbankBiz;
use App\Libraries\KbankOut;
use App\Libraries\simple_html_dom;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;


class PaymentKbank implements ShouldQueue, ShouldBeUnique
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
        return ['render', 'kbank:' . $this->id];
    }

    public function uniqueId()
    {
        return $this->id;
    }


    public function handle()
    {

        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $update = true;

        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('kbank', $mobile_number);
        if (!$bank) {
            return true;
        }

        if ($bank->local == 'Y') {
            return $this->KbankLocal();
        } else {
            return $this->KbankApi();
        }

    }

    public function KbankLocal()
    {
        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOne('kbank', $mobile_number);
        if (!$bank) {
            return 1;
        }

        $balance = 0;
        $USERNAME = $bank->user_name; //"give855";
        $PASSWORD = $bank->user_pass; //"Zxcv@3622";

        $ACCOUNT_NAME = substr($bank['acc_no'], 0, 3) . "-" . substr($bank['acc_no'], 3, 1) . "-" . substr($bank['acc_no'], 4, 5) . "-" . substr($bank['acc_no'], 9, 1); //"719-220819-2"
        $accname = str_replace("-", "", $bank['acc_no']);

        $em = new KbankBiz();
        $em->setLogin($USERNAME, $PASSWORD);
        $em->setAccountNumber($accname);

        if ($em->login()) {

            $balance = $em->getBalance();
            if ($balance >= 0) {
                $bank->balance = $balance;
            }
            $bank->checktime = $datenow;
            $bank->save();

            $path = storage_path('logs/kbank/getbalance_' . $accname . '_' . now()->format('Y_m_d') . '.log');
            file_put_contents($path, print_r($balance, true));

            $collect = $em->getTransaction();

            $path = storage_path('logs/kbank/gettransaction_' . $accname . '_' . now()->format('Y_m_d') . '.log');
            file_put_contents($path, print_r($collect, true));

            if (count($collect) > 0) {

                foreach ($collect as $list) {

                    if (core()->DateDiff($list['date']) > 1) continue;

                    $list['value'] = str_replace(",", "", $list['in']);
                    $list['tx_hash'] = md5($accname . $list['date'] . $list['value']);
                    if ($list['value'] == "") {
                        continue;
                    }

                    $newpayment = BankPayment::firstOrNew(['tx_hash' => $list['tx_hash'], 'account_code' => $bank->code]);
                    $newpayment->account_code = $bank->code;
                    $newpayment->bank = 'kbank_' . $accname;
                    $newpayment->bankstatus = 1;
                    $newpayment->bankname = 'KBANK';
                    $newpayment->bank_time = $list['date'];
                    $newpayment->report_id = rtrim($list['report_id'], 'A');
                    $newpayment->atranferer = $list['fromaccno'];
                    $newpayment->channel = $list['channel'];
                    $newpayment->value = $list['value'];
                    $newpayment->tx_hash = $list['tx_hash'];
                    $newpayment->detail = $list['info'];
                    $newpayment->title = $list['title'];
                    $newpayment->time = $list['date'];
                    $newpayment->create_by = 'SYSAUTO';
                    $newpayment->ip_topup = '';
                    $newpayment->save();

                }

            }
            return 0;
        }

    }

    public function KbankApi_()
    {
        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $path = storage_path('logs/kbank/getinfo' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($mobile_number, true));
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('kbank', $mobile_number);
        if (!$bank) {
            return 1;
        }


        $accname = str_replace("-", "", $bank->acc_no);

        $path = storage_path('logs/kbank/getbalance_' . $accname . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($accname, true));

        $url = 'https://api-kbank.me2me.biz/kbiz/' . $mobile_number . '/getbalance';

        $response = rescue(function () use ($url) {
            return Http::timeout(15)->withHeaders([
                'access-key' => 'b499fe72-a9fb-4a6a-817d-c096c39a6896'
            ])->post($url);

        }, function ($e) {
            return $e;
        });

        if ($response->failed()) {
            return 1;
        }

        if ($response->successful()) {

            $response = $response->json();

            $path = storage_path('logs/kbank/getbalance_' . $accname . '_' . now()->format('Y_m_d') . '.log');
            file_put_contents($path, print_r($response, true));

            $balance = $response['data']['balance'];

            $bank->balance = str_replace(',', '', number_format((float)$balance, 2));
            $bank->checktime = $datenow;
            $bank->save();


            $url = 'https://api-kbank.me2me.biz/kbiz/' . $mobile_number . '/transection';

            $response = rescue(function () use ($url) {
                return Http::timeout(15)->withHeaders([
                    'access-key' => 'b499fe72-a9fb-4a6a-817d-c096c39a6896'
                ])->post($url);

            }, function ($e) {
                return $e;
            });

            if ($response->failed()) {
                return 1;
            }

            if ($response->successful()) {
                $response = $response->json();

                $path = storage_path('logs/kbank/gettransaction_' . $accname . '_' . now()->format('Y_m_d') . '.log');
                file_put_contents($path, print_r($response, true));

                $lists = $response['data']['data'];


                if (count($lists) > 0) {

                    foreach ($lists as $list) {

                        if (core()->DateDiff($list['index']['transDate']) > 1) continue;
                        if ($list['index']['debitCreditIndicator'] !== 'CR') continue;

                        $list['date'] = $list['index']['transDate'];

                        $amount = floatval(preg_replace('/[^0-9\.\+\-]/', '', $list['index']['depositAmount']));
                        $list['value'] = str_replace(",", "", $amount);

                        if (is_null($list['index']['toAccountNumber']) || empty($list['index']['toAccountNumber']) || $list['index']['toAccountNumber'] == '') {
                            $detail = $list['detail']['data'];
                            $list["report_id"] = $detail['bankNameEn'];
                            $list['toAccountNumber'] = $detail['toAccountNo'];
                            $list["channel"] = $detail['bankNameEn'];
                            $titles = explode(' ', $detail['toAccountNameTh']);
                        } else {
                            $list["report_id"] = '';
                            $list["channel"] = $list['index']['channelTh'];
                            $list['toAccountNumber'] = $list['index']['toAccountNumber'];
                            $titles = explode(' ', $list['index']['fromAccountNameTh']);
                        }

                        $title = isset($titles[1]) ? $titles[1] : '';
                        $list['title'] = $title;
                        $list["fromaccno"] = str_replace(array("x", "-"), array("", ""), $list['toAccountNumber']);
                        if ($list["report_id"] != '') {
                            $list["info"] = $list['index']['transNameTh'] . ' ' . $detail['toAccountNameTh'] . ' / ' . $list["fromaccno"];
                        } else {
                            $list["info"] = $list['index']['transNameTh'] . ' ' . $list['index']['fromAccountNameTh'] . ' / X' . $list["fromaccno"];
                        }

                        $list['tx_hash'] = md5($accname . $list['date'] . $list['value']);


                        $newpayment = BankPayment::firstOrNew(['tx_hash' => $list['tx_hash'], 'account_code' => $bank->code]);
                        $newpayment->account_code = $bank->code;
                        $newpayment->bank = 'kbank_' . $accname;
                        $newpayment->bankstatus = 1;
                        $newpayment->bankname = 'KBANK';
                        $newpayment->bank_time = $list['date'];
                        $newpayment->report_id = rtrim($list['report_id'], 'A');
                        $newpayment->atranferer = $list['fromaccno'];
                        $newpayment->channel = $list['channel'];
                        $newpayment->value = $list['value'];
                        $newpayment->tx_hash = $list['tx_hash'];
                        $newpayment->detail = $list['info'];
                        $newpayment->title = $list['title'];
                        $newpayment->time = $list['date'];
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->ip_topup = '';
                        $newpayment->save();

                    }
                }
            }
            return 0;
        }

    }

    public function KbankApi()
    {
        $datenow = now()->toDateTimeString();
        $header = [];
        $response = [];
        $mobile_number = $this->id;
//        $path = storage_path('logs/kbank/getinfo' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
//        file_put_contents($path, print_r($mobile_number, true));
        $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('kbank', $mobile_number);
        if (!$bank) {
            return 1;
        }


        $accname = str_replace("-", "", $bank->acc_no);

        $path = storage_path('logs/kbank/getbalance_' . $accname . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($accname, true));

        $kbank = new KbankOut();

        $response = $kbank->BankCurl($bank->acc_no, 'transaction', 'POST');
        if ($response['status'] === true) {
            $lists = $response['data']['activityList'];
            if (count($lists) > 0) {
                foreach ($lists as $list) {
                    if ($list['transactionType'] == 'CR') {

                        $list['tx_hash'] = md5($accname . $list['transactionUxDate'] . $list['amount']);
                        $list['date'] = date("Y-m-d H:i:s", ($list['transactionUxDate']));

                        $newpayment = BankPayment::firstOrNew(['tx_hash' => $list['tx_hash'], 'account_code' => $bank->code]);
                        $newpayment->account_code = $bank->code;
                        $newpayment->bank = 'kbank_' . $accname;
                        $newpayment->bankstatus = 1;
                        $newpayment->bankname = 'KBANK';
                        $newpayment->bank_time = $list['date'];
                        $newpayment->report_id = $list['transactionUxDate'];
                        $newpayment->atranferer = $list['fromAccountNo'];
                        $newpayment->channel = $list['channel'];
                        $newpayment->value = $list['amount'];
                        $newpayment->tx_hash = $list['tx_hash'];
                        $newpayment->detail = $list['transactionDescription'].' '.$list['fromAccountNo'].' '.$list['fromAccountName'];
                        $newpayment->title = '';
                        $newpayment->time = $list['date'];
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->ip_topup = '';
                        $newpayment->save();

                    }else{

                        $list['tx_hash'] = md5($accname . $list['transactionUxDate'] . $list['amount']);
                        $list['date'] = date("Y-m-d H:i:s", ($list['transactionUxDate']));

                        $newpayment = BankPayment::firstOrNew(['tx_hash' => $list['tx_hash'], 'account_code' => $bank->code]);
                        $newpayment->account_code = $bank->code;
                        $newpayment->bank = 'kbank_' . $accname;
                        $newpayment->bankstatus = 2;
                        $newpayment->bankname = 'KBANK';
                        $newpayment->bank_time = $list['date'];
                        $newpayment->report_id = $list['transactionUxDate'];
                        $newpayment->atranferer = $list['toAccountNo'];
                        $newpayment->channel = $list['channel'];
                        $newpayment->value = -$list['amount'];
                        $newpayment->tx_hash = $list['tx_hash'];
                        $newpayment->detail = $list['transactionDescription'].' '.$list['toAccountNo'].' '.$list['toAccountName'];
                        $newpayment->title = '';
                        $newpayment->time = $list['date'];
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->ip_topup = '';
                        $newpayment->save();

                    }
                }
            }
        }
    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
