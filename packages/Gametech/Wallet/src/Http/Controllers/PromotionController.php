<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BillRepository;
use Gametech\Promotion\Repositories\PromotionContentRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class PromotionController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    private $promotionRepository;

    private $proContentRepository;

    private $memberRepository;

    private $billRepository;

    private $bankPaymentRepository;

    private $gameUserRepository;

    /**
     * Create a new Repository instance.
     *
     * @param PromotionRepository $promotionRepo
     * @param MemberRepository $memberRepo
     * @param PromotionContentRepository $proContentRepo
     */
    public function __construct
    (
        PromotionRepository        $promotionRepo,
        MemberRepository           $memberRepo,
        PromotionContentRepository $proContentRepo,
        BillRepository             $billRepo,
        BankPaymentRepository      $bankPaymentRepo,
        GameUserRepository         $gameUserRepo
    )
    {
        $this->middleware('customer')->except(['show']);

        $this->_config = request('_config');

        $this->promotionRepository = $promotionRepo;

        $this->memberRepository = $memberRepo;

        $this->proContentRepository = $proContentRepo;

        $this->billRepository = $billRepo;

        $this->bankPaymentRepository = $bankPaymentRepo;

        $this->gameUserRepository = $gameUserRepo;
    }

    public function index()
    {
        $pro = false;
        $pro_limit = 0;
        $promotions = [];

        $config = core()->getConfigData();
        if ($config->seamless == 'Y') {
            if (($config->pro_onoff == 'Y')) {
                if ($config->pro_wallet == 'Y') {
                    $pro = true;
                }
            }


            if ($this->user()->promotion == 'N') {
                $pro = false;
            }


            if ($pro) {

                $pro_limit = $this->memberRepository->getPro($this->id());
                if ($pro_limit > 0) {
                    $promotions = $this->promotionRepository->loadPromotion($this->id());
                } else {
                    $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);
                }
            } else {
                $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);

            }

        } else {
            $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);

        }

        $pro_contents = $this->proContentRepository->orderBy('sort')->findWhere(['enable' => 'Y', ['code', '<>', 0]]);
        $profile = $this->user()->load('bank');

        $type = app('Gametech\Game\Repositories\GameTypeRepository')->findOneByField('id', 'PROMOTION');


        return view($this->_config['view'], compact('promotions', 'pro_contents', 'pro_limit', 'profile', 'type'));

    }

    public function loadPromotion()
    {

        $pro = false;
        $pro_limit = 0;
        $config = core()->getConfigData();
        if ($config->seamless === 'Y') {
            if (($config->pro_onoff == 'Y')) {
                if ($config->pro_wallet == 'Y') {
                    $pro = true;
                }
            }

            if ($this->user()->promotion == 'N') {
                $pro = false;
            }

            if ($pro) {

                $pro_limit = $this->memberRepository->getPro($this->id());
                if ($pro_limit > 0) {
                    $promotions = $this->promotionRepository->loadPromotion($this->id())->toArray();
                } else {
                    $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]])->toArray();
                }
            } else {
                $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]])->toArray();

            }

        } else {
            $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]])->toArray();

        }


        $pro_contents = $this->proContentRepository->orderBy('sort')->findWhere(['enable' => 'Y', ['code', '<>', 0]])->toArray();


