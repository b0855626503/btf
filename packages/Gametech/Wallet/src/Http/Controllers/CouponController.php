<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Core\Models\CouponListProxy;
use Gametech\Core\Repositories\CouponListRepository;
use Gametech\Core\Repositories\CouponRepository;
use Gametech\Member\Repositories\MemberCreditFreeLogRepository;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BonusRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;


class CouponController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $repository;

    protected $memberRepository;
    protected $bonusRepository;

    protected $bankPaymentRepository;
    protected $couponListRepository;

    protected $memberCreditLogRepository;
    protected $memberCreditFreeLogRepository;


    public function __construct
    (
        CouponRepository              $repository,
        CouponListRepository          $couponListRepository,
        BonusRepository               $bonusRepository,
        BankPaymentRepository         $bankPaymentRepository,
        MemberRepository              $memberRepository,
        MemberCreditLogRepository     $memberCreditLogRepository,
        MemberCreditFreeLogRepository $memberCreditFreeLogRepository
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->repository = $repository;

        $this->couponListRepository = $couponListRepository;

        $this->bonusRepository = $bonusRepository;

        $this->bankPaymentRepository = $bankPaymentRepository;

        $this->memberRepository = $memberRepository;

        $this->memberCreditLogRepository = $memberCreditLogRepository;

        $this->memberCreditFreeLogRepository = $memberCreditFreeLogRepository;

    }

    public function redeem(Request $request)
    {

        $config = core()->getConfigData();
        $status = false;
        $datenow = now()->toDateString();
        $datetime = now()->toDateTimeString();
        $code = $request->input('coupon');

        $member = $this->user();


        $coupon = $this->couponListRepository->scopeQuery(function ($query) use ($code, $datenow) {
            return $query->whereDate('date_start', '<=', $datenow)->whereDate('date_stop', '>=', $datenow)->where('status', 'N')->where('enable', 'Y')->where('name', $code);
        })->first();


        if (isset($coupon)) {
            $main = $this->repository->findOneByField('code', $coupon['coupon_code']);
            if (isset($main)) {
                if ($main['enable'] == 'N') {
                    return $this->sendError(Lang::get('app.coupon.cannot'), 200);
                }

                $coupon_chk = CouponListProxy::where('member_code', $member->code)->where('coupon_code', $main->code)->where('enable', 'Y')->first();

                if (isset($coupon_chk)) {
                    return $this->sendError(Lang::get('app.coupon.cannot_rejoin'), 200);
                }

                if ($coupon['money'] > 0) {
                    if (is_null($main['refill_start'])) {
                        $payment = $this->bankPaymentRepository->where('member_topup', $member->code)->where('status', 1)->where('enable', 'Y')->where('bankstatus', 1)->sum('value');

                    } else {
                        $payment = $this->bankPaymentRepository->where('member_topup', $member->code)->where('status', 1)->where('enable', 'Y')->where('bankstatus', 1)->whereBetween('date_approve', array($main['refill_start'], $main['refill_stop']))->sum('value');

                    }

//                    $payment = $this->bankPaymentRepository->findWhere(['status' => 1, 'enable' => 'Y', 'bankstatus' => 1]);

                    if ($payment >= $coupon['money']) {
                        $status = true;
                    } else {
                        return $this->sendError(Lang::get('app.coupon.condition'), 200);
                    }
                }

                if ($coupon['date_expire'] == 0) {
                    $expire = null;
                } else {
                    $expire = now()->addDays($coupon['date_expire']);
                }


                $bill = $this->bonusRepository->create([
                    'refer_coupon' => $coupon['code'],
                    'name' => $main['name'],
                    'cashback' => $coupon['cashback'],
                    'member_code' => $member->code,
                    'value' => $coupon['value'],
                    'turnpro' => $coupon['turnpro'],
                    'amount_limit' => $coupon['amount_limit'],
                    'date_expire' => $expire,
                    'status' => 'N',
                    'user_create' => 'SYSTEM',
                    'user_update' => 'SYSTEM',
                ]);

                if ($coupon->cashback == 'Y') {

                    $this->memberCreditFreeLogRepository->create([
                        'enable' => 'Y',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $coupon['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => 0,
                        'balance_after' => 0,
                        'credit' => $coupon['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bill->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "ได้รับเครดิตโบนัส (ฟรี) จากคูปอง " . $coupon['name'] . " จำนวน  :" . $coupon['value'],
                        'kind' => 'BONUS',
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                } else {

                    $this->memberCreditLogRepository->create([
                        'enable' => 'Y',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $coupon['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => 0,
                        'balance_after' => 0,
                        'credit' => $coupon['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bill->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "ได้รับเครดิตโบนัส จากคูปอง " . $coupon['name'] . " จำนวน  :" . $coupon['value'],
                        'kind' => 'BONUS',
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                }


                $coupon->status = 'Y';
                $coupon->member_code = $member->code;
                $coupon->date_update = $datetime;
                $coupon->save();

                return $this->sendSuccess(Lang::get('app.coupon.credit_amount') . $coupon['value']);


            } else {
                return $this->sendError(Lang::get('app.coupon.fail'), 200);
            }


        } else {
            return $this->sendError(Lang::get('app.coupon.empty'), 200);
        }

    }

    public function bonusList()
    {
        $datenow = now()->toDateString();
        $html = '';
        $id = $this->id();
        $datas = $this->bonusRepository->findWhere(['member_code' => $id, 'status' => 'N']);

        if (isset($datas)) {
            foreach ($datas as $item) {
                if (!is_null($item->date_expire)) {
                    if ($datenow >= $item->date_expire) continue;
                }
                $method = ($item->cashback == 'Y' ? Lang::get('app.coupon.freecredit') : Lang::get('app.coupon.credit'));
                $html .= '<div class="card border-primary mb-3">';
                $html .= '<div class="card-body text-primary">';
                $html .= '<h5 class="card-title">' . $method . '</h5>';
                $html .= '<p class="card-text">'. Lang::get('app.coupon.amount').  $item->value . Lang::get('app.coupon.turn') . $item->turnpro . Lang::get('app.coupon.limit'). $item->amount_limit . Lang::get('app.coupon.rate').'</p>';
                if (!is_null($item->date_expire)) {
                    $html .= '<p class="card-text">'.Lang::get('app.coupon.canget') . $item->date_expire . '</p>';
                }
                $html .= '<button onclick="getBonus(' . $item->code . ')" class="btn btn-primary">'.Lang::get('app.coupon.get').'</button>';
                $html .= '</div>';
                $html .= '</div>';
            }
        } else {

            $html .= '<div class="card border-primary mb-3">';
            $html .= '<div class="card-body text-primary">';
            $html .= '<h5 class="card-title">'.Lang::get('app.coupon.notfound').'</h5>';
            $html .= '</div>';
            $html .= '</div>';

        }

        $result['html'] = $html;
        return $this->sendResponseNew($result, 'complete');


    }

    public function getBonus(Request $request)
    {
        $config = core()->getConfigData();
        $code = $request->input('id');
        $member = $this->user();

        $gamelist = core()->getGame();
        $bonus = $this->bonusRepository->findOneWhere(['member_code' => $member->code, 'code' => $code, 'status' => 'N']);
        if (isset($bonus)) {

            if ($config->multigame_open == 'Y') {

                if ($bonus->cashback == 'Y') {
//                    $game_user = app('Gametech\Game\Repositories\GameUserFreeRepository')->findOneWhere(['member_code' => $member->code, 'game_code' => $gamelist->code, 'enable' => 'Y']);
//                    if (!$game_user) {
//                        return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);
//                    }
                    $amount = $bonus->value;
                    $credit_before = $member->balance_free;
                    $credit_after = $member->balance_free + $bonus->value;

                    $total = ($member->balance_free + $amount);
                    $amount_total = ($total * 0);
                    $withdraw_limit_amount = ($total * 0);

                    $this->memberCreditFreeLogRepository->create([
                        'enable' => 'Y',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $bonus['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => $credit_before,
                        'balance_after' => $credit_after,
                        'credit' => $bonus['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bonus->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "รับเครดิตเข้ากระเป๋าฟรี จาก " . $bonus['name'] . " จำนวน  :" . $bonus['value'],
                        'kind' => 'G_BONUS',
                        'amount_balance' => $amount_total,
                        'withdraw_limit' => 0,
                        'withdraw_limit_amount' => $withdraw_limit_amount,
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                    $member->balance_free += $bonus->value;
                    $member->save();

                } else {

//                    $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneWhere(['member_code' => $member->code, 'game_code' => $gamelist->code, 'enable' => 'Y']);
//                    if (!$game_user) {
//                        return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);
//                    }

                    $amount = $bonus->value;
                    $credit_before = $member->balance;
                    $credit_after = $member->balance + $bonus->value;

                    $total = ($member->balance + $amount);
                    $amount_total = ($total * 0);
                    $withdraw_limit_amount = ($total * 0);

                    $this->memberCreditLogRepository->create([
                        'enable' => 'Y',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $bonus['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => $credit_before,
                        'balance_after' => $credit_after,
                        'credit' => $bonus['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bonus->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "รับเครดิตเข้ากระเป๋า จาก " . $bonus['name'] . " จำนวน  :" . $bonus['value'],
                        'kind' => 'G_BONUS',
                        'amount_balance' => $amount_total,
                        'withdraw_limit' => 0,
                        'withdraw_limit_amount' => $withdraw_limit_amount,
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                    $member->balance += $bonus->value;
                    $member->save();

                }

            } else {


                if ($bonus->cashback == 'Y') {
                    if ($bonus->turnpro > 0) {
                        if ($member->balance_free > $config->pro_reset) {
                            return $this->sendError(Lang::get('app.coupon.cannot_get') . $config->pro_reset, 200);
                        }
                    }

                    $game_user = app('Gametech\Game\Repositories\GameUserFreeRepository')->findOneWhere(['member_code' => $member->code, 'game_code' => $gamelist->code, 'enable' => 'Y']);
                    if (!$game_user) {
                        return $this->sendError(Lang::get('app.coupon.nomember'), 200);
                    }
                    $amount = $bonus->value;
                    $credit_before = $member->balance_free;
                    $credit_after = $member->balance_free + $bonus->value;

                    $total = ($member->balance_free + $amount);
                    $amount_total = ($total * $bonus->turnpro);
                    $withdraw_limit_amount = ($total * $bonus->amount_limit);

                    $this->memberCreditFreeLogRepository->create([
                        'enable' => 'N',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $bonus['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => $credit_before,
                        'balance_after' => $credit_after,
                        'credit' => $bonus['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bonus->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "รับเครดิตเข้ากระเป๋าฟรี จาก " . $bonus['name'] . " จำนวน  :" . $bonus['value'],
                        'kind' => 'G_BONUS',
                        'amount_balance' => $amount_total,
                        'withdraw_limit' => 0,
                        'withdraw_limit_amount' => $withdraw_limit_amount,
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                    $member->balance_free += $bonus->value;
                    $member->save();
                } else {
                    if ($bonus->turnpro > 0) {
                        if ($member->balance > $config->pro_reset) {
                            return $this->sendError(Lang::get('app.coupon.cannot_get'). $config->pro_reset, 200);
                        }
                    }

                    $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneWhere(['member_code' => $member->code, 'game_code' => $gamelist->code, 'enable' => 'Y']);
                    if (!$game_user) {
                        return $this->sendError(Lang::get('app.coupon.nomember'), 200);
                    }

                    $amount = $bonus->value;
                    $credit_before = $member->balance;
                    $credit_after = $member->balance + $bonus->value;

                    $total = ($member->balance + $amount);
                    $amount_total = ($total * $bonus->turnpro);
                    $withdraw_limit_amount = ($total * $bonus->amount_limit);

                    $this->memberCreditLogRepository->create([
                        'enable' => 'N',
                        'ip' => request()->ip(),
                        'credit_type' => 'D',
                        'amount' => $bonus['value'],
                        'bonus' => 0,
                        'total' => 0,
                        'balance_before' => $credit_before,
                        'balance_after' => $credit_after,
                        'credit' => $bonus['value'],
                        'credit_bonus' => 0,
                        'credit_total' => 0,
                        'credit_before' => 0,
                        'credit_after' => 0,
                        'member_code' => $member->code,
                        'user_name' => $member->user_name,
                        'game_code' => 0,
                        'gameuser_code' => 0,
                        'pro_code' => 0,
                        'bank_code' => 0,
                        'refer_code' => $bonus->code,
                        'refer_table' => 'bonus',
                        'auto' => 'N',
                        'remark' => "รับเครดิตเข้ากระเป๋า จาก " . $bonus['name'] . " จำนวน  :" . $bonus['value'],
                        'kind' => 'G_BONUS',
                        'amount_balance' => $amount_total,
                        'withdraw_limit' => 0,
                        'withdraw_limit_amount' => $withdraw_limit_amount,
                        'user_create' => '',
                        'user_update' => ''
                    ]);

                    $member->balance += $bonus->value;
                    $member->save();
                }

            }
            $bonus->status = 'Y';
            $bonus->save();

            if ($config->seamless == 'Y') {
                $game_user->pro_code = 999;
                $game_user->bill_code = $bonus->code;
                $game_user->amount = 0;
                $game_user->bonus = $bonus->value;
                $game_user->turnpro = $bonus->turnpro;
                $game_user->amount_balance = $amount_total;
                $game_user->withdraw_limit = 0;
                $game_user->withdraw_limit_rate = $bonus->amount_limit;
                $game_user->withdraw_limit_amount = $withdraw_limit_amount;
                $game_user->save();
            }


            return $this->sendSuccess(Lang::get('app.coupon.credit_amount'). $bonus['value']);

        } else {
            return $this->sendError(Lang::get('app.coupon.expire'), 200);
        }

    }


}
