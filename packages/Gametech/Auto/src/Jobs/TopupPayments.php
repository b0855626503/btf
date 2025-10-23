<?php

namespace Gametech\Auto\Jobs;


use Gametech\Core\Models\AllLog;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;


class TopupPayments implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//    public $deleteWhenMissingModels = true;

    public $uniqueFor = 10;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 3;

    protected $bankpayment;

    protected $alllog;

    protected $item;

    protected $config;


    public function __construct($item)
    {
        $this->item = $item;
        $this->config = core()->getConfigData();

    }

    public function tags()
    {
        return ['render', 'topup:'.$this->item];
    }

    public function uniqueId()
    {
        return $this->item;
    }

    public function handle()
    {

        $config = $this->config;

        $payment = BankPayment::find($this->item);


        if ($payment->status == 0 && $payment->autocheck == 'W') {

            $logs = Alllog::where('bank_payment_id', $payment->code)->first();

            if ($logs) {
                $payment->autocheck = 'Y';
                $payment->status = 1;
                $payment->saveQuietly();
            } else {

                if ($config->seamless == 'Y') {
                    app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStartSeamless($payment->value, $payment->member_topup, $payment->code);
                    app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPaymentSeamless(collect($payment)->toArray());

                } else {
                    if ($config->multigame_open == 'Y') {
                        app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStart($payment->value, $payment->member_topup, $payment->code);
                        app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPayment(collect($payment)->toArray());
                    } else {
                        app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStartSingle($payment->value, $payment->member_topup, $payment->code);
                        app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPaymentSingle(collect($payment)->toArray());
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
