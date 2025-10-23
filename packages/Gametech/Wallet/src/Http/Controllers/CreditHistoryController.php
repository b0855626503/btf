<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankRepository;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CreditHistoryController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    protected $bankRepository;

    protected $memberRepository;

    protected $sum;

    protected $sum_ic;

    protected $sum_cashback;


    /**
     * Create a new Repository instance.
     *
     * @param BankRepository $bankRepo
     * @param MemberRepository $memberRepo
     */
    public function __construct
    (
        BankRepository $bankRepo,
        MemberRepository $memberRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->bankRepository = $bankRepo;

        $this->memberRepository = $memberRepo;

        $this->sum = 0;

        $this->sum_ic = 0;
        $this->sum_cashback = 0;
    }

    public function index()
    {
        $config = core()->getConfigData();

        $banks[] = ['method' => 'withdraw' , 'name' => Lang::get('app.home.withdraw')  , 'color' => 'green' , 'id' => 'withdraw-tab' , 'href' => '#withdraw' , 'select' => 'true'];
        if ($config->multigame_open == 'Y') {
            $banks[] = ['method' => 'transfer', 'name' => 'โยก', 'color' => 'red', 'id' => 'transfer-tab', 'href' => '#transfer', 'select' => 'false'];
        }
        $banks[] = ['method' => 'cashback' , 'name' => 'Cashback' , 'color' => 'red' , 'id' => 'cashback-tab' , 'href' => '#cashback' , 'select' => 'false'];
        $banks[] = ['method' => 'memberic' , 'name' => 'IC' , 'color' => 'yellow' , 'id' => 'memberic-tab' , 'href' => '#memberic' , 'select' => 'false'];

        if ($config->freecredit_open == 'Y') {
            if ($config->wheel_open == 'Y') {
                $banks[] = ['method' => 'spin', 'name' => 'วงล้อ' , 'color' => 'green' , 'id' => 'spin-tab' , 'href' => '#spin' , 'select' => 'false'];
            }
        }
        $banks = collect($banks);

        return view($this->_config['view'], compact('banks'));
    }

    public function store()
    {
        $result['success'] = true;
        $date_start = request()->input('date_start');
        $date_stop = request()->input('date_stop');
        $id = request()->input('id');

        switch ($id){
            case 'cashback':
                $result['data'] = $this->loadCashback($date_start,$date_stop);
//                $result['sum'] = $this->sum;
                break;
            case 'withdraw':
                $result['data'] = $this->loadWithdraw($date_start,$date_stop);
                break;
            case 'transfer':
                $result['data'] = $this->loadBill($date_start,$date_stop);
                break;
            case 'memberic':
                $result['data'] = $this->loadIC($date_start,$date_stop);
//                $result['sum_ic'] = core()->currency($this->sum_ic);
//                $result['sum_cashback'] = core()->currency($this->sum_cashback);
                break;
            case 'spin':
                $result['data'] = $this->loadSpin($this->id(), $date_start, $date_stop);
                break;

            default:
                $result['data'] = '';
        }

        return json_encode($result);
    }

    public function loadWithdraw($date_start=null,$date_stop=null)
    {

        $config = core()->getConfigData();

        if ($config->seamless == 'Y') {
            $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdrawSeamlessFree($this->id(), $date_start, $date_stop)->toArray());

        } else {
            $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdrawFree($this->id(), $date_start, $date_stop)->toArray());

        }
