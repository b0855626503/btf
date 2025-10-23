<?php

namespace Gametech\API\Http\Controllers;

use Gametech\API\Models\GameLogProxy;
use Gametech\API\Traits\LogSeamless;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

class BlazeGamingController extends AppBaseController
{
    use LogSeamless;

    protected $_config;

    protected $repository;

    protected $memberRepository;

    protected $gameUserRepository;

    protected $request;

    protected $member;

    //    protected $balance;
    protected $balances;

    protected $game = 'BLAZEGAMING';

    protected $method = 'game';

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

        $this->member = MemberProxy::without('bank')->where('code', $this->request['playerId'])->where('enable', 'Y')->first();

        $this->apiId = config($this->method.'.blaze.apiId');

        $this->apiKey = config($this->method.'.blaze.apiKey');

        //        $this->member->balance = $this->member->balance;

        $this->balances = 'balance';

    }

    public function getBalance(Request $request)
    {
        $session = $request->all();

        $hashKey = $this->hasHKey($session['sessionKey'], $session['hashKey']);
        if ($hashKey) {

            if ($this->member) {

                $param = [
                    'apiId' => $session['apiId'],
                    'apiKey' => $session['apiKey'],
                    'sessionKey' => $session['sessionKey'],
                    'hashKey' => $session['hashKey'],
                    'balance' => (float) $this->member->balance,
                    'currency' => 'MMK',
                    'timeStamp' => now('UTC')->format('Y-m-d\TH:i:s'),
                    'errorDetails' => null,
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
                    'errorDetails' => [
                        'errorCode' => 13103,
                        'errorMsg' => 'Invalid hash key',
                    ],
                ];
            }
        } else {
            $param = [
                'errorDetails' => [
                    'errorCode' => 13103,
                    'errorMsg' => 'Invalid hash key',
                ],
            ];
        }

        if (isset($param['errorDetails'])) {
            $param['apiId'] = $session['apiId'];
            $param['apiKey'] = $session['apiKey'];
            $param['sessionKey'] = $session['sessionKey'];
            $param['hashKey'] = $session['hashKey'];
            $param['timeStamp'] = now('UTC')->format('Y-m-d\TH:i:s');
        }

        return response()->json($param);
    }

    public function hasHKey($sessionKey, $hashKey)
    {
        $getHash = md5($this->apiId.'-'.$this->apiKey.'-'.$sessionKey);
        if ($getHash !== $hashKey) {
            return false;
        }

        return true;
    }

    public function transferOut(Request $request)
    {
        $param = [];
        $amount = 0;
        $session = $request->all();

        if ($this->member) {

            $oldbalance = $this->member->balance;

            if ($session['tip'] === false) {
                $data = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'OPEN')
                    ->where('con_1', $session['txnId'])
                    ->where('con_2', $session['referenceNumber'])
                    ->whereNull('con_4')
                    ->first();
            } else {
                $data = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'TIPS')
                    ->where('con_1', $session['txnId'])
                    ->where('con_2', $session['referenceNumber'])
                    ->whereNull('con_4')
                    ->first();
            }

            if ($data) {

                $param = [
                    'errorDetails' => [
                        'errorCode' => 11204,
                        'errorMsg' => 'Transaction already exist',
                    ],
                ];

            } else {

                if ($session['buyInAmount'] < 0) {
                    $param = [
                        'errorDetails' => [
                            'errorCode' => 11124,
                            'errorMsg' => 'Invalid parameter',
                        ],
                    ];
                } else {

                    $balance = ($this->member->balance - $session['buyInAmount']);
                    if ($balance < 0) {

                        $param = [
                            'errorDetails' => [
                                'errorCode' => 11150,
                                'errorMsg' => 'Insufficient fund',
                            ],
                        ];

                        return response()->json($param);

                    }

                    $this->member->decrement($this->balances, $session['buyInAmount']);

                    $param = [
                        'apiId' => $session['apiId'],
                        'apiKey' => $session['apiKey'],
                        'sessionKey' => $session['sessionKey'],
                        'hashKey' => $session['hashKey'],
                        'balance' => (float) $this->member->balance,
                        'referenceNumber' => $session['referenceNumber'],
                        'txnId' => $session['txnId'],
                        'timeStamp' => now('UTC')->format('Y-m-d\TH:i:s'),
                        'errorDetails' => null,
                    ];

                    $session_in['input'] = $session;
                    $session_in['output'] = $param;
                    $session_in['company'] = $this->game;
                    $session_in['game_user'] = $this->member->user_name;
                    $session_in['method'] = ($session['tip'] === false ? 'OPEN' : 'TIPS');
                    $session_in['response'] = 'in';
                    $session_in['amount'] = $session['buyInAmount'];
                    $session_in['con_1'] = $session['txnId'];
                    $session_in['con_2'] = $session['referenceNumber'];
                    $session_in['con_3'] = null;
                    $session_in['con_4'] = null;
                    $session_in['before_balance'] = $oldbalance;
                    $session_in['after_balance'] = $this->member->balance;
                    $session_in['date_create'] = now()->toDateTimeString();
                    $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                    GameLogProxy::create($session_in);

                    $newparam = [
                        'playInfo' => $session['gameId'],
                        'betAmount' => $session['buyInAmount'],
                        'status' => ($session['tip'] === false ? 'OPEN' : 'TIPS'),
                        'id' => $session['txnId'],
                        'roundId' => $session['referenceNumber'],
                    ];
                    LogSeamless::log($this->game, $this->member->user_name, $newparam, $oldbalance, $this->member->balance);
                }
            }

        } else {

            $param = [
                'errorDetails' => [
                    'errorCode' => 13103,
                    'errorMsg' => 'Invalid hash key',
                ],
            ];

        }

        return response()->json($param);
    }

    public function transferIn(Request $request)
    {
        $param = [];
        $amount = 0;
        $session = $request->all();

        $path = storage_path('logs/seamless/blaze'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r('-- SETTLE --', true), FILE_APPEND);

        file_put_contents($path, print_r($session, true), FILE_APPEND);

        if ($this->member) {

            $oldbalance = $this->member->balance;

            $data = GameLogProxy::where('company', $this->game)
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'OPEN')
//                ->where('con_1', $session['txnId'])
                ->where('con_2', $session['referenceNumber'])
                ->first();

            if (! $data) {

                $param = [
                    'errorDetails' => [
                        'errorCode' => 12201,
                        'errorMsg' => 'Bet transaction not exists',
                    ],
                ];

                return response()->json($param);

            }

            //            $data = GameLogProxy::where('company', $this->game)
            //                ->where('response', 'in')
            //                ->where('game_user', $this->member->user_name)
            //                ->where('method', 'SETTLED')
            //                ->where('con_1', $session['txnId'])
            //                ->where('con_2', $session['referenceNumber'])
            //                ->whereNull('con_3')
            //                ->whereNull('con_4')
            //                ->first();
            //
            //            if ($data) {
            //
            //                $param = [
            //                    'errorDetails' => [
            //                        'errorCode' => 11204,
            //                        'errorMsg' => 'Transaction already exist',
            //                    ],
            //                ];
            //
            //            } else {

            if ($session['buyOutAmount'] < 0) {
                $param = [
                    'errorDetails' => [
                        'errorCode' => 12124,
                        'errorMsg' => 'Invalid parameter',
                    ],
                ];

            } else {

                $this->member->increment($this->balances, $session['buyOutAmount']);

                $param = [
                    'apiId' => $session['apiId'],
                    'apiKey' => $session['apiKey'],
                    'sessionKey' => $session['sessionKey'],
                    'hashKey' => $session['hashKey'],
                    'balance' => (float) $this->member->balance,
                    'referenceNumber' => $session['referenceNumber'],
                    'txnId' => $session['txnId'],
                    'timeStamp' => now('UTC')->format('Y-m-d\TH:i:s'),
                    'errorDetails' => null,
                ];

                $session_in['input'] = $session;
                $session_in['output'] = $param;
                $session_in['company'] = $this->game;
                $session_in['game_user'] = $this->member->user_name;
                $session_in['method'] = 'SETTLED';
                $session_in['response'] = 'in';
                $session_in['amount'] = $session['buyOutAmount'];
                $session_in['con_1'] = $session['txnId'];
                $session_in['con_2'] = $session['referenceNumber'];
                $session_in['con_3'] = null;
                $session_in['con_4'] = null;
                $session_in['before_balance'] = $oldbalance;
                $session_in['after_balance'] = $this->member->balance;
                $session_in['date_create'] = now()->toDateTimeString();
                $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
                GameLogProxy::create($session_in);

                $newparam = [
                    'playInfo' => $session['gameId'],
                    'payoutAmount' => $session['buyOutAmount'],
                    'status' => 'SETTLED',
                    'id' => $session['txnId'],
                    'roundId' => $session['referenceNumber'],
                    'transactionType' => 'BY_ROUND',
                    'isSingleState' => false,
                ];
                LogSeamless::log($this->game, $this->member->user_name, $newparam, $oldbalance, $this->member->balance);
            }
            //            }

        } else {

            $param = [
                'errorDetails' => [
                    'errorCode' => 12124,
                    'errorMsg' => 'Invalid parameter',
                ],
            ];

        }

        return response()->json($param);
    }

    public function rollback(Request $request)
    {
        $param = [];
        $amount = 0;
        $session = $request->all();

        if ($this->member) {

            $oldbalance = $this->member->balance;

            $txnType = $session['txnType'];

            if ($txnType === 'BUYIN') {

                $checkDup = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'REFUND')
                    ->where('con_1', $session['txnId'])
                    ->where('con_2', $session['referenceNumber'])
                    ->whereNull('con_3')
                    ->whereNull('con_4')
                    ->first();

                if ($checkDup) {

                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16117,
                            'errorMsg' => 'Transaction already rollback',
                        ],
                    ];

                    return response()->json($param);
                }

                $data = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'OPEN')
                    ->where('con_1', $session['referenceTransactionId'])
                    ->first();

                if (! $data) {
                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16201,
                            'errorMsg' => 'Bet transaction not exists',
                        ],
                    ];

                    return response()->json($param);
                }

            } else {

                $checkDup = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'ROLLBACK')
                    ->where('con_1', $session['txnId'])
                    ->where('con_2', $session['referenceNumber'])
                    ->whereNull('con_3')
                    ->whereNull('con_4')
                    ->first();

                if ($checkDup) {

                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16117,
                            'errorMsg' => 'Transaction already rollback',
                        ],
                    ];

                    return response()->json($param);
                }

                $data = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'SETTLED')
                    ->where('con_1', $session['referenceTransactionId'])
                    ->first();

                if (! $data) {
                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16116,
                            'errorMsg' => 'Transaction not found',
                        ],
                    ];

                    return response()->json($param);
                }
            }

            if ($txnType === 'BUYIN') {

                if ($data['amount'] == $session['amount']) {
                    $this->member->increment($this->balances, $session['amount']);
                } else {
                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16118,
                            'errorMsg' => 'Rollback amount mismatched',
                        ],
                    ];

                    return response()->json($param);
                }

            } else {

                $balance = ($this->member->balance - $session['amount']);
                if ($balance < 0) {

                    $param = [
                        'errorDetails' => [
                            'errorCode' => 11150,
                            'errorMsg' => 'Insufficient fund',
                        ],
                    ];

                    return response()->json($param);

                }

                if ($data['amount'] == $session['amount']) {
                    $this->member->decrement($this->balances, $session['amount']);
                } else {
                    $param = [
                        'errorDetails' => [
                            'errorCode' => 16118,
                            'errorMsg' => 'Rollback amount mismatched',
                        ],
                    ];

                    return response()->json($param);
                }
            }

            $param = [
                'apiId' => $session['apiId'],
                'apiKey' => $session['apiKey'],
                'sessionKey' => $session['sessionKey'],
                'hashKey' => $session['hashKey'],
                'balance' => (float) $this->member->balance,
                'referenceNumber' => $session['referenceNumber'],
                'txnId' => $session['txnId'],
                'timeStamp' => now('UTC')->format('Y-m-d\TH:i:s'),
                'errorDetails' => null,
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = ($txnType === 'BUYIN' ? 'REFUND' : 'ROLLBACK');
            $session_in['response'] = 'in';
            $session_in['amount'] = $session['amount'];
            $session_in['con_1'] = $session['txnId'];
            $session_in['con_2'] = $session['referenceNumber'];
            $session_in['con_3'] = $session['referenceTransactionId'];
            $session_in['con_4'] = null;
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

            if ($data['method'] == 'OPEN') {
                $newparam = [
                    'playInfo' => $session['gameId'],
                    'payoutAmount' => $session['amount'],
                    'status' => 'REFUND',
                    'id' => $session['txnId'],
                    'roundId' => $session['referenceNumber'],
                    'transactionType' => 'BY_TRANSACTION',
                    'isSingleState' => false,
                ];
            } else {
                $newparam = [
                    'playInfo' => $session['gameId'],
                    'betAmount' => $session['amount'],
                    'status' => 'ROLLBACK',
                    'id' => $session['txnId'],
                    'roundId' => $session['referenceNumber'],
                    'transactionType' => 'BY_TRANSACTION',
                    'isSingleState' => false,
                ];

            }

            LogSeamless::log($this->game, $this->member->user_name, $newparam, $oldbalance, $this->member->balance);

        } else {

            $param = [
                'errorDetails' => [
                    'errorCode' => 13103,
                    'errorMsg' => 'Invalid hash key',
                ],
            ];

        }

        return response()->json($param);
    }

    public function winReward(Request $request)
    {
        $param = [];
        $amount = 0;
        $session = $request->all();

        if ($this->member) {

            $oldbalance = $this->member->balance;

            $checkDup = GameLogProxy::where('company', $this->game)
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'WIN')
                ->where('con_1', $session['txnId'])
                ->where('con_2', $session['referenceNumber'])
                ->first();

            if ($checkDup) {

                $param = [
                    'errorDetails' => [
                        'errorCode' => 11204,
                        'errorMsg' => 'Transaction already exist',
                    ],
                ];

                return response()->json($param);
            }

            $this->member->increment($this->balances, $session['amount']);

            $param = [
                'apiId' => $session['apiId'],
                'apiKey' => $session['apiKey'],
                'sessionKey' => $session['sessionKey'],
                'hashKey' => $session['hashKey'],
                'balance' => (float) $this->member->balance,
                'referenceNumber' => $session['referenceNumber'],
                'txnId' => $session['txnId'],
                'timeStamp' => now('UTC')->format('Y-m-d\TH:i:s'),
                'errorDetails' => null,
            ];

            $session_in['input'] = $session;
            $session_in['output'] = $param;
            $session_in['company'] = $this->game;
            $session_in['game_user'] = $this->member->user_name;
            $session_in['method'] = 'WIN';
            $session_in['response'] = 'in';
            $session_in['amount'] = $session['amount'];
            $session_in['con_1'] = $session['txnId'];
            $session_in['con_2'] = $session['referenceNumber'];
            $session_in['con_3'] = $session['rewardType'];
            $session_in['con_4'] = $session['rewardTitle'];
            $session_in['before_balance'] = $oldbalance;
            $session_in['after_balance'] = $this->member->balance;
            $session_in['date_create'] = now()->toDateTimeString();
            $session_in['expireAt'] = new UTCDateTime(now()->addDays(2));
            GameLogProxy::create($session_in);

            $newparam = [
                'playInfo' => $session['gameId'],
                'payoutAmount' => $session['amount'],
                'status' => 'WINREWARD',
                'id' => $session['txnId'],
                'roundId' => $session['referenceNumber'],
                'transactionType' => 'BY_ROUND',
                'isSingleState' => false,
            ];
            LogSeamless::log($this->game, $this->member->user_name, $newparam, $oldbalance, $this->member->balance);

        } else {
            $param = [
                'errorDetails' => [
                    'errorCode' => 13103,
                    'errorMsg' => 'Invalid hash key',
                ],
            ];
        }

        return response()->json($param);
    }
}
