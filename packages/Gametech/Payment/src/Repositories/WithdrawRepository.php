<?php

namespace Gametech\Payment\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Models\Withdraw;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class WithdrawRepository extends Repository
{
    private $memberRepository;

    private $memberLogRepository;

    private $memberCreditLogRepository;

    private $gameUserRepository;

    /**
     * WithdrawRepository constructor.
     * @param MemberLogRepository $memberLogRepo
     * @param MemberRepository $memberRepo
     * @param MemberCreditLogRepository $memberCreditLogRepo
     * @param GameUserRepository $gameUserRepo
     * @param App $app
     */
    public function __construct
    (
        MemberLogRepository       $memberLogRepo,
        MemberRepository          $memberRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        GameUserRepository        $gameUserRepo,
        App                       $app
    )
    {
        $this->memberLogRepository = $memberLogRepo;

        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->gameUserRepository = $gameUserRepo;

        parent::__construct($app);
    }

    public function findForUpdate(int $id)
    {
        return Withdraw::where('code',$id)->lockForUpdate()->first();
    }


    /**
     * @param $id
     * @param $amount
     * @return bool
     */

    public function withdraw($id, $amount): bool
    {

        $datenow = now();
        $timenow = $datenow->toTimeString();
        $today = $datenow->toDateString();
        $ip = request()->ip();
        $baseamount = $amount;
        $member = $this->memberRepository->find($id);
        if (!$member) {
            return false;
        }


        if ($member->balance < $amount) {
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ยอดแจ้งถอน มากกว่า ยอดที่มี');
            return false;
        }

        $oldcredit = $member->balance;
        $aftercredit = ($oldcredit - $baseamount);


        ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'เริ่มต้นทำรายการแจ้งถอน');

        DB::beginTransaction();

        try {

            $data = [
                'member_code' => $id,
                'amount' => $amount,
                'oldcredit' => $oldcredit,
                'aftercredit' => $aftercredit,
                'ip' => $ip
            ];


//            $this->memberLogRepository->create([
//                'member_code' => $member->code,
//                'mode' => 'WITHDRAW',
//                'menu' => 'withdraw',
//                'record' => $member->code,
//                'remark' => 'ถอนเงินจาก กระเป๋า Wallet',
//                'item_before' => serialize($member),
//                'item' => serialize($data),
//                'ip' => $ip,
//                'user_create' => $member->name
//            ]);

            $chk = $this->findOneWhere(['member_code' => $member->code, 'amount' => $amount, 'status' => 0, 'date_record' => $today, 'timedept' => $timenow]);
            if ($chk) {
                DB::rollBack();
                return false;
            }

            $member->balance -= $amount;
            $member->ip = $ip;
            $member->save();

            $bill = $this->create([
                'member_code' => $member->code,
                'member_user' => $member->user_name,
                'bankm_code' => $member->bank_code,
                'amount' => floor($amount),
                'balance' => $baseamount,
                'oldcredit' => $oldcredit,
                'aftercredit' => $aftercredit,
                'status' => 0,
                'date_record' => $today,
                'bankout' => '',
                'remark' => '',
                'timedept' => $timenow,
                'ip' => $ip,
                'user_create' => $member->name,
                'user_update' => $member->name
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $baseamount,
                'bonus' => 0,
                'total' => $baseamount,
                'balance_before' => $oldcredit,
                'balance_after' => $aftercredit,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member->code,
                'user_name' => $member->user_name,
                'game_code' => 0,
                'bank_code' => $member->bank_code,
                'gameuser_code' => 0,
                'auto' => 'N',
                'refer_code' => $bill->code,
                'refer_table' => 'withdraws',
                'remark' => "ทำรายการถอนเงิน อ้างอิงบิล ID :" . $bill->code . ' ยอดก่อนถอน ' . $member->balance . ' แจ้งถอน ' . $amount . ' คงเหลือ ' . $aftercredit,
                'kind' => 'WITHDRAW',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

//            $member->balance -= $amount;
//            $member->ip = $ip;
//            $member->save();

            DB::commit();

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'พบปัญหาในการทำรายการ');
            DB::rollBack();
//            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ดำเนินการ Rollback แล้ว');

            report($e);
            return false;
        }


        ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ทำรายการแจ้งถอนสำเร็จแล้ว');
        return true;

    }