//        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdrawSeamlessFree($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            $image = ['0' => 'ic_fail', '1' => 'ic_success', '2' => 'ic_fail'];
            $status = ['0' => 'รออนุมัติ', '1' => 'อนุมัติ', '2' => 'ไม่อนุุมัติ'];
            $color = ['0' => 'bg-info', '1' => 'bg-success', '2' => 'bg-danger'];

            return [
                'code' => $item->code,
                'id' => '#WD'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->oldcredit,
                'credit_after' => $item->aftercredit,
                'status' => $item->status,
                'image' => $image[$item->status],
                'method' => 'ถอนเงิน',
                'status_color' => $color[$item->status],
                'status_display' => $status[$item->status]
            ];

        });

        return $responses;

    }

    public function loadBill($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadBillFree($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'code' => $item->code,
                'id' => '#BL'.Str::of($item->code)->padLeft(8,0),
                'promotion_name' => (!is_null($item->promotion) ? $item->promotion : 'ไม่มี'),
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'balance_before' => $item->balance_before,
                'balance_after' => $item->balance_after,
                'credit' => $item->credit,
                'credit_bonus' => $item->credit_bonus,
                'credit_balance' => $item->credit_balance,
                'credit_before' => $item->credit_before,
                'credit_after' => $item->credit_after,
                'game_name' => $item->game['name'],
                'filepic' => $item->transfer_type == 1 ? Storage::url('game_img/'.$item->game['filepic']) : Storage::url('game_img/wallet.png'),
                'transfer' => $item->transfer_type == 1 ? 'Free Credit -> Game (โยกเข้าเกม)' : 'Free Credit <- Game (โยกออกเกม)',
                'status' => $item->transfer_type == 1 ? 'text-success' : 'text-danger',
            ];

        });

        return $responses;

    }

    public function loadCashback($date_start=null,$date_stop=null)
    {


        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadCashbackNew($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){

            $item = (object)$items;
            $status = ['0' => 'รออนุมัติ', '1' => 'อนุมัติ', '2' => 'ยกเลิก'];
            $color = ['0' => 'bg-info', '1' => 'bg-success', '2' => 'bg-danger'];

            if($item->credit_type == 'D'){
                $this->sum += $item->amount;
                $item->status_display = 'โอนเข้า';
                $item->status = 1;
            }elseif($item->credit_type == 'W'){
                $this->sum -= $item->amount;
                $item->status_display = 'โอนออก';
                $item->status = 1;
            }

            return [
                'code' => $item->code,
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->credit_before,
                'credit_after' => $item->credit_balance,
                'status' => $item->status,
                'status_color' => $color[$item->status],
                'status_display' => $status[$item->status]
            ];

        });



        return $responses;

    }

    public function loadIC($date_start=null,$date_stop=null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadICNew($this->id(),$date_start,$date_stop)->toArray());

        $responses = $responses->map(function ($items){

            $item = (object)$items;
            $status = ['0' => 'รออนุมัติ', '1' => 'อนุมัติ', '2' => 'ยกเลิก'];
            $color = ['0' => 'bg-info', '1' => 'bg-success', '2' => 'bg-danger'];
//            dd($item);
//            $this->sum_ic += $item->ic;
//            $this->sum_cashback += $item->cashback;

            if($item->credit_type == 'D'){
                $this->sum += $item->amount;
                $item->status_display = 'โอนเข้า';
                $item->status = 1;
            }elseif($item->credit_type == 'W'){
                $this->sum -= $item->amount;
                $item->status_display = 'โอนออก';
                $item->status = 1;
            }

            return [
                'code' => $item->code,
                'date_create' => core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->credit_before,
                'credit_after' => $item->credit_balance,
                'status' => $item->status,
                'status_color' => $color[$item->status],
                'status_display' => $status[$item->status]
            ];

        });



        return $responses;

    }

    public function loadSpin($id, $date_start = null, $date_stop = null)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadSpin($id, $date_start, $date_stop)->toArray());

        $responses = $responses->map(function ($items) {
            $item = (object)$items;
            $status = ['0' => 'รออนุมัติ', '1' => 'อนุมัติ', '2' => 'ยกเลิก'];
            $color = ['0' => 'bg-info', '1' => 'bg-success', '2' => 'bg-danger'];
            return [
                'code' => $item->code,
                'id' => '#BO' . Str::of($item->code)->padLeft(8, 0),
                'date_create' => core()->formatDate($item->date_create, 'd/m/y H:i'),
                'amount' => $item->amount,
                'status' => 1,
                'status_color' => $color[1],
                'status_display' => $status[1]
            ];

        });

        return $responses;

    }
}
