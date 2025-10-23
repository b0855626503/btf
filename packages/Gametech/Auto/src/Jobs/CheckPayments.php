<?php

namespace Gametech\Auto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class CheckPayments implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 30;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 0;

    public $retryAfter = 0;

    protected $bank;

    protected $item;

    public function __construct($bank, $item)
    {
        $this->bank = $bank;
        $this->item = $item;

    }

    public function tags()
    {
        return ['render', 'check:'.$this->item->code];
    }

    public function uniqueId()
    {
        return $this->item->code;
    }

    public function handle(): bool
    {
        $bank = $this->bank;
        $item = $this->item;

        $this->bankPaymentRepository = app('Gametech\Payment\Repositories\BankPaymentRepository');
        $this->memberRepository = app('Gametech\Member\Repositories\MemberRepository');

        $payment = $this->bankPaymentRepository->find($item->code);


        $members = $this->memberRepository->loadAccount($bank, $payment);

        $cnt = $members->count();
        if ($cnt == 0) {
            $payment->autocheck = 'Y';
            $payment->remark_admin = 'ไม่พบหมายเลขบัญชี';
            $payment->topup_by = 'System Auto';
            $payment->save();
            return false;
        } elseif ($cnt > 1) {
            $users = collect($members)->pluck('user_name')->implode(' , ');
            $payment->autocheck = 'Y';
            $payment->remark_admin = 'พบหมายเลขบัญชี ' . $cnt . ' บัญชี : ' . $users;
            $payment->topup_by = 'System Auto';
            $payment->save();
            return false;

        }

        $member = $members->first();

        $payment->member_topup = $member->code;
        $payment->autocheck = 'W';
        $payment->remark_admin = 'รอระบบเติมอัตโนมัติ';
        $payment->topup_by = 'System Auto';
        $payment->save();
        return true;

    }
}