//        $pros = collect($pro_contents)->prepend($promotions);
        $pro_contents = collect($pro_contents)->map(function ($items, $key) {

            $items['filepic'] = Storage::url('procontent_img/' . $items['filepic']);

            return $items;
        });

        $promotions = collect($promotions)->map(function ($items, $key) {

            $items['filepic'] = Storage::url('promotion_img/' . $items['filepic']);

            return $items;
        });

        $pros = collect($pro_contents)->merge($promotions);

        $result['promotions'] = $pros;
        $result['getpro'] = (($pro_limit > 0) ? true : false);

        return $this->sendResponse($result, 'Complete');
    }

    public function show()
    {
        $pro = false;
        $pro_limit = 0;
        $promotions = [];

        $type = app('Gametech\Game\Repositories\GameTypeRepository')->findOneByField('id', 'PROMOTION');

        $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);

        $pro_contents = $this->proContentRepository->orderBy('sort')->findWhere(['enable' => 'Y', ['code', '<>', 0]]);

        return view($this->_config['view'], compact('promotions', 'pro_contents', 'pro_limit', 'type'));

    }

    public function indextest()
    {
        $pro = false;
        $pro_limit = 0;
        $promotions = [];

        $config = core()->getConfigData();
        if ($config->seamless == 'Y') {
            if (($config->pro_onoff == 'Y')) {
                if ($config->pro_wallet == 'Y') {
                    $pro = true;
                }
            }


            if ($this->user()->promotion == 'N') {
                $pro = false;
            }


            if ($pro) {

                $pro_limit = $this->memberRepository->getPro($this->id());
                if ($pro_limit > 0) {
                    $promotions = $this->promotionRepository->loadPromotiontest($this->id());
                } else {
                    $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);
                }
            } else {
                $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);

            }

        } else {
            $promotions = $this->promotionRepository->orderBy('sort')->findWhere(['enable' => 'Y', 'use_wallet' => 'Y', ['code', '<>', 0]]);

        }

        $pro_contents = $this->proContentRepository->orderBy('sort')->findWhere(['enable' => 'Y', ['code', '<>', 0]]);

        return view($this->_config['view'], compact('promotions', 'pro_contents', 'pro_limit'));

    }

    public function store(Request $request)
    {

        $promotion = [];
        $datenow = now();
        $user = $this->user();
        $promotion_id = $request->input('promotion');
        $amount = $this->memberRepository->getPro($user->code);
        if ($amount) {

            $promotion = $this->promotionRepository->checkSelectPro($promotion_id, $user->code, $amount, $datenow);

            if ($promotion['bonus'] == 0) {
                session()->flash('error', 'ขออภัยค่ะ คุณไม่สามารถรับโปรโมชั่นนี้ได้ หรือ เงื่อนไขในการรับไม่ถูกต้อง');
                return redirect()->route('customer.promotion.index');
            }

            $promotion['amount'] = $amount;
            $promotion['member_code'] = $user->code;
            $response = $this->billRepository->getPro($promotion);
            if ($response['success'] === true) {
                $bills = $response['data'];
                session()->flash('success', 'ได้รับโบนัสจำนวน ' . $bills->credit_bonus);
            } else {
                session()->flash('error', $response['msg']);
            }
            return redirect()->route('customer.promotion.index');
        } else {
            session()->flash('error', 'ขออภัยค่ะ คุณไม่สามารถรับโปรโมชั่นนี้ได้');
            return redirect()->route('customer.promotion.index');
        }

    }

    public function store_api(Request $request)
    {

        $promotion = [];
        $datenow = now();
        $user = $this->user();
        $promotion_id = $request->input('promotion');
        $amount = $this->memberRepository->getPro($user->code);
        if ($amount) {

            $promotion = $this->promotionRepository->checkSelectPro($promotion_id, $user->code, $amount, $datenow);

            if ($promotion['bonus'] == 0) {

                return $this->sendError('ขออภัยค่ะ คุณไม่สามารถรับโปรโมชั่นนี้ได้ หรือ เงื่อนไขในการรับไม่ถูกต้อง', 200);
            }

            $promotion['amount'] = $amount;
            $promotion['member_code'] = $user->code;
            $response = $this->billRepository->getPro($promotion);
            if ($response['success'] === true) {
                $bills = $response['data'];
                return $this->sendSuccess('ได้รับโบนัสจำนวน ' . $bills->credit_bonus);

            } else {
                return $this->sendError($response['msg'], 200);

            }

        } else {

            return $this->sendError('ขออภัยค่ะ คุณไม่สามารถรับโปรโมชั่นนี้ได้', 200);
        }

    }

    public function cancel()
    {
        $user = $this->user();

        $amount = $this->memberRepository->getPro($user->code);
        if ($amount > 0) {
            $gameuser = $this->gameUserRepository->findOneWhere(['member_code' => $user->code]);
            if (!$gameuser) {
                return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);
            }

//            $gameuser->withdraw_limit_amount += ($gameuser->withdraw_limit_rate * $amount);
//            $gameuser->amount_balance += ($amount * $gameuser->turnpro);
//            $gameuser->save();

            $this->bankPaymentRepository->where('member_topup', $user->code)->where('pro_check', 'N')->update([
                'pro_check' => 'Y',
                'user_update' => $user->name
            ]);

            app('Gametech\Member\Repositories\MemberCreditLogRepository')->create([
                'ip' => request()->ip(),
                'credit_type' => 'D',
                'balance_before' => $user->balance,
                'balance_after' => $user->balance,
                'credit' => 0,
                'total' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => $user->balance,
                'credit_after' => $user->balance,
                'pro_code' => 0,
                'bank_code' => 0,
                'auto' => 'N',
                'enable' => 'Y',
                'user_create' => "System Auto",
                'user_update' => "System Auto",
                'refer_code' => 0,
                'refer_table' => 'blank',
                'remark' => 'กดปุ่ม ไม่รับโปร บนหน้าต่าง POPUP แจ้งเตือนการได้รับสิทธิ์ (ยอดเติม ' . $amount . ')',
                'kind' => 'OTHER',
                'amount' => 0,
                'amount_balance' => $gameuser->amount_balance,
                'withdraw_limit' => $gameuser->withdraw_limit,
                'withdraw_limit_amount' => $gameuser->withdraw_limit_amount,
                'method' => 'D',
                'member_code' => $user->code
            ]);

        }


        return $this->sendSuccess('ดำเนินการสำเร็จ');

    }


}
