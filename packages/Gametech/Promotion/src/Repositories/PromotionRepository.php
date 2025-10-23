<?php

namespace Gametech\Promotion\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Repositories\MemberPromotionLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionRepository extends Repository
{
    protected $memberRepository;
    protected $memberPromotionLogRepository;
    protected $promotionTimeRepository;
    protected $promotionAmountRepository;


    /**
     * PromotionRepository constructor.
     * @param MemberRepository $memberRepository
     * @param PromotionTimeRepository $promotionTimeRepository
     * @param PromotionAmountRepository $promotionAmountRepository
     * @param MemberPromotionLogRepository $memberPromotionLogRepository
     * @param App $app
     */
    public function __construct
    (
        MemberRepository             $memberRepository,
        PromotionTimeRepository      $promotionTimeRepository,
        PromotionAmountRepository    $promotionAmountRepository,
        MemberPromotionLogRepository $memberPromotionLogRepository,

        App                          $app
    )
    {

        $this->memberRepository = $memberRepository;
        $this->memberPromotionLogRepository = $memberPromotionLogRepository;
        $this->promotionTimeRepository = $promotionTimeRepository;
        $this->promotionAmountRepository = $promotionAmountRepository;


        parent::__construct($app);
    }

    public function checkSelectPro($pro_id, $member_id, $amount, $date)
    {
        $promotion = [
            'pro_code' => 0,
            'pro_id' => '',
            'pro_name' => '',
            'turnpro' => 0,
            'withdraw_limit' => 0,
            'withdraw_limit_rate' => 0,
            'bonus' => 0,
            'total' => $amount,
        ];

        $pro = $this->find($pro_id);
        if (!$pro) {
            return $promotion;
        }

//        if($pro->amount_min > 0){
//            if($pro->amount_min > $amount){
//                return $promotion;
//            }
//        }

        $member = $this->memberRepository->find($member_id);


        switch ($pro->id) {
            case 'pro_newuser':
                if ($member->status_pro === 0) {
                    $promotion = $this->checkPromotion($pro_id, $amount, $date);
                }
                break;

            case 'pro_firstday':
                if ($this->checkProFirstDay($member->code) == 0) {
                    $promotion = $this->checkPromotion($pro_id, $amount, $date);
                }
                break;

            case 'pro_bigbonus':
            case 'pro_allbonus':
            case 'pro_hottime':
                $promotion = $this->checkPromotion($pro_id, $amount, $date);
                break;

//            case 'pro_hottime':
//                $promotion = $this->checkPromotion($pro_id, $amount, $date);
//                break;

            default:
                $promotion = $this->checkPromotion($pro_id, $amount, $date);
        }

        return $promotion;

    }


    public function checkPromotion($id, $amount, $date)
    {
        $min = 0;
        $pro_amount = 0.00;
        $order = array();

        $promotion = $this->find($id);

        if (!empty($promotion)) {

            if ($amount < $promotion->amount_min) {
                $pro_amount = 0.00;
            } else {

                switch ($promotion->length_type) {
                    case 'PRICE':
                        $pro_amount = $promotion->bonus_price;
                        break;
                    case 'PERCENT':
                        $pro_amount = $amount * $promotion->bonus_percent / 100;
                        break;
                    case 'TIME':
                        $order = $this->promotionTimeRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'TIMEPC':
                        $order = $this->promotionTimeRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'AMOUNT':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'AMOUNTPC':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'BETWEEN':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'BETWEENPC':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                }

            }


            if ($pro_amount < $promotion->bonus_min) {
                $pro_amount = 00.00;
            }

            if($promotion->bonus_max > 0) {
                if ($pro_amount > $promotion->bonus_max) {
                    $pro_amount = $promotion->bonus_max;
                }
            }

        }


        $type = [
            '' => '-',
            'PRICE' => 'จ่ายเป็น บาท',
            'PERCENT' => 'จ่ายเป็น %',
            'TIME' => 'ช่วงเวลา จ่ายเป็น บาท',
            'TIMEPC' => 'ช่วงเวลา จ่ายเป็น %',
            'AMOUNT' => 'ช่วงราคาตรงกัน จ่ายเป็น บาท',
            'AMOUNTPC' => 'ช่วงราคาตรงกัน จ่ายเป็น %',
            'BETWEEN' => 'ช่วงระหว่างราคา จ่ายเป็น บาท',
            'BETWEENPC' => 'ช่วงระหว่างราคา จ่ายเป็น %'
        ];

        if ($pro_amount > 0) {
            $total = ($amount + $pro_amount);

            $result['pro_code'] = $id;
            $result['pro_id'] = $promotion->id;
            $result['pro_name'] = $promotion->name_th;
            $result['turnpro'] = $promotion->turnpro;
            $result['withdraw_limit'] = $promotion->withdraw_limit;
            $result['withdraw_limit_rate'] = $promotion->withdraw_limit_rate;
            $result['total'] = $total;
            $result['bonus'] = $pro_amount;
        } else {
            $result['pro_code'] = 0;
            $result['pro_id'] = '';
            $result['pro_name'] = '';
            $result['turnpro'] = 0;
            $result['withdraw_limit'] = 0;
            $result['withdraw_limit_rate'] = 0;
            $result['total'] = $amount;
            $result['bonus'] = 0;
        }
        $result['type'] = $type[$promotion->length_type];

        return $result;
    }

