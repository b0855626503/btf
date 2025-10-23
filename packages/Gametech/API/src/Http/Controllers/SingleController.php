<?php

namespace Gametech\API\Http\Controllers;

use Gametech\API\Models\GameLogProxy;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

class SingleController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberRepository;

    protected $gameUserRepository;

    protected $request;

    protected $member;

    //    protected $balance;
    protected $balances;

    protected $game;

    public function __construct(
        BankPaymentRepository $repository,
        MemberRepository $memberRepo,
        GameUserRepository $gameUserRepo,
        Request $request
    ) {
        $this->_config = request('_config');

        $this->middleware('api');

        $this->repository = $repository;

        $this->memberRepository = $memberRepo;

        $this->gameUserRepository = $gameUserRepo;

        $this->request = $request;

        //        $this->member->balance = $this->member->balance;

        $this->balances = 'balance';

        $this->game = 'SINGLE';
    }

    public function getBalance(Request $request)
    {
        $session = $request->all();

        $this->member = MemberProxy::without('bank')->where('user_name', $this->request['username'])->where('enable', 'Y')->first();

        //        $path = storage_path('logs/seamless/Sinsle' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r('-- BET --', true), FILE_APPEND);
        //        file_put_contents($path, print_r($session, true), FILE_APPEND);

        if ($this->member) {

            $param = [

                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'companyKey' => null,
                    'accountName' => $this->member->user_name,
                    'username' => $this->member->user_name,
                    'balance' => (float) $this->member->balance,
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'getbalance';
            $session_in['response'] = 'in';
            $session_in['amount'] = 0;
            $session_in['con_1'] = null;
            $session_in['con_2'] = null;
            $session_in['con_3'] = null;
            $session_in['con_4'] = null;
            $session_in['before_balance'] = $this->member->balance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        } else {
            $param = [

                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Can not get balace',
                ],
            ];
        }

        return $param;
    }

    public function bet(Request $request)
    {
        $param = [];
        $amount = 0;
        $session = $request->all();
        $session['datetime'] = now()->toDateTimeString();
        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- BET --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $this->member = MemberProxy::without('bank')->where('user_name', $this->request['betBy'])->where('enable', 'Y')->first();

        if ($this->member) {

            $oldbalance = $this->member->balance;

            $balance = ($this->member->balance - $session['betAmount']);
            if ($balance < 0) {

                $param = [
                    'status' => 'FAIL',
                    'errors' => [
                        'message' => 'Balance Insufficient',
                    ],
                ];

                return $param;

            }

            $this->member->decrement($this->balances, $session['betAmount']);

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'message' => 'success',
                    'balance' => (float) $this->member->balance,
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'bet';
            $session_in['response'] = 'in';
            $session_in['betAmount'] = $session['betAmount'];
            $session_in['betNo'] = $session['betNo'];
            $session_in['betBy'] = $session['betBy'];
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['chanel'] = $session['chanel'];
            $session_in['game'] = $session['game'];
            $session_in['payout'] = $session['payout'];
            $session_in['betOn'] = $session['betOn'];
            $session_in['invoiceNo'] = $session['invoiceNo'] ?? '';
            $session_in['status'] = '';
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        } else {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'No User',
                ],
            ];

        }

        return $param;
    }

    public function betLotto(Request $request)
    {
        $param = [];
        $amount = 0;

        $session = $request->all();
        $session['datetime'] = now()->toDateTimeString();
        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- BETLOTTO --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $this->member = MemberProxy::without('bank')->where('user_name', $this->request['betBy'])->where('enable', 'Y')->first();

        if ($this->member) {

            $oldbalance = $this->member->balance;

            foreach ($session['betRequestVOs'] as $item) {

                $oldbalance = $this->member->balance;
                //                $data = GameLogProxy::where('company', $this->game)
                //                    ->where('response', 'in')
                //                    ->where('game_user', $this->member->user_name)
                //                    ->where('method', 'bet')
                //                    ->where('betNo', $item['betNo'])
                //                    ->where('game', $session['game'])
                // //                ->whereNull('con_4')
                //                    ->first();
                //
                //                if ($data) {
                //
                //                    $param = [
                //                        'status' => "FAIL",
                //                        'errors' => [
                //                            "message" => "Bet Duplicate"
                //                        ]
                //                    ];
                //
                //                } else {

                $balance = ($this->member->balance - $item['betAmount']);
                if ($balance < 0) {

                    $param = [
                        'status' => 'FAIL',
                        'errors' => [
                            'message' => 'Balance Insufficient',
                        ],
                    ];

                    return $param;

                }

                $this->member->decrement($this->balances, $item['betAmount']);
                //                $this->member->increment($this->balances, $session['betRequestVOs']['payout']);

                $param = [
                    'status' => 'SUCCESS',
                    'errors' => null,
                    'data' => [
                        'message' => 'success',
                        'balance' => (float) $this->member->balance,
                    ],
                ];

                $session_in['input'] = $session;
                $session_in['output'] = $param;
                $session_in['company'] = $this->game;
                $session_in['game_user'] = $this->member->user_name;
                $session_in['method'] = 'bet';
                $session_in['response'] = 'in';
                $session_in['betAmount'] = $item['betAmount'];
                $session_in['betNo'] = $item['betNo'];
                $session_in['betOn'] = $item['betOn'];
                $session_in['betBy'] = $session['betBy'];
                $session_in['invoiceNo'] = $item['invoiceNo'];
                $session_in['chanel'] = $item['chanel'] ?? '';
                $session_in['game'] = $session['game'];
                $session_in['payout'] = $item['payout'];
                $session_in['gameTrnNo'] = $item['gameTrnNo'] ?? $item['betNo'];
                $session_in['status'] = '';
                $session_in['before_balance'] = $oldbalance;
                $session_in['after_balance'] = $this->member->balance;
                $session_in['date_create'] = now()->toDateTimeString();
                $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                GameLogProxy::create($session_in);

                //                }
            }

        } else {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'No User',
                ],
            ];

        }

        return $param;
    }

    public function setBetResult(Request $request)
    {
        $param = [];
        $amount = 0;
        $sumamount = 0;
        $sumjackpot = 0;
        $jackpot = 0;
        $remark = '';
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- SETTLE --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $datas = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('method', 'bet')
            ->where('gameTrnNo', $session['gameTrnNo'])
            ->where('game', $session['game'])
            ->get();

        if ($datas->isEmpty()) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Bet Transaction',
                ],
            ];

        } else {

            $this->member = MemberProxy::without('bank')->where('user_name', $datas[0]['betBy'])->where('enable', 'Y')->first();

            $oldbalance = $this->member->balance;
            foreach ($datas as $data) {

                $amount = 0;
                $jackpot = 0;
                $oldbalancesub = $this->member->balance;

                if ($session['game'] === 'GAME_COCK') {

                    if (in_array('R', $session['betResults'])) {

                        if ($data['betOn'] === 'R') {
                            $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                            $this->member->increment($this->balances, $amount);
                            $remark .= ' WIN R';
                            $sumamount += $amount;
                        }

                    } elseif (in_array('B', $session['betResults'])) {

                        if ($data['betOn'] === 'B') {
                            $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                            $this->member->increment($this->balances, $amount);
                            $remark .= ' WIN B';
                            $sumamount += $amount;
                        }

                    } elseif (in_array('C', $session['betResults'])) {

                        $amount = $data['betAmount'];
                        $this->member->increment($this->balances, $amount);
                        $remark .= ' CANCEL';
                        $sumamount += $amount;

                    } elseif (in_array('D', $session['betResults'])) {

                        if ($data['betOn'] === 'D') {
                            $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                            $this->member->increment($this->balances, $amount);
                            $remark .= ' WIN DRAW';
                            $sumamount += $amount;
                        } else {
                            $amount = $data['betAmount'];
                            $this->member->increment($this->balances, $amount);
                            $remark .= ' DRAW RETURN BET';
                            $sumamount += $amount;
                        }

                    }

                } elseif ($session['game'] === 'GAME_BAR') {

                    if (in_array('CANCEL', $session['betResults'])) {

                        $amount = $data['betAmount'];
                        $this->member->increment($this->balances, $amount);

                        $remark .= ' CANCEL';
                        $sumamount += $amount;

                    } elseif (in_array('TIE', $session['betResults'])) {

                        if ($data['betOn'] === 'TIE') {

                            $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                            $this->member->increment($this->balances, $amount);

                            $remark .= ' TIE WIN';
                            $sumamount += $amount;

                        } else {

                            $amount = $data['betAmount'];
                            $this->member->increment($this->balances, $amount);

                            $remark .= ' TIE RETURN BET';
                            $sumamount += $amount;

                        }

                    } elseif (in_array($data['betOn'], $session['betResults'])) {

                        $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                        $this->member->increment($this->balances, $amount);
                        $remark .= ' WIN';
                        $sumamount += $amount;

                    }

                } elseif ($session['game'] === 'GAME_LOTTO888') {

                    if (in_array($data['betOn'], $session['betResults'])) {
                        $amount = ($data['betAmount'] * $data['payout']);
                        $this->member->increment($this->balances, $amount);
                        $remark .= ' WIN';
                        $sumamount += $amount;
                    }

                    if ($session['jackpotResult'] == $data['betOn']) {

                        $jackpot = ($data['betAmount'] * $data['payout'] * $session['jackpotMultiple']);
                        $this->member->increment($this->balances, $jackpot);
                        $remark .= ' JACKPOT';
                        $sumjackpot += $jackpot;

                    }

                }

                if ($amount > 0 || $jackpot > 0) {

                    $param = [];

                    $session_in['input'] = $session;
                    $session_in['output'] = $param;
                    $session_in['company'] = $this->game;
                    $session_in['game_user'] = $this->member->user_name;
                    $session_in['method'] = 'settlesub';
                    $session_in['response'] = 'in';
                    $session_in['amount'] = $amount;
                    $session_in['jackpotAmount'] = $jackpot;
                    $session_in['total'] = ($amount + $jackpot);
                    $session_in['betResults'] = $session['betResults'];
                    $session_in['betOn'] = $data['betOn'];
                    $session_in['betBy'] = $data['betBy'];
                    $session_in['betAmount'] = $data['betAmount'];
                    $session_in['payout'] = $data['payout'];
                    $session_in['chanel'] = $data['chanel'];
                    $session_in['gameTrnNo'] = $session['gameTrnNo'];
                    $session_in['game'] = $session['game'];
                    $session_in['jackpotResult'] = $session['jackpotResult'];
                    $session_in['jackpotMultiple'] = $session['jackpotMultiple'];
                    $session_in['status'] = '';
                    $session_in['remark'] = $remark;
                    $session_in['before_balance'] = $oldbalancesub;
                    $session_in['after_balance'] = $this->member->balance;
                    $session_in['date_create'] = now()->toDateTimeString();
                    $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                    GameLogProxy::create($session_in);
                }

            }

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'betResults' => $session['betResults'],
                    'chanel' => $session['chanel'],
                    'companyKey' => $session['companyKey'],
                    'game' => $session['game'],
                    'gameTrnNo' => $session['gameTrnNo'],
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'settle';
            $session_in['response'] = 'in';
            $session_in['amount'] = $sumamount;
            $session_in['jackpotAmount'] = $sumjackpot;
            $session_in['total'] = ($sumamount + $sumjackpot);
            $session_in['betResults'] = $session['betResults'];
            $session_in['betOn'] = $data['betOn'];
            $session_in['betBy'] = $data['betBy'];
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['game'] = $session['game'];
            $session_in['jackpotResult'] = $session['jackpotResult'];
            $session_in['jackpotMultiple'] = $session['jackpotMultiple'];
            $session_in['status'] = '';
            $session_in['remark'] = $remark;
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }

    public function setBetResultLotto90(Request $request)
    {
        $param = [];
        $bets = [];
        $bet = '';
        $amount = 0;
        $sumamount = 0;
        $jackpot = 0;
        $special = '';
        $specialXl = '';
        $remark = '';
        $oldbalancesub = 0;
        $oldbalance = 0;
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- SETTLE 90 --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $datas = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('method', 'bet')
            ->where('gameTrnNo', $session['gameTrnNo'])
            ->where('game', $session['game'])
            ->get();

        if ($datas->isEmpty()) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Bet Transaction',
                ],
            ];

        } else {

            $specials = json_decode($session['specials'], true);
            $specialXs = json_decode($session['specialXs'], true);

            $this->member = MemberProxy::without('bank')->where('user_name', $datas[0]['betBy'])->where('enable', 'Y')->first();

            $oldbalance = $this->member->balance;

            foreach ($datas as $data) {

                $amount = 0;
                $remark = '';
                $bet = '';
                $oldbalancesub = $this->member->balance;
                if (isset($specials[$data['chanel']]) && $specials[$data['chanel']] == $data['betOn']) {
                    $bet = $data['chanel'].'-'.$specials[$data['chanel']];
                    $amount = ($data['betAmount'] * $data['payout']);
                    $this->member->increment($this->balances, $amount);
                    $remark = ' WIN IN Specials';
                    $bets[] = $data['betOn'];
                    $sumamount += $amount;
                }

                if (in_array($data['betOn'], $specialXs)) {
                    $bet = $data['betOn'];
                    $amount = ($data['betAmount'] * $data['payout']);
                    $this->member->increment($this->balances, $amount);
                    $remark = ' WIN IN SpecialXl';
                    $bets[] = $data['betOn'];
                    $sumamount += $amount;
                }

                if ($amount > 0) {
                    $param = [
                        'specials' => $specials,
                        'specialXs' => $specialXs,
                    ];

                    $session_in['input'] = $session;
                    $session_in['output'] = $param;
                    $session_in['company'] = $this->game;
                    $session_in['game_user'] = $this->member->user_name;
                    $session_in['method'] = 'settlesub';
                    $session_in['response'] = 'in';
                    $session_in['amount'] = $amount;
                    $session_in['jackpotAmount'] = $jackpot;
                    $session_in['total'] = ($amount + $jackpot);
                    $session_in['betResults'] = $bet;
                    $session_in['gameTrnNo'] = $session['gameTrnNo'];
                    $session_in['betNo'] = $session['betNo'];
                    $session_in['chanel'] = $data['chanel'];
                    $session_in['betOn'] = $data['betOn'];
                    $session_in['betBy'] = $data['betBy'];
                    $session_in['betAmount'] = $data['betAmount'];
                    $session_in['payout'] = $data['payout'];
                    $session_in['game'] = $session['game'];
                    $session_in['specials'] = $specials;
                    $session_in['specialXs'] = $specialXs;
                    $session_in['status'] = '';
                    $session_in['remark'] = $remark;
                    $session_in['before_balance'] = $oldbalancesub;
                    $session_in['after_balance'] = $this->member->balance;
                    $session_in['date_create'] = now()->toDateTimeString();
                    $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                    GameLogProxy::create($session_in);
                }

            }

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'betResults' => $bets,
                    'chanel' => $datas[0]['chanel'],
                    'companyKey' => $session['companyKey'],
                    'game' => $session['game'],
                    'gameTrnNo' => $session['gameTrnNo'],
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'settle';
            $session_in['response'] = 'in';
            $session_in['amount'] = $sumamount;
            $session_in['jackpotAmount'] = $jackpot;
            $session_in['total'] = ($amount + $jackpot);
            $session_in['betResults'] = $bets;
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['betNo'] = $session['betNo'];
            $session_in['betBy'] = $datas[0]['betBy'];
            $session_in['game'] = $session['game'];
            $session_in['specials'] = $special;
            $session_in['specialXs'] = $specialXl;
            $session_in['status'] = '';
            $session_in['remark'] = $remark;
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }

    public function setBetResultLotto12(Request $request)
    {
        $param = [];
        $bets = [];
        $bet = '';
        $amount = 0;
        $sumamount = 0;
        $jackpot = 0;
        $special = '';
        $specialXl = '';
        $remark = '';
        $oldbalancesub = 0;
        $oldbalance = 0;
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- SETTLE 12 --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $datas = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('method', 'bet')
            ->where('gameTrnNo', $session['tranGameNo'])
            ->where('game', $session['game'])
            ->get();

        if ($datas->isEmpty()) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Bet Transaction',
                ],
            ];

        } else {

            $result = json_decode($session['result'], true);

            $this->member = MemberProxy::without('bank')->where('user_name', $datas[0]['betBy'])->where('enable', 'Y')->first();

            $oldbalance = $this->member->balance;

            foreach ($datas as $data) {

                $amount = 0;
                $remark = '';
                $bet = '';
                $oldbalancesub = $this->member->balance;

                if (isset($result['oddEvent']) && $result['oddEvent'] == $data['betOn']) {
                    $bet = $data['betOn'];
                    $amount = ($data['betAmount'] * $data['payout']);
                    $this->member->increment($this->balances, $amount);
                    $remark = ' WIN IN Odd Even';
                    $bets[] = $data['betOn'];
                    $sumamount += $amount;
                }

                if (isset($result['range']) && $result['range'] == $data['betOn']) {
                    $bet = $data['betOn'];
                    $amount = ($data['betAmount'] * $data['payout']);
                    $this->member->increment($this->balances, $amount);
                    $remark = ' WIN IN Range';
                    $bets[] = $data['betOn'];
                    $sumamount += $amount;
                }

                if (isset($result['smallBig']) && $result['smallBig'] == $data['betOn']) {
                    $bet = $data['betOn'];
                    $amount = ($data['betAmount'] * $data['payout']);
                    $this->member->increment($this->balances, $amount);
                    $remark = ' WIN IN Small Big';
                    $bets[] = $data['betOn'];
                    $sumamount += $amount;
                }

                if (isset($result['jackpotResult']) && $result['jackpotResult'] == $data['betOn']) {

                    $jackpot = ($data['betAmount'] * $data['payout'] * $result['numJackpotMultiple']);
                    $this->member->increment($this->balances, $jackpot);
                    $remark .= ' JACKPOT';
                    $sumjackpot += $jackpot;

                }

                if ($amount > 0) {
                    $param = [];

                    $session_in['input'] = $session;
                    $session_in['output'] = $param;
                    $session_in['company'] = $this->game;
                    $session_in['game_user'] = $this->member->user_name;
                    $session_in['method'] = 'settlesub';
                    $session_in['response'] = 'in';
                    $session_in['amount'] = $amount;
                    $session_in['jackpotAmount'] = $jackpot;
                    $session_in['total'] = ($amount + $jackpot);
                    $session_in['betResults'] = $bet;
                    $session_in['gameTrnNo'] = $session['tranGameNo'];
                    $session_in['betNo'] = $session['tranGameNo'];
                    $session_in['chanel'] = $data['chanel'] ?? '';
                    $session_in['betOn'] = $data['betOn'];
                    $session_in['betBy'] = $data['betBy'];
                    $session_in['betAmount'] = $data['betAmount'];
                    $session_in['payout'] = $data['payout'];
                    $session_in['game'] = $session['game'];
                    $session_in['status'] = '';
                    $session_in['remark'] = $remark;
                    $session_in['before_balance'] = $oldbalancesub;
                    $session_in['after_balance'] = $this->member->balance;
                    $session_in['date_create'] = now()->toDateTimeString();
                    $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                    GameLogProxy::create($session_in);
                }

            }

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'betResults' => $bets,
                    'chanel' => '',
                    'companyKey' => $session['companyKey'],
                    'game' => $session['game'],
                    'gameTrnNo' => $session['tranGameNo'],
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'settle';
            $session_in['response'] = 'in';
            $session_in['amount'] = $sumamount;
            $session_in['jackpotAmount'] = $jackpot;
            $session_in['total'] = ($amount + $jackpot);
            $session_in['betResults'] = $bets;
            $session_in['gameTrnNo'] = $session['tranGameNo'];
            $session_in['betNo'] = $session['tranGameNo'];
            $session_in['betBy'] = $datas[0]['betBy'];
            $session_in['game'] = $session['game'];
            $session_in['specials'] = $special;
            $session_in['specialXs'] = $specialXl;
            $session_in['status'] = '';
            $session_in['remark'] = $remark;
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }

    public function cancelRound(Request $request)
    {
        $param = [];
        $amount = 0;
        $jackpot = 0;
        $special = '';
        $specialXl = '';
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- CANCEL ROUND --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $data = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('game_user', $this->member->user_name)
            ->where('method', 'settle')
            ->where('chanel', $session['chanel'])
            ->where('gameTrnNo', $session['gameTrnNo'])
            ->where('game', $session['game'])
            ->first();

        if (! $data) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Settle Transaction',
                ],
            ];

        } else {

            $this->member = MemberProxy::without('bank')->where('user_name', $data['betBy'])->where('enable', 'Y')->first();
            $oldbalance = $this->member->balance;

            if ($data['status'] == '') {

                $data->status = 'CANCELED';
                $data->save();

                $this->member->decrement($this->balances, $data['total']);

            }

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'chanel' => $session['chanel'],
                    'companyKey' => $session['companyKey'],
                    'game' => $session['game'],
                    'gameTrnNo' => $session['gameTrnNo'],
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'cancel';
            $session_in['response'] = 'in';
            $session_in['betBy'] = $data['betBy'];
            $session_in['total'] = $data['total'];
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['chanel'] = $session['chanel'];
            $session_in['game'] = $session['game'];
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }

    public function cancelBet(Request $request)
    {
        $param = [];
        $amount = 0;
        $jackpot = 0;
        $special = '';
        $specialXl = '';
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- CANCEL BET --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $data = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('method', 'bet')
            ->where('chanel', $session['chanel'])
            ->where('betNo', $session['betNo'])
            ->where('invoiceNo', $session['invoiceNo'])
            ->where('game', $session['game'])
            ->first();

        if (! $data) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Settle Transaction',
                ],
            ];

        } else {

            $this->member = MemberProxy::without('bank')->where('user_name', $data['betBy'])->where('enable', 'Y')->first();
            $oldbalance = $this->member->balance;

            if ($data['status'] == '') {

                $data->status = 'CANCELED';
                $data->save();

                $this->member->increment($this->balances, $data['betAmount']);

                $param = [
                    'status' => 'SUCCESS',
                    'errors' => null,
                    'data' => [
                        'gameTrnNo' => $session['gameTrnNo'],
                        'companyKey' => $session['companyKey'],
                        'game' => $session['game'],
                        'betNo' => $session['betNo'],
                        'responseTime' => now()->format('Y-m-d H:i:s.').now()->millisecond,
                        'code' => 0,
                        'message' => 'Success',
                    ],
                ];

            } elseif ($data['status'] == 'CANCELED') {

                $param = [
                    'status' => 'SUCCESS',
                    'errors' => null,
                    'data' => [
                        'gameTrnNo' => $session['gameTrnNo'],
                        'companyKey' => $session['companyKey'],
                        'game' => $session['game'],
                        'betNo' => $session['betNo'],
                        'responseTime' => now()->format('Y-m-d H:i:s.').now()->millisecond,
                        'code' => 1,
                        'message' => 'Bet transaction already CANCELED',
                    ],
                ];

            }

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'cancel';
            $session_in['response'] = 'in';
            $session_in['total'] = $data['betAmount'];
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['chanel'] = $session['chanel'];
            $session_in['game'] = $session['game'];
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }

    public function resetResult(Request $request)
    {
        $param = [];
        $amount = 0;
        $jackpot = 0;
        $special = '';
        $specialXl = '';
        $session = $request->all();

        $path = storage_path('logs/seamless/Sinsle'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- RESET --', true), FILE_APPEND);
        file_put_contents($path, print_r($session, true), FILE_APPEND);

        $data = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('method', 'settle')
            ->where('chanel', $session['chanel'])
            ->where('gameTrnNo', $session['gameTrnNo'])
            ->where('game', $session['game'])
            ->where('status', '')
            ->first();

        if (! $data) {

            $param = [
                'status' => 'FAIL',
                'errors' => [
                    'message' => 'Not Found Settle Transaction',
                ],
            ];

        } else {

            $this->member = MemberProxy::without('bank')->where('user_name', $data['betBy'])->where('enable', 'Y')->first();
            $oldbalance = $this->member->balance;

            $data->status = 'CANCELED';
            $data->save();

            $this->member->decrement($this->balances, $data['total']);

            if (in_array('R', $session['betResults'])) {

                if ($data['betOn'] === 'R') {
                    $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                    $this->member->increment($this->balances, $amount);
                }

            } elseif (in_array('B', $session['betResults'])) {

                if ($data['betOn'] === 'B') {
                    $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                    $this->member->increment($this->balances, $amount);
                }

            } elseif (in_array('C', $session['betResults'])) {

                $amount = $data['betAmount'];
                $this->member->increment($this->balances, $amount);

            } elseif (in_array('D', $session['betResults'])) {

                if ($data['betOn'] === 'D') {
                    $amount = ($data['betAmount'] * $data['payout']) + $data['betAmount'];
                    $this->member->increment($this->balances, $amount);
                } else {
                    $amount = $data['betAmount'];
                    $this->member->increment($this->balances, $amount);
                }

            }

            $param = [
                'status' => 'SUCCESS',
                'errors' => null,
                'data' => [
                    'betResults' => $session['betResults'],
                    'chanel' => $session['chanel'],
                    'companyKey' => $session['companyKey'],
                    'game' => $session['game'],
                    'gameTrnNo' => $session['gameTrnNo'],
                ],
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'settle';
            $session_in['response'] = 'in';
            $session_in['amount'] = $amount;
            $session_in['jackpotAmount'] = $jackpot;
            $session_in['total'] = ($amount + $jackpot);
            $session_in['betResults'] = $session['betResults'];
            $session_in['betOn'] = $data['betOn'];
            $session_in['gameTrnNo'] = $session['gameTrnNo'];
            $session_in['game'] = $session['game'];
            $session_in['jackpotResult'] = $data['jackpotResult'];
            $session_in['jackpotMultiple'] = $data['jackpotMultiple'];
            $session_in['status'] = '';
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

        }

        return $param;
    }
}
