<?php

namespace Gametech\Payment\Repositories;


use App\Notifications\RealTimeNotification;
use Gametech\Core\Eloquent\Repository;
use Gametech\Core\Repositories\AllLogRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberDiamondLogRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberPromotionLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Throwable;


class BankPaymentRepository extends Repository
{

    use ActivityLogger;

    use ActivityLoggerUser;

    private $memberRepository;

    private $memberCreditLogRepository;

    private $memberPromotionLogRepository;

    private $allLogRepository;

    private $paymentPromotionRepository;

    private $promotionRepository;

    private $bankAccountRepository;

    private $memberPointLogRepository;

    private $memberDiamondLogRepository;

    private $gameUserRepository;

    public function __construct
    (
        MemberRepository             $memberRepo,
        MemberCreditLogRepository    $memberCreditLogRepo,
        AllLogRepository             $allLogRepo,
        PaymentPromotionRepository   $paymentPromotionRepo,
        PromotionRepository          $promotionRepo,
        BankAccountRepository        $bankAccountRepo,
        MemberPointLogRepository     $memberPointLogRepo,
        MemberDiamondLogRepository   $memberDiamondLogRepo,
        GameUserRepository           $gameUserRepo,
        MemberPromotionLogRepository $memberPromotionLogRepo,
        App                          $app
    )
    {
        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->allLogRepository = $allLogRepo;

        $this->paymentPromotionRepository = $paymentPromotionRepo;

        $this->promotionRepository = $promotionRepo;

        $this->bankAccountRepository = $bankAccountRepo;

        $this->memberPointLogRepository = $memberPointLogRepo;

        $this->memberDiamondLogRepository = $memberDiamondLogRepo;

        $this->gameUserRepository = $gameUserRepo;

        $this->memberPromotionLogRepository = $memberPromotionLogRepo;

        parent::__construct($app);
    }