    public function checkPromotionId($pro_id, $amount, $date)
    {
        $id = 0;
        $min = 0;
        $pro_amount = 0.00;
        $order = array();

        $promotion = $this->findOneWhere(['enable' => 'Y', 'active' => 'Y', 'id' => $pro_id]);
//        $promotion = $this->findOneByField('id', $pro_id);

        if (!empty($promotion)) {

            $id = $promotion->code;

            if ($amount < $promotion->amount_min) {
                $pro_amount = 0.00;
            } else {

                switch ($promotion->length_type) {
                    case 'PRICE':
                        $pro_amount = $promotion->bonus_price;
                        break;
                    case 'PERCENT':
                        $pro_amount = ($amount * $promotion->bonus_percent) / 100;
                        break;
                    case 'TIME':
                        $order = $this->promotionTimeRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'TIMEPC':
                        $order = $this->promotionTimeRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'AMOUNT':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'AMOUNTPC':
                        $order = $this->promotionAmountRepository->promotion($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                    case 'BETWEEN':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = $order['amount'];
                        break;
                    case 'BETWEENPC':
                        $order = $this->promotionAmountRepository->promotionBetween($id, $amount);
                        $pro_amount = ($amount * $order['amount']) / 100;
                        break;
                }
            }

            if ($pro_amount < $promotion->bonus_min) {
                $pro_amount = 00.00;
            }

            if($promotion->bonus_max > 0) {
                if ($pro_amount > $promotion->bonus_max) {
                    $pro_amount = $promotion->bonus_max;
                }
            }

        }


        $type = [
            '' => '-',
            'PRICE' => 'จ่ายเป็น บาท',
            'PERCENT' => 'จ่ายเป็น %',
            'TIME' => 'ช่วงเวลา จ่ายเป็น บาท',
            'TIMEPC' => 'ช่วงเวลา จ่ายเป็น %',
            'AMOUNT' => 'ช่วงราคาตรงกัน จ่ายเป็น บาท',
            'AMOUNTPC' => 'ช่วงราคาตรงกัน จ่ายเป็น %',
            'BETWEEN' => 'ช่วงระหว่างราคา จ่ายเป็น บาท',
            'BETWEENPC' => 'ช่วงระหว่างราคา จ่ายเป็น %'
        ];

        if ($pro_amount > 0) {
            $total = ($amount + $pro_amount);

            $result['pro_code'] = $id;
            $result['pro_id'] = $promotion->id;
            $result['pro_name'] = $promotion->name_th;
            $result['turnpro'] = $promotion->turnpro;
            $result['withdraw_limit'] = $promotion->withdraw_limit;
            $result['withdraw_limit_rate'] = $promotion->withdraw_limit_rate;
            $result['total'] = $total;
            $result['bonus'] = $pro_amount;
            $result['type'] = $type[$promotion->length_type];
        } else {
            $result['pro_code'] = 0;
            $result['pro_id'] = '';
            $result['pro_name'] = '';
            $result['turnpro'] = 0;
            $result['withdraw_limit'] = 0;
            $result['withdraw_limit_rate'] = 0;
            $result['total'] = $amount;
            $result['bonus'] = 0.00;
            $result['type'] = '-';
        }


        return $result;
    }


    public function loadPromotion($id)
    {
        $datenow = now();
        $today = now()->toDateString();

        $member = $this->memberRepository->find($id);
        $count = $this->checkProFirstDay($id);
        $hottime = $this->checkHotTime($today, '00:00', '00:01', $datenow);


        $code[] = 'pro_faststart';
        $code[] = 'pro_cashback';
        $code[] = 'pro_ic';
//        $pro = $this->active()->where('use_wallet', 'Y')->whereNotIn('id', ['pro_faststart','pro_cashback','pro_ic']);


        if ($member->status_pro == 1) {

            $code[] = 'pro_newuser';
        }

        if ($count) {
            $code[] = 'pro_firstday';
        }

        if (!$hottime) {

            $code[] = 'pro_hottime';
        }


        return $this->active()->where('use_wallet', 'Y')->whereNotIn('id', $code)->get();

//        $pro .= $pro->whereNotIn('id', 'pro_faststart')->whereNotIn('id', 'pro_cashback')->whereNotIn('id', 'pro_ic');
//        dd($pro->get());
//        return (clone $pro)->get();

    }

    public function loadPromotiontest($id)
    {
        $datenow = now();
        $today = '2021-11-20';

        $member = $this->memberRepository->find($id);
        $count = $this->checkProFirstDayTest($id);
        $hottime = $this->checkHotTime($today, '00:00', '00:01', $datenow);


        $code[] = 'pro_faststart';
        $code[] = 'pro_cashback';
        $code[] = 'pro_ic';
//        $pro = $this->active()->where('use_wallet', 'Y')->whereNotIn('id', ['pro_faststart','pro_cashback','pro_ic']);


        if ($member->status_pro == 1) {

            $code[] = 'pro_newuser';
        }

        if ($count) {
            $code[] = 'pro_firstday';
        }
        if (!$hottime) {

            $code[] = 'pro_hottime';
        }


        return $this->active()->where('use_wallet', 'Y')->whereNotIn('id', $code)->get();

//        $pro .= $pro->whereNotIn('id', 'pro_faststart')->whereNotIn('id', 'pro_cashback')->whereNotIn('id', 'pro_ic');
//        dd($pro->get());
//        return (clone $pro)->get();

    }

    public function checkProFirstDay($id)
    {
        $bill = false;
        $today = now()->toDateString();
//        $member = $this->memberRepository->find($id);
//        $user =  $this->billRepository->with(['promotion' => function (Builder $query) {
//            $query->where('id', 'pro_firstday');
//        }]);
//        return $this->findOneWhere(['id' => 'pro_firstday'])->bill()->where('member_code', $id)->whereDate('date_create', $today)->exists();
//        $data = $this->where('id', 'pro_firstday')->with(['bills' => function ($query) use ($id, $today) {
//            $query->where('member_code', $id)->whereDate('date_create', $today);
//        }])->first();

//        $datas = $this->where('id', 'pro_firstday')->with(['bill' => function ($query) use ($id, $today) {
//            $query->where('member_code', $id)->whereDate('date_create', $today);
//        }])->get();

        $datas = $this->whereIn('id', ['pro_firstday','pro_newuser','pro_allbonus'])->with(['bill' => function ($query) use ($id, $today) {
            $query->where('member_code', $id)->whereDate('date_create', $today);
        }])->get();

        $datas = $datas->toArray();



        foreach($datas as $data){
            if(!is_null($data['bill'])){
                $bill = true;
            }
        }

//        dd($bill);


        return $bill;

//        $datas = $this->whereIn('id', ['pro_firstday','pro_newuser','pro_allbonus'])->with(['bill' => function ($query) use ($id, $today) {
//            $query->where('member_code', $id)->whereDate('date_create', $today);
//        }])->get();
//
//        $datas = $datas->toArray();
//
//
//        foreach($datas as $data){
//            if(!is_null($data['bill'])){
//                $bill = true;
//            }
//        }

//        $bill = ($data->bills ?? []);
//        if (count($bill) > 0) {
//            return true;
//        }
//        return false;
//        dd($data->exists());
//            $query->where('id', 'pro_firstday');
//        }])->where('member_code', $id)->whereDate('date_create', $today)->exists();
//        $user = $this->find(1);
//        $user = $member->bills()->with('promotion')->whereHas(['promotion' => function (Builder $query) {
//            $query->where('id', 'pro_firstday');
//        }])->get();
//        dd($user);
//        return $member->bills()->promotion()->where('id', 'pro_firstday')->whereDate('date_create', $today)->exists();

    }

    public function checkProFirstDayTest($id)
    {
        $bill = false;
        $today = now()->toDateString();
//        $member = $this->memberRepository->find($id);
//        $user =  $this->billRepository->with(['promotion' => function (Builder $query) {
//            $query->where('id', 'pro_firstday');
//        }]);
//        return $this->findOneWhere(['id' => 'pro_firstday'])->bill()->where('member_code', $id)->whereDate('date_create', $today)->exists();
//        $data = $this->where('id', 'pro_firstday')->has('bills', function ($model) use ($id, $today) {
//            $model->where('member_code', $id)->whereDate('date_create', $today);
//        })->first();

        $datas = $this->whereIn('id', ['pro_firstday','pro_newuser','pro_allbonus'])->with(['bill' => function ($query) use ($id, $today) {
            $query->where('member_code', $id)->whereDate('date_create', $today);
        }])->get();

        $datas = $datas->toArray();



        foreach($datas as $data){
            if(!is_null($data['bill'])){
                $bill = true;
            }
        }

//        dd($bill);


        return $bill;
//        dd($data->exists());
//            $query->where('id', 'pro_firstday');
//        }])->where('member_code', $id)->whereDate('date_create', $today)->exists();
//        $user = $this->find(1);
//        $user = $member->bills()->with('promotion')->whereHas(['promotion' => function (Builder $query) {
//            $query->where('id', 'pro_firstday');
//        }])->get();
//        dd($user);
//        return $member->bills()->promotion()->where('id', 'pro_firstday')->whereDate('date_create', $today)->exists();

    }


    public function checkHotTime($today, $time_start, $time_stop, $datenow)
    {
        $datestart = $today . ' ' . $time_start . ':00';
        $datestop = $today . ' ' . $time_stop . ':00';
        $hot = DB::select("select '$datenow' as datenow,'$datestart' as datestart,'$datestop' as datestop  from dual where ? between ? and ?", [$datenow, $datestart, $datestop]);
        if (is_null($hot)) {
            return false;
        }
        return true;

    }

    public function CalculatePro($member, $amount, $date)
    {
        $bonus = 0;
        $pro_code = 0;
        $total = $amount;
        $status_pro = $member['status_pro'];
        $pro_name = '';
        $withdraw_limit = 0;
        $withdraw_limit_rate = 0;
        $turnpro = 0;
        // Check Member Get Promotion (for single mode)
        if ($member['promotion'] == 'Y') {

            // Pro New User for First Deposit
            if ($status_pro == 0) {
                $promotion = $this->checkPromotionId('pro_newuser', $amount, $date);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $pro_name = $promotion['pro_name'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
                $turnpro = $promotion['turnpro'];

                if ($bonus > 0) {
                    $status_pro = 1;
                }
            }

            if ($bonus == 0) {
                // Pro First Deposit of Day
                $count_firstday = $this->checkProFirstDay($member['code']);
                if ($count_firstday === false) {
                    $promotion = $this->checkPromotionId('pro_firstday', $amount, $date);
                    $bonus = $promotion['bonus'];
                    $pro_code = $promotion['pro_code'];
                    $total = $promotion['total'];
                    $pro_name = $promotion['pro_name'];
                    $withdraw_limit = $promotion['withdraw_limit'];
                    $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
                    $turnpro = $promotion['turnpro'];
                }
            }

            // Pro Big Bonus
            if ($bonus == 0) {
                $promotion = $this->checkPromotionId('pro_allbonus', $amount, $date);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $pro_name = $promotion['pro_name'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
                $turnpro = $promotion['turnpro'];
            }

        }

        $result['bonus'] = $bonus;
        $result['pro_code'] = $pro_code;
        $result['total'] = $total;
        $result['status_pro'] = $status_pro;
        $result['pro_name'] = $pro_name;
        $result['turnpro'] = $turnpro;
        $result['withdraw_limit'] = $withdraw_limit;
        $result['withdraw_limit_rate'] = $withdraw_limit_rate;


        return $result;

    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Promotion\Contracts\Promotion';
    }

    public function createnew(array $data)
    {
        $reward = $this->create($data);

        $order = $this->find($reward->code);


        $this->uploadImages($data, $order);


        return $order;
    }

    public function updatenew(array $data, $id, $attribute = "id")
    {
        $order = $this->find($id);

        $order->update($data);


        $this->uploadImages($data, $order);


        return $order;
    }

    public function uploadImages($data, $order, $type = "filepic")
    {

        $request = request();

        $hasfile = is_null($request->fileupload);

        if (!$hasfile) {
            $file = Str::random(10) . '.' . $request->fileupload->extension();
            $dir = 'promotion_img';

            Storage::putFileAs($dir, $request->fileupload, $file);
            $order->{$type} = $file;
            $order->save();

        }
    }
}