    public function withdrawSingle($id, $amount)
    {
        $response['success'] = false;

        $datenow = now();
        $timenow = $datenow->toTimeString();
        $today = $datenow->toDateString();
        $ip = request()->ip();
        $baseamount = $amount;
        $member = $this->memberRepository->find($id);

        ActivityLoggerUser::activity('ทำรายการแจ้งถอนเกมเครดิต จาก : ' . $member->user_name, 'เตรียมการทำรายการแจ้งถอน จำนวน ' . $baseamount . ' ยอดเครดิตที่มี ' . $member->balance);



        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        $pro_code = $game_user->pro_code;

        if ($member->balance < $game_user->amount_balance) {
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ยอดเครดิต ยังไม่ผ่าน ยอดเทิน');
            $response['msg'] = 'พบข้อผิดพลาด ยอดเครดิต ยังไม่ผ่านเงื่อนไข ที่ต้องการ';
            return $response;
        }

        if ($game_user->amount_balance > 0) {


            if ($amount < $game_user->amount_balance) {
                ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ยอดแจ้งถอน ต้องมากกว่า ยอดเทิน');
                $response['msg'] = 'พบข้อผิดพลาด ยอดที่แจ้งถอนมา ไม่ถูกต้อง';
                return $response;
            }

            if ($amount != $member->balance) {
                ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'แจ้งถอนไม่หมด');
                $response['msg'] = 'พบข้อผิดพลาด ยอดที่แจ้งถอนมา ไม่ถูกต้อง';
                return $response;
            }

            if ($game_user->withdraw_limit_amount > 0) {
                if ($amount > $game_user->withdraw_limit_amount) {
                    $amount = $game_user->withdraw_limit_amount;
                }
            }

//            if($gameuser->withdraw_limit > 0){
//                    $amount = $gameuser->withdraw_limit;
//            }

        } else {

            if ($member->balance < $baseamount) {
                ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ยอดแจ้งถอน มากกว่า ยอดที่มี');
                $response['msg'] = 'พบข้อผิดพลาด ยอดที่แจ้งถอนมา มากกว่ายอดที่มีอยู่';
                return $response;
            }

        }

        $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $baseamount, false);
        if ($response['success'] === true) {
            ActivityLoggerUser::activity('ถอนเงินจากเกม ' . $game_name . ' ของ ID : ' . $user_name, 'จำนวนเงิน ' . $baseamount . ' ระบบทำการถอนเงินออกจากเกมแล้ว');
        } else {
            ActivityLoggerUser::activity('ถอนเงินจากเกม ' . $game_name . ' ของ ID : ' . $user_name, 'จำนวนเงิน ' . $baseamount . ' ไม่สามารถถอนเงินออกจากเกมได้');
            return $response;
        }

//        dd($response);

        $oldcredit = $member->balance;
        $aftercredit = ($oldcredit - $baseamount);

        ActivityLoggerUser::activity('ทำรายการแจ้งถอนเกมเครดิต จาก : ' . $member->user_name, 'เริ่มต้นทำรายการแจ้งถอน');

        DB::beginTransaction();

        try {

            $member->balance = $response['after'];
            $member->ip = $ip;
            $member->save();

            if ($game_user->amount_balance > 0) {
                $game_user->bill_code = 0;
                $game_user->pro_code = 0;
                $game_user->bonus = 0;
                $game_user->amount = 0;
                $game_user->turnpro = 0;
                $game_user->amount_balance = 0;
                $game_user->withdraw_limit = 0;
                $game_user->withdraw_limit_rate = 0;
                $game_user->withdraw_limit_amount = 0;
//                $gameuser->save();
            }
            $game_user->balance = $response['after'];
            $game_user->save();

            $bill = $this->create([
                'member_code' => $member->code,
                'member_user' => $member->user_name,
                'bankm_code' => $member->bank_code,
                'amount' => floor($amount),
                'balance' => $baseamount,
                'oldcredit' => $response['before'],
                'aftercredit' => $response['after'],
                'status' => 0,
                'date_record' => $today,
                'bankout' => '',
                'remark' => '',
                'timedept' => $timenow,
                'ip' => $ip,
                'user_create' => $member->name,
                'user_update' => $member->name
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $baseamount,
                'bonus' => 0,
                'total' => $baseamount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => $baseamount,
                'credit_bonus' => 0,
                'credit_total' => $baseamount,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member->code,
                'user_name' => $member->user_name,
                'game_code' => $game_code,
                'bank_code' => $member->bank_code,
                'gameuser_code' => $user_code,
                'auto' => 'N',
                'refer_code' => $bill->code,
                'refer_table' => 'withdraws',
                'remark' => "ทำรายการถอนเงิน อ้างอิงบิล ID :" . $bill->code . ' ยอดก่อนถอน ' . $oldcredit . ' แจ้งถอน ' . $amount . ' คงเหลือ ' . $aftercredit,
                'kind' => 'WITHDRAW',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);


            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('ทำรายการแจ้งถอนเกมเครดิต จาก : ' . $member->user_name, 'พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLoggerUser::activity('ทำรายการแจ้งถอนเกมเครดิต จาก : ' . $member->user_name, 'ดำเนินการ Rollback แล้ว');

            $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $baseamount);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('ถอนเงินจากเกม ' . $game_name . ' ของ ID : ' . $user_name, 'จำนวนเงิน ' . $baseamount . ' ฝากเงินกลับเข้าเกม เรียบร้อย');
            } else {
                ActivityLoggerUser::activity('ถอนเงินจากเกม ' . $game_name . ' ของ ID : ' . $user_name, 'จำนวนเงิน ' . $baseamount . ' ไม่สามารถ ฝากเงินเข้าเกมได้');
            }
            report($e);
            return $response;
        }


        ActivityLoggerUser::activity('ทำรายการแจ้งถอนเกมเครดิต จาก : ' . $member->user_name, 'ทำรายการแจ้งถอนสำเร็จแล้ว');
        return $response;

    }



    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return \Gametech\Payment\Models\Withdraw::class;
    }
}