    public function loadDeposit($id, $date_start = null, $date_stop = null)
    {
        return $this->with('promotion')->orderBy('date_create', 'desc')->findWhere(['member_topup' => $id, 'enable' => 'Y', ['value', '>', 0]])
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?", [$date_start, $date_stop]);
            });


    }

    public function checkPayment($limit = 5, $bank = 'tw')
    {

        return $this->when($bank, function ($query, $bank) {
            if ($bank === 'tw') {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');
            } else {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');

            }
        })->orderBy('code', 'asc')
            ->waiting()->active()->income()->where('tx_hash', '!=', '')
            ->where('bankstatus', 1)
            ->where('autocheck', 'N')
//            ->whereIn('create_by', ['SYSAUTO','BAYAUTO1','BAYAUTO2','BAYAUTO3','BAYAUTO4','BAYAUTO5'])
            ->whereNotIn('create_by', ['SCBAUTO1', 'SCBAUTO2', 'SCBAUTO3', 'SCBAUTO4', 'SCBAUTO5', 'TOPUPSCBAUTO1', 'TOPUPSCBAUTO2', 'TOPUPSCBAUTO3', 'TOPUPSCBAUTO4', 'TOPUPSCBAUTO5', 'KBANKAUTO1', 'KBANKAUTO2', 'KBANKAUTO3', 'KBANKAUTO4', 'KBANKAUTO5'])
            ->with('bank_account')
            ->whereHas('bank_account', function ($model) use ($bank) {
                $model->active()->topup()->in()->with('bank')->whereHas('bank', function ($model) use ($bank) {
                    $model->where('shortcode', strtoupper($bank));
                });
            })
            ->limit($limit)->get();


    }

    public function loadPayment($limit = 5)
    {

        return $this->scopeQuery(function ($query) use ($limit) {
            return $query->orderBy('code', 'asc')
                ->waiting()->active()->income()
                ->where('bankstatus', 1)
                ->where('autocheck', 'W')
                ->where('member_topup', '<>', 0)
//                ->whereIn('create_by', ['SYSAUTO','BAYAUTO1','BAYAUTO2','BAYAUTO3','BAYAUTO4','BAYAUTO5'])
                ->whereNotIn('create_by', ['SCBAUTO1', 'SCBAUTO2', 'SCBAUTO3', 'SCBAUTO4', 'SCBAUTO5', 'TOPUPSCBAUTO1', 'TOPUPSCBAUTO2', 'TOPUPSCBAUTO3', 'TOPUPSCBAUTO4', 'TOPUPSCBAUTO5', 'KBANKAUTO1', 'KBANKAUTO2', 'KBANKAUTO3', 'KBANKAUTO4', 'KBANKAUTO5'])
                ->limit($limit);
        })->with(['bank_account' => function ($model) {
            return $model->active()->topup()->in()->with('bank');
        }])->all();

    }


    public function refillPayment($data): bool
    {
        $ip = request()->ip();

        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();

        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }


        $member_code = $member->code;
        $amount = $data['value'];
        $total = $amount;
        $bonus = 0;
        $pro_code = 0;
        $point = 0;
        $diamond = 0;
        $count_deposit = 1;
        $status_pro = $member->status_pro;
        $bank_code = $bank_acc->bank->code;
        $game_code = 0;
        $user_name = '';
        $game_balance = 0;

        $credit_before = $member['balance'];
        $credit_after = ($credit_before + $total);


        try {

            $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
            if ($chk) {
                ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว',$member_code);
                return false;
            }

            $alllog = $this->allLogRepository->create([
                "before_credit" => $credit_before,
                "after_credit" => $credit_after,
                'status_log' => 0,
                "pro_id" => $pro_code,
                "pro_amount" => $bonus,
                "bonus" => $bonus,
                'game_code' => $game_code,
                'type_record' => 0,
                'gamebalance' => $game_balance,
                "member_code" => $member_code,
                "member_user" => $member['user_name'],
                "amount" => $amount,
                "bank_payment_id" => $data['code'],
                "ip" => $ip,
                "username" => $user_name,
                "remark" => '',
                "user_create" => 'System Auto',
                "user_update" => 'System Auto'
            ]);

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้', $member_code);
            report($e);
            return false;
        }

        ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name, $member_code);


        DB::beginTransaction();

        try {

            $chknew = $this->memberCreditLogRepository->findOneWhere(['member_code' => $member_code, 'refer_code' => $data['code'], 'refer_table' => 'bank_payment', 'kind' => 'TOPUP']);
            if ($chknew) {
                ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'ตรวจพบ log ซ้ำซ้อน ยกเลิก ของ User: ' . $member->user_name, $member_code);
                DB::rollBack();
                return false;
            }

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => $credit_before,
                'balance_after' => $credit_after,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'pro_code' => $pro_code,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => "Auto Topup From Deposit ID : " . $data['code'],
                'kind' => 'TOPUP',
                'user_create' => "System Auto",
                'user_update' => "System Auto"
            ]);


            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();


            if ($config->point_open == 'Y') {
                if ($amount >= $config->points && $config->points > 0) {
                    $point = floor($amount / $config->points);
                    $this->memberPointLogRepository->create([
                        'point_type' => 'D',
                        'point_amount' => $point,
                        'point_before' => $member->point_deposit,
                        'point_balance' => ($member->point_deposit + $point),
                        'member_code' => $member_code,
                        'remark' => 'เพิ่ม Point จากการเติมเงิน',
                        'emp_code' => $data['emp_topup'],
                        'ip' => $ip,
                        'user_create' => "System Auto",
                        'user_update' => "System Auto",
                    ]);

                }
            }
            if ($config->diamond_open == 'Y') {

                if ($config->diamond_per_bill == 'N') {

                    if ($amount >= $config->diamonds && $config->diamonds > 0) {
                        $diamond = floor($amount / $config->diamonds);

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->diamonds . ' ได้รับ 1 เม็ด สรุปได้รับ ' . $diamond,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);
                    }

                } else {

                    if ($amount >= $config->diamonds_topup && $config->diamonds_topup > 0 && $config->diamonds_amount > 0) {
                        $diamond = $config->diamonds_amount;

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->diamonds_topup . ' ได้รับ ' . $diamond . ' เม็ด',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);

                    }

                }


            }


            $payment->user_id = $user_name;
            $payment->status = 1;
            $payment->before_credit = $credit_before;
            $payment->after_credit = $credit_after;
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = $payment->remark_admin . ' (เติมแล้ว)';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();


            $member->status_pro = $status_pro;
            $member->balance += $total;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->count_deposit += $count_deposit;
            $member->save();


            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction', $member_code);
            DB::rollBack();
            ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'Rollback Transaction', $member_code);
            report($e);
            return false;

        }

        ActivityLoggerUser::activity('Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name, $member_code);


        return true;

    }

    public function refillPaymentSingle($data): bool
    {
        $ip = request()->ip();

        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();

        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }


        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;
        $amount = $data['value'];


        $promotion = $this->promotionRepository->CalculatePro($member, $amount, $datenow);
        $bonus = $promotion['bonus'];
        $pro_code = $promotion['pro_code'];
        $pro_name = $promotion['pro_name'];
        $total = $promotion['total'];
        $status_pro = $promotion['status_pro'];
        $turnpro = $promotion['turnpro'];
        $withdraw_limit = $promotion['withdraw_limit'];
        $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
        $point = 0;
        $diamond = 0;
        $count_deposit = 1;

        $bank_code = $bank_acc->bank->code;


        $credit_before = $game_balance;
        $credit_after = ($credit_before + $total);

        $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
        if ($chk) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว', $member->code);
            return false;
        }


        try {

            $alllog = $this->allLogRepository->create([
                "before_credit" => $credit_before,
                "after_credit" => $credit_after,
                'status_log' => 0,
                "pro_id" => $pro_code,
                "pro_amount" => $bonus,
                "bonus" => $bonus,
                'game_code' => $game_code,
                'type_record' => 0,
                'gamebalance' => $game_balance,
                "member_code" => $member_code,
                "member_user" => $member['user_name'],
                "amount" => $amount,
                "bank_payment_id" => $data['code'],
                "ip" => $ip,
                "username" => $user_name,
                "remark" => '',
                "user_create" => 'System Auto',
                "user_update" => 'System Auto'
            ]);

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้');
            report($e);
            return false;
        }

        $money_text = 'User ' . $member->user_name . ' Game ID : ' . $user_name . ' จำนวนเงิน ' . $amount . ' โบนัส ' . $bonus . ' จากโปร ' . $pro_name . ' รวมเป็น ' . $total;

        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name . ' Game ID : ' . $user_name);
        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], $money_text);

        $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $total, false);


        if ($response['success'] === true) {
            ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบทำการฝากเงินเข้าเกมแล้ว ยอด ก่อน ' . $response['before'] . ' ยอดหลัง ' . $response['after']);
        } else {
            ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ไม่สามารถฝากเงินเข้าเกมได้');
            return false;
        }


        DB::beginTransaction();

        try {

            $chknew = $this->memberCreditLogRepository->findOneWhere(['member_code' => $member_code, 'refer_code' => $data['code'], 'refer_table' => 'bank_payment', 'kind' => 'TOPUP']);
            if ($chknew) {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' หยุดการทำงาน เนื่องจาก Log ซ้ำ');
                return false;
            }

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => 0,
                'balance_after' => 0,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'pro_code' => $pro_code,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => "Auto Topup From Deposit ID : " . $data['code'],
                'kind' => 'TOPUP',
                'user_create' => "System Auto",
                'user_update' => "System Auto"
            ]);


            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();


            if ($config->point_open == 'Y') {
                if ($amount >= $config->points && $config->points > 0) {
                    $point = floor($amount / $config->points);
                    $this->memberPointLogRepository->create([
                        'point_type' => 'D',
                        'point_amount' => $point,
                        'point_before' => $member->point_deposit,
                        'point_balance' => ($member->point_deposit + $point),
                        'member_code' => $member_code,
                        'remark' => 'เพิ่ม Point จากการเติมเงิน',
                        'emp_code' => $data['emp_topup'],
                        'ip' => $ip,
                        'user_create' => "System Auto",
                        'user_update' => "System Auto",
                    ]);

                }
            }

            if ($config->diamond_open == 'Y') {

                if ($config->diamond_per_bill == 'N') {

                    if ($amount >= $config->diamonds && $config->diamonds > 0) {
                        $diamond = floor($amount / $config->diamonds);

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->diamonds . ' ได้รับ 1 เม็ด สรุปได้รับ ' . $diamond,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);
                    }

                } else {

                    if ($amount >= $config->diamonds_topup && $config->diamonds_topup > 0 && $config->diamonds_amount > 0) {
                        $diamond = $config->diamonds_amount;

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->diamonds_topup . ' ได้รับ ' . $diamond . ' เม็ด',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);

                    }

                }

            }


            $payment->user_id = $user_name;
            $payment->status = 1;
            $payment->before_credit = $response['before'];
            $payment->after_credit = $response['after'];
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = $payment->remark_admin . ' (เติมแล้ว)';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();



            if ($pro_code > 0) {

                $bill = app('Gametech\Payment\Repositories\BillRepository')->create([
                    'enable' => 'Y',
                    'ref_id' => '',
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'pro_code' => $pro_code,
                    'transfer_type' => 1,
                    'amount' => $amount,
                    'balance_before' => $response['before'],
                    'balance_after' => $response['after'],
                    'credit' => $amount,
                    'credit_bonus' => $bonus,
                    'credit_balance' => $total,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name']
                ]);

                $this->memberPromotionLogRepository->create([
                    'date_start' => now()->toDateString(),
                    'bill_code' => $bill->code,
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'game_name' => $game_name,
                    'gameuser_code' => $user_code,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'turnpro' => $turnpro,
                    'balance' => ($response['before'] - $amount),
                    'amount' => $amount,
                    'bonus' => $bonus,
                    'amount_balance' => ($total * $turnpro),
                    'total_amount_balance' => (($response['before'] - $amount) + ($total * $turnpro)),
                    'withdraw_limit' => $withdraw_limit,
                    'withdraw_limit_rate' => 0,
                    'complete' => 'N',
                    'enable' => 'Y',
                    'user_create' => $member['name'],
                    'user_update' => $member['name']
                ]);

            }


            $member->status_pro = $status_pro;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->balance = $response['after'];
            $member->count_deposit += $count_deposit;
            $member->save();

            $game_user->balance = $response['after'];
            $game_user->pro_code = $pro_code;
            $game_user->bill_code = $bill->code;
            $game_user->turnpro = $turnpro;
            $game_user->amount += $amount;
            $game_user->bonus += $bonus;
            $game_user->amount_balance += (($credit_before - $amount) + ($total * $turnpro));
            $game_user->withdraw_limit = $withdraw_limit;
            $game_user->withdraw_limit_rate = $withdraw_limit_rate;
            $game_user->withdraw_limit_amount += (($amount + $bonus) * $withdraw_limit_rate);
            $game_user->save();

            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction');
            DB::rollBack();
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'Rollback Transaction');

            $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $total);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบทำการถอนเงินออกจากเกมแล้ว');

            } else {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบไม่สามารถถอนเงินออกจากเกมได้');
            }
            report($e);
            return false;

        }

        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name);


        return true;

    }

    public function refillPaymentSeamless($data): bool
    {
        $ip = request()->ip();

        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();

        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }


        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        if(!$game_user){
            $res = $this->gameUserRepository->addGameUser($game->code, $member->code, ['username' => $member->user_name, 'product_id' => 'PGSOFT', 'user_create' => $member->user_name]);
            if ($res['success'] !== true) {
                return false;
            }
            $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);

        }
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $member->balance;
        $member_code = $member->code;
        $amount = $data['value'];


