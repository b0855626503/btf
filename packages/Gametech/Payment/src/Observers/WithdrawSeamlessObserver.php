<?php


namespace Gametech\Payment\Observers;


use App\Events\SumNewWithdraw;
use App\Helpers\TelegramBot;
use App\Libraries\KbankOut;
use App\Libraries\ScbOut;
use Gametech\Auto\Jobs\PaymentOutSeamlessKbank;
use Gametech\Auto\Jobs\PaymentOutSeamlessScb;
use Gametech\Core\Models\Log;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\Payment\Models\BankAccount;
use Gametech\Payment\Models\WithdrawSeamless as EventData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class WithdrawSeamlessObserver
{
    use ActivityLogger;


    public function created(EventData $data)
    {

        $userId = 0;
        $userName = '';
        if (Auth::guard('customer')->check()) {
            $userId = Request::user('customer')->code;
            $userName = Request::user('customer')->user_name;
        }

        if ($userId > 0) {
            $log = new Log;
            $log->emp_code = $userId;
            $log->mode = 'ADD';
            $log->menu = 'withdraws';
            $log->record = $data->code;
            $log->item_before = json_encode($data->getOriginal());
            $log->item = json_encode($data->getChanges());
            $log->ip = Request::ip();
            $log->user_create = $userName;
            $log->save();
        }

        $withdraw = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
            ->active()->waiting()
            ->count();

        broadcast(new SumNewWithdraw($withdraw,'up'));
	    
	    if ($data->payment_last) {
		    $ref = 'ฝากล่่าสุด ' . $data->payment_last['date_create']->format('Y-m-d H:i:s') . ' - ' . $data->payment_last['bank'] . ' [ ' . $data->payment_last['value'] . ' ]';
	    } else {
		    $ref = 'ไม่พบข้อมูลการเติมก่อนหน้า';
	    }
	    $datetime = $data->date_create->format('Y-m-d H:i:s');
	    
	    
	    $message = <<<HTML
<b>แจ้งเตือนการทำรายการ</b>
———————
<b>ประเภท:</b> แจ้งถอนเงิน<br>
<b>ชื่อผู้ใช้:</b> <code>{$data->member_user}</code><br>
<b>จำนวนเงิน:</b> {$data->amount}<br>
<b>เวลา:</b> {$datetime} (GMT+7)<br>
<b>อ้างอิง:</b> {$ref}<br>
<b>รายละเอียดเพิ่มเติม:</b> ชื่อ {$data->member->name} เลขบัญชี {$data->member->acc_no}
HTML;
	    TelegramBot::Send('notify/send', $message, ['parse_mode' => 'HTML']);

//        $msg = $data->date_create->format('Y-m-d H:i:s').' มีรายการถอน จาก ID : '.$data->member_user.' '.$data->member->name.' จำนวนเงิน ['.$data->amount.']';
//        TelegramBot::Send('notify/send',$msg);

    }

    public function created_(EventData $data)
    {

        $ip = Request::ip();
        $datenow = now()->toDateTimeString();

        $bank = BankAccount::where('auto_transfer', 'Y')->where('status_auto', 'Y')->where('enable', 'Y')->where('bank_type', 2)->first();
        if (isset($bank)) {
            $bank_code = $bank->bank->code;
            if ($bank_code == 2) {
                $kbank = new KbankOut();
                $ubank = $kbank->Banks($data->bankm_code);
                if ($ubank != '500') {
                    if ($data->amount >= $bank->min_amount && $data->amount <= $bank->max_amount) {

                        $data->account_code = $bank->code;
                        $data->ip_admin = $ip;
                        $data->user_update = 'SYSTEM';
                        $data->date_approve = $datenow;
                        $data->save();

//                    $return = PaymentOutSeamlessKbank::dispatchAfterResponse($data->code);
                        $return = PaymentOutSeamlessKbank::dispatchNow($data->code);
//                dd($return);
                        switch ($return['success']) {
                            case 'NORMAL':
                                $data->status = 1;
                                $data->save();
                                break;

                            case 'NOMONEY':
                            case 'FAIL_AUTO':
                                $data->account_code = 0;
                                $data->status_withdraw = 'W';
                                $data->status = 0;
                                $data->emp_approve = 0;
                                $data->ip_admin = '';
                                $data->save();

                                break;

                            case 'COMPLETE':
                            case 'NOTWAIT':
                            case 'MONEY':
                                break;

                        }

                        if ($return['complete'] === true) {

                            $member = app('Gametech\Member\Repositories\MemberRepository')->find($data->member_code);

                            $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $data->member_code);

                            app('Gametech\Member\Repositories\MemberCreditLogRepository')->create([
                                'ip' => $ip,
                                'credit_type' => 'D',
                                'balance_before' => $member->balance,
                                'balance_after' => $member->balance,
                                'credit' => 0,
                                'total' => $data->amount,
                                'credit_bonus' => 0,
                                'credit_total' => 0,
                                'credit_before' => $member->balance,
                                'credit_after' => $member->balance,
                                'pro_code' => 0,
                                'bank_code' => $data->bankm_code,
                                'auto' => 'Y',
                                'enable' => 'Y',
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
                                'refer_code' => $data->code,
                                'refer_table' => 'withdraws',
                                'remark' => 'ระบบโอนเงินออโต้แล้ว รายการที่ : ' . $data->code . ' / ไอดี : ' . $member->user_name . ' / ยอด : ' . $data->amount . ' / บัญชี : ' . $member->acc_no,
                                'kind' => 'AUTO_WDS',
                                'amount' => $data->amount,
                                'amount_balance' => $game_user->amount_balance,
                                'withdraw_limit' => $game_user->withdraw_limit,
                                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                                'method' => 'D',
                                'member_code' => $data->member_code,
                                'user_name' => $member->user_name,
                                'emp_code' => 0,
                                'emp_name' => 'SYSTEM'
                            ]);

                        } else {

                            $member = app('Gametech\Member\Repositories\MemberRepository')->find($data->member_code);

                            $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $data->member_code);

                            app('Gametech\Member\Repositories\MemberCreditLogRepository')->create([
                                'ip' => $ip,
                                'credit_type' => 'D',
                                'balance_before' => $member->balance,
                                'balance_after' => $member->balance,
                                'credit' => 0,
                                'total' => $data->amount,
                                'credit_bonus' => 0,
                                'credit_total' => 0,
                                'credit_before' => $member->balance,
                                'credit_after' => $member->balance,
                                'pro_code' => 0,
                                'bank_code' => $data->bankm_code,
                                'auto' => 'Y',
                                'enable' => 'Y',
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
                                'refer_code' => $data->code,
                                'refer_table' => 'withdraws',
                                'remark' => 'ระบบไม่สามารถโอนเงินออโต้ได้ รายการที่ : ' . $data->code . ' / ไอดี ' . $member->user_name . ' ทีมงานโปรดดำเนินการเอง',
                                'kind' => 'AUTO_WDF',
                                'amount' => $data->amount,
                                'amount_balance' => $game_user->amount_balance,
                                'withdraw_limit' => $game_user->withdraw_limit,
                                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                                'method' => 'D',
                                'member_code' => $data->member_code,
                                'user_name' => $member->user_name,
                                'emp_code' => 0,
                                'emp_name' => 'SYSTEM'
                            ]);

                        }
                    }
                }
            } else if ($bank_code == 4) {
                $kbank = new ScbOut();
                $ubank = $kbank->Banks($data->bankm_code);
                if ($ubank != '500') {
                    if ($data->amount >= $bank->min_amount && $data->amount <= $bank->max_amount) {

                        $data->account_code = $bank->code;
                        $data->ip_admin = $ip;
                        $data->user_update = 'SYSTEM';
                        $data->date_approve = $datenow;
                        $data->save();

//                    $return = PaymentOutSeamlessKbank::dispatchAfterResponse($data->code);
                        $return = PaymentOutSeamlessScb::dispatchNow($data->code);
//                dd($return);
                        switch ($return['success']) {
                            case 'NORMAL':
                                $data->status = 1;
                                $data->save();
                                break;

                            case 'NOMONEY':
                            case 'FAIL_AUTO':
                                $data->account_code = 0;
                                $data->status_withdraw = 'W';
                                $data->status = 0;
                                $data->emp_approve = 0;
                                $data->ip_admin = '';
                                $data->save();

                                break;

                            case 'COMPLETE':
                            case 'NOTWAIT':
                            case 'MONEY':
                                break;

                        }

                        if ($return['complete'] === true) {

                            $member = app('Gametech\Member\Repositories\MemberRepository')->find($data->member_code);

                            $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $data->member_code);

                            app('Gametech\Member\Repositories\MemberCreditLogRepository')->create([
                                'ip' => $ip,
                                'credit_type' => 'D',
                                'balance_before' => $member->balance,
                                'balance_after' => $member->balance,
                                'credit' => 0,
                                'total' => $data->amount,
                                'credit_bonus' => 0,
                                'credit_total' => 0,
                                'credit_before' => $member->balance,
                                'credit_after' => $member->balance,
                                'pro_code' => 0,
                                'bank_code' => $data->bankm_code,
                                'auto' => 'Y',
                                'enable' => 'Y',
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
                                'refer_code' => $data->code,
                                'refer_table' => 'withdraws',
                                'remark' => 'ระบบโอนเงินออโต้แล้ว รายการที่ : ' . $data->code . ' / ไอดี : ' . $member->user_name . ' / ยอด : ' . $data->amount . ' / บัญชี : ' . $member->acc_no,
                                'kind' => 'AUTO_WDS',
                                'amount' => $data->amount,
                                'amount_balance' => $game_user->amount_balance,
                                'withdraw_limit' => $game_user->withdraw_limit,
                                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                                'method' => 'D',
                                'member_code' => $data->member_code,
                                'user_name' => $member->user_name,
                                'emp_code' => 0,
                                'emp_name' => 'SYSTEM'
                            ]);

                        } else {

                            $member = app('Gametech\Member\Repositories\MemberRepository')->find($data->member_code);

                            $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $data->member_code);

                            app('Gametech\Member\Repositories\MemberCreditLogRepository')->create([
                                'ip' => $ip,
                                'credit_type' => 'D',
                                'balance_before' => $member->balance,
                                'balance_after' => $member->balance,
                                'credit' => 0,
                                'total' => $data->amount,
                                'credit_bonus' => 0,
                                'credit_total' => 0,
                                'credit_before' => $member->balance,
                                'credit_after' => $member->balance,
                                'pro_code' => 0,
                                'bank_code' => $data->bankm_code,
                                'auto' => 'Y',
                                'enable' => 'Y',
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
                                'refer_code' => $data->code,
                                'refer_table' => 'withdraws',
                                'remark' => 'ระบบไม่สามารถโอนเงินออโต้ได้ รายการที่ : ' . $data->code . ' / ไอดี ' . $member->user_name . ' ทีมงานโปรดดำเนินการเอง',
                                'kind' => 'AUTO_WDF',
                                'amount' => $data->amount,
                                'amount_balance' => $game_user->amount_balance,
                                'withdraw_limit' => $game_user->withdraw_limit,
                                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                                'method' => 'D',
                                'member_code' => $data->member_code,
                                'user_name' => $member->user_name,
                                'emp_code' => 0,
                                'emp_name' => 'SYSTEM'
                            ]);

                        }
                    }
                }
            }

        }


    }

    public function updated(EventData $data)
    {
        $userId = 0;
        $userName = '';
        if (Auth::guard('admin')->check()) {
            $userId = Request::user('admin')->code;
            $userName = Request::user('admin')->user_name;
        }

        if ($userId > 0) {
            $log = new Log;
            $log->emp_code = $userId;
            $log->mode = 'EDIT';
            $log->menu = 'withdraws';
            $log->record = $data->code;
            $log->item_before = json_encode($data->getOriginal());
            $log->item = json_encode($data->getChanges());
            $log->ip = Request::ip();
            $log->user_create = $userName;
            $log->save();
        }
//        ActivityLogger::activitie('แก้ไขข้อมูล รายการที่ ' . $data->code, json_encode($logs));

        $withdraw = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
            ->active()->waiting()
            ->count();

        broadcast(new SumNewWithdraw($withdraw,'down'));

    }


    public function deleted(EventData $data)
    {
        $userId = 0;
        $userName = '';
        if (Auth::guard('admin')->check()) {
            $userId = Request::user('admin')->code;
            $userName = Request::user('admin')->user_name;
        }

        if ($userId > 0) {
            $log = new Log;
            $log->emp_code = $userId;
            $log->mode = 'DEL';
            $log->menu = 'withdraws';
            $log->record = $data->code;
            $log->item_before = json_encode($data->getOriginal());
            $log->item = json_encode($data->getChanges());
            $log->ip = Request::ip();
            $log->user_create = $userName;
            $log->save();
        }

        $withdraw = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
            ->active()->waiting()
            ->count();

        broadcast(new SumNewWithdraw($withdraw,'down'));
//        ActivityLogger::activitie('ลบข้อมูล รายการที่ ' . $data->code, json_encode($logs));

    }
}
