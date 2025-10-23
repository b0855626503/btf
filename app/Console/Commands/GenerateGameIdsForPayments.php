<?php

namespace App\Console\Commands;

use Gametech\Game\Models\GameUserProxy;
use Gametech\Member\Models\MemberProxy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GenerateGameIdsForPayments extends Command
{
    protected $signature = 'payment:generate-ids 
                            {--force : บังคับสร้างใหม่แม้มีไอดีแล้ว}';

    protected $description = 'เพิอ่ม ฝากให้ ไอดีเกมให้สมาชิกทุกคน (เช็กในตาราง games_user ก่อน)';

    public function handle()
    {
        $force = $this->option('force');
        $this->info('เริ่มสร้างฝากเงินให้ ไอดีเกม...');

        MemberProxy::chunk(500, function ($members) use ($force) {
            foreach ($members as $member) {


                $amount = 300;

                $account = 1;


                $bank_account = app('Gametech\Payment\Repositories\BankAccountRepository')->find($account);

                $bank = app('Gametech\Payment\Repositories\BankRepository')->find($bank_account->banks);

                $detail = 'เงินพิเศษ ให้สมาชิก '.$member->user_name.'เพิ่มรายการฝากเงินโดย System';

                $datenow = now()->toDateTimeString();
                $hash = md5($account . $datenow . $amount . $detail);

                $data = [
                    'bank' => strtolower($bank->shortcode . '_' . $bank_account->acc_no),
                    'detail' => $detail,
                    'account_code' => $account,
                    'autocheck' => 'W',
                    'bankstatus' => 1,
                    'bank_name' => $bank->shortcode,
                    'bank_time' => $datenow,
                    'channel' => 'MANUAL',
                    'value' => $amount,
                    'tx_hash' => $hash,
                    'status' => 0,
                    'ip_admin' => request()->ip(),
                    'member_topup' => $member->code,
                    'remark_admin' =>'เงินฝากขวัญถุง สมาชิก คนละ 300 บาท',
                    'emp_topup' => 0,
                    'user_create' => 'รอระบบเติมอัตโนมัติ ทำรายการฝากเงินโดย System',
                    'create_by' => 'SYSAUTO'
                ];

                $response =  app('Gametech\Payment\Repositories\BankPaymentRepository')->create($data);
                if ($response->code) {
                    $this->info("ฝากเงินให้: {$member->username}");
                } else {
                    $this->info("ฝากเงินให้ ไมา่ได้: {$member->username}");
                }

            }
        });

        $this->info('สร้างไอดีเกมเสร็จสิ้น!');
    }


}