//        $promotion = $this->promotionRepository->CalculatePro($member, $amount, $datenow);
        $bonus = 0;
        $pro_code = 0;
        $pro_name = '';
        $total = $amount;
        $status_pro = 0;
        $point = 0;
        $diamond = 0;
        $count_deposit = 1;

        $bank_code = $bank_acc->bank->code;


        $credit_before = $game_balance;
        $credit_after = ($credit_before + $total);

        $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
        if ($chk) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว', $member_code);
            return false;
        }


        try {

            $alllog = $this->allLogRepository->create([
                "before_credit" => $credit_before,
                "after_credit" => $credit_after,
                'status_log' => 0,
                "pro_id" => $pro_code,
                "pro_amount" => $bonus,
                "bonus" => $bonus,
                'game_code' => $game_code,
                'type_record' => 0,
                'gamebalance' => $game_balance,
                "member_code" => $member_code,
                "member_user" => $member['user_name'],
                "amount" => $amount,
                "bank_payment_id" => $data['code'],
                "ip" => $ip,
                "username" => $user_name,
                "remark" => '',
                "user_create" => 'System Auto',
                "user_update" => 'System Auto'
            ]);

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้', $member_code);
            report($e);
            return false;
        }

        $money_text = 'User ' . $member->user_name . ' Game ID : ' . $user_name . ' จำนวนเงิน ' . $amount . ' โบนัส ' . $bonus . ' จากโปร ' . $pro_name . ' รวมเป็น ' . $total;

        ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name . ' Game ID : ' . $user_name . ' ' . $money_text, $member_code);


        DB::beginTransaction();

        try {

            $remark = '';

            if ($game_user->amount_balance > 0) {
                if ($member->balance <= $config->pro_reset) {
                    $game_user->bill_code = 0;
                    $game_user->pro_code = 0;
                    $game_user->bonus = 0;
                    $game_user->amount = 0;
                    $game_user->turnpro = 0;
                    $game_user->amount_balance = 0;
                    $game_user->withdraw_limit = 0;
                    $game_user->withdraw_limit_rate = 0;
                    $game_user->withdraw_limit_amount = 0;
                    $game_user->save();

                    $this->memberPromotionLogRepository->where('member_code', $member_code)->where('complete', 'N')->update([
                        'complete' => 'Y'
                    ]);


                } else {


                    $game_user->amount += $total;
                    $game_user->amount_balance += ($total * $game_user->turnpro);
                    $game_user->withdraw_limit_amount += ($total * $game_user->withdraw_limit_rate);


                    $game_user->save();

                    $remark = ' !! ผิดเงื่อนไขโปร เพิ่มยอดเทิน ' . $game_user->turnpro . ' เท่า !! ';


                }
            }

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => 0,
                'balance_after' => 0,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $member->balance,
                'credit_after' => ($member->balance + $total),
                'member_code' => $member_code,
                'pro_code' => $pro_code,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => ($payment->emp_topup == 0 ? $remark." (อิงรายการฝากที่ : " . $data['code'].') ' : $payment->remark_admin),
                'kind' => 'TOPUP',
                'amount_balance' => $game_user->amount_balance,
                'withdraw_limit' => $game_user->withdraw_limit,
                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                'user_create' => "System Auto",
                'user_update' => "System Auto"
            ]);


            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();


            if ($config->point_open == 'Y') {
                if ($amount >= $config->points && $config->points > 0) {
                    $point = floor($amount / $config->points);
                    $this->memberPointLogRepository->create([
                        'point_type' => 'D',
                        'point_amount' => $point,
                        'point_before' => $member->point_deposit,
                        'point_balance' => ($member->point_deposit + $point),
                        'member_code' => $member_code,
                        'remark' => 'เพิ่ม Point จากการเติมเงิน',
                        'emp_code' => $data['emp_topup'],
                        'ip' => $ip,
                        'user_create' => "System Auto",
                        'user_update' => "System Auto",
                    ]);

                }
            }

            if ($config->diamond_open == 'Y') {

                if ($config->diamond_per_bill == 'N') {

                    if ($amount >= $config->diamonds && $config->diamonds > 0) {
                        $diamond = floor($amount / $config->diamonds);

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->diamonds . ' ได้รับ 1 เม็ด สรุปได้รับ ' . $diamond,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);
                    }

                } else {

                    if ($amount >= $config->diamonds_topup && $config->diamonds_topup > 0 && $config->diamonds_amount > 0) {
                        $diamond = $config->diamonds_amount;

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->diamonds_topup . ' ได้รับ ' . $diamond . ' เม็ด',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => "System Auto",
                            'user_update' => "System Auto",
                        ]);

                    }

                }

            }


            $payment->user_id = $user_name;
            $payment->status = 1;
            $payment->before_credit = $member->balance;
            $payment->after_credit = ($member->balance + $total);
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = $payment->remark_admin . ' (เติมแล้ว)';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();


//            $member->status_pro = $status_pro;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->balance += $total;
            $member->count_deposit += $count_deposit;
            $member->save();


//            $game_user->balance += $total;
//            $game_user->save();

            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction', $member_code);
            DB::rollBack();
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'Rollback Transaction', $member_code);

            report($e);
            return false;

        }

        ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name, $member_code);
        Notification::send($member, new RealTimeNotification(Lang::get('app.topup.complete').$total));


        return true;

    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\BankPayment';
    }
}
