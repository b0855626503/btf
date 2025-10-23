<?php

namespace Gametech\API\Http\Controllers;

use Gametech\API\Models\GameLogProxy;
use Gametech\API\Traits\LogSeamless;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class NewCommonController extends AppBaseController
{
    use LogSeamless;

    protected $_config;

    protected $repository;

    protected $memberRepository;

    protected $gameUserRepository;

    protected $request;

    protected $member;

    protected $balances = 'balance';

    protected $game = 'ACE333';

    protected $days = 3;

    protected $now;

    protected $expireAt;

    public function __construct(
        BankPaymentRepository $repository,
        MemberRepository      $memberRepo,
        GameUserRepository    $gameUserRepo,
        Request               $request
    )
    {
        $this->_config = $request->input('_config');
        $this->middleware('api');
        $this->repository = $repository;
        $this->memberRepository = $memberRepo;
        $this->gameUserRepository = $gameUserRepo;
        $this->request = $request;

        $this->now = now();

        // 🔎 ตรวจสอบค่า session['productId']
        $productId = session('productId'); // หรือจะใช้ $request->session()->get('productId')
        if (in_array($productId, ['UMBET', 'LALIKA', 'AFB1188', 'VIRTUAL_SPORT', 'COCKFIGHT', 'AMBSPORTBOOK', 'SABASPORTS', 'SBO', 'AOG', 'FB_SPORT', 'DB SPORTS'])) {
            $this->days = 7;
        }

        $this->expireAt = new UTCDateTime($this->now->copy()->addDays($this->days));

        $username = $request->input('username');
        $token = $request->input('token', $request->input('sessionToken'));

        $query = MemberProxy::without('bank')->where('user_name', $username)->where('enable', 'Y');
        if ($token) {
            $query->where('session_id', $token);
        }

        $this->member = $query->first();
    }

    protected function responseData($id, $username, $productId, $statusCode, $balance = 0)
    {
        return [
            'id' => $id,
            'statusCode' => $statusCode,
            'balance' => (float)$balance,
            'productId' => $productId,
            'currency' => 'THB',
            'username' => $username,
            'timestampMillis' => $this->now->getTimestampMs(),
        ];
    }

    protected function createGameLog(array $data)
    {
        return GameLogProxy::create($data);
    }

    public function getBalance(Request $request)
    {
        $session = $request->all();

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 30001);
        }

        $param = $this->responseData(
            $session['id'],
            $this->member->user_name,
            $session['productId'],
            0,
            $this->member->balance
        );

        $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'getbalance',
            'response' => 'in',
            'amount' => 0,
            'con_1' => null,
            'con_2' => null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $this->member->balance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        return $param;
    }

    public function placeBets(Request $request)
    {
        $session  = $request->all();
        $param    = [];
        $timedOut = false;

        $txns = (array) ($session['txns'] ?? []);

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $oldBalance = $this->member->balance;
        $amount     = collect($txns)->sum(fn ($t) => (float) ($t['betAmount'] ?? 0));

        // main log
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'betmain',
            'response'        => 'in',
            'amount'          => $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        $finalize = function (array $out) use ($mainLog) {
            $mainLog->output = $out;
            $mainLog->save();
            return $out;
        };

        foreach ($txns as $txn) {
            $txnId      = $txn['id']       ?? null;
            $roundId    = $txn['roundId']  ?? null;
            $status     = $txn['status']   ?? null;
            $betAmount  = (float) ($txn['betAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            $txnDup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->exists();

            if ($txnDup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            if ($status === 'OPEN') {
                $waitingExists = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'WAITING')
                    ->where('con_1', $txnId)
                    ->where('con_2', $roundId)
                    ->exists();

                if ($waitingExists) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $this->member->balance,
                        ];
                    $this->createGameLog([
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $session['productId'] ?? '',
                        'game_user'      => $this->member->user_name,
                        'method'         => $status,
                        'response'       => 'in',
                        'amount'         => $betAmount,
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => null,
                        'con_4'          => null,
                        'before_balance' => $oldBalance,
                        'after_balance'  => $this->member->balance,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ]);
                    break;
                }
            }

            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];
                $this->createGameLog([
                    'input'          => $txn,
                    'output'         => $param,
                    'company'        => $session['productId'] ?? '',
                    'game_user'      => $this->member->user_name,
                    'method'         => $status,
                    'response'       => 'in',
                    'amount'         => $betAmount,
                    'con_1'          => $txnId,
                    'con_2'          => $roundId,
                    'con_3'          => null,
                    'con_4'          => null,
                    'before_balance' => $oldBalance,
                    'after_balance'  => $this->member->balance,
                    'date_create'    => $this->now->toDateTimeString(),
                    'expireAt'       => $this->expireAt,
                ]);
                break;
            }

            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    $newBalance = $member->{$this->balances} - $betAmount;
                    if ($newBalance < 0) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            'log'   => null,
                            'member_balance' => (float) $member->{$this->balances},
                        ];
                    }

                    $member->decrement($this->balances, $betAmount);
                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $log = [
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $session['productId'] ?? '',
                        'game_user'      => $this->member->user_name,
                        'method'         => $status,
                        'response'       => 'in',
                        'amount'         => $betAmount,
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => null,
                        'con_4'          => null,
                        'before_balance' => $oldBalance,
                        'after_balance'  => $member->{$this->balances},
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'log'            => $log,
                        'member_balance' => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                $this->createGameLog($txResult['log']);
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txResult['member_balance']
//                );

                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }
    public function settleBets(Request $request)
    {
        $session = $request->all();
        $param   = [];

//        Log::channel('gamelog')->debug("Start settlebet-----------", ['session' => $session]);

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;
        $amount     = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'settlemain',
            'response'        => 'in',
            'amount'          => $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $isSingleState     = (bool)($txn['isSingleState'] ?? false);
            $skipBalanceUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);
            $isFeature         = (bool)($txn['isFeature'] ?? false);
            $isFeatureBuy      = (bool)($txn['isFeatureBuy'] ?? false);
            $isEndRound        = array_key_exists('isEndRound', $txn) ? (bool)$txn['isEndRound'] : true;
            $ismulti           = ($isFeature || $isFeatureBuy || ! $isEndRound);
            $transactionType   = $txn['transactionType'] ?? 'BY_TRANSACTION';

            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null;
            $payout  = (float)($txn['payoutAmount'] ?? 0);
            $betAmt  = (float)($txn['betAmount'] ?? 0);

            // 1) single-state: หัก OPEN ก่อน (ถ้าไม่ skip)
            if ($isSingleState) {
                if (! $skipBalanceUpdate) {
                    // กันซ้ำ OPEN
                    $existingBet = GameLogProxy::where('company', $session['productId'] ?? '')
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', 'OPEN')
                        ->where('con_1', $txnId)
                        ->where('con_2', $roundId)
                        ->first();

                    if ($existingBet) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                        break;
                    }

                    try {
                        $res = DB::transaction(function () use ($betAmt, $session, $txn, $txnId, $roundId, $oldBalance) {
                            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                            $newBalance = $member->{$this->balances} - $betAmt;
                            if ($newBalance < 0) {
                                return [
                                    'ok'   => false,
                                    'param'=> $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                                ];
                            }

                            $member->decrement($this->balances, $betAmt);
                            $member->refresh();

                            return [
                                'ok'  => true,
                                'bal' => (float)$member->{$this->balances},
                            ];
                        }, 1);

                        if (! $res['ok']) {
                            $param = $res['param'];
                            break;
                        }

                        // log OPEN (นอก TX)
                        $this->createGameLog([
                            'input'           => $txn,
                            'output'          => [],
                            'company'         => $session['productId'] ?? '',
                            'game_user'       => $this->member->user_name,
                            'method'          => 'OPEN',
                            'response'        => 'in',
                            'amount'          => $betAmt,
                            'con_1'           => $txnId,
                            'con_2'           => $roundId,
                            'con_3'           => null,
                            'con_4'           => null,
                            'before_balance'  => $oldBalance,
                            'after_balance'   => $this->member->balance,
                            'date_create'     => $this->now->toDateTimeString(),
                            'expireAt'        => $this->expireAt,
                        ]);
                    } catch (\Throwable $e) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                                'message' => $e->getMessage(),
                            ];
                        break;
                    }
                } else {
                    // ไม่หักยอด แต่มีรอย OPEN
                    $this->createGameLog([
                        'input'           => $txn,
                        'output'          => [],
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => 'OPEN',
                        'response'        => 'in',
                        'amount'          => $betAmt,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $this->member->balance,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ]);
                }
            }

            // 2) ตรวจ placeBets ตาม transactionType
            $relatedLogs = collect();
            $openLog     = null;

            if ($transactionType === 'BY_ROUND') {
                $relatedLogs = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->get();

                if ($relatedLogs->isEmpty()) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                if (! $ismulti && ! $skipBalanceUpdate) {
                    $dupLog = GameLogProxy::where('company', $session['productId'] ?? '')
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', $status)
                        ->where('con_2', $roundId)
                        ->whereNull('con_4')
                        ->latest('created_at')
                        ->first();

                    if ($dupLog && $dupLog['con_3'] === false) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                        break;
                    }
                }
            } else { // BY_TRANSACTION
                $openLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'OPEN')
                    ->where('con_1', $txnId)
                    ->latest('created_at')
                    ->first();

                if (! $openLog) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                if (! $skipBalanceUpdate) {
                    $dupSettle = GameLogProxy::where('company', $session['productId'] ?? '')
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', $status)
                        ->where('con_1', $txnId)
                        ->whereNull('con_4')
                        ->exists();

                    if ($dupSettle) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                        break;
                    }
                }
            }

            // 3) เติมเงิน (ถ้าต้องทำ) — ทำใน TX
            $settleResult = [
                'ok'              => true,
                'param'           => null,
                'logData'         => null,
                'member_balance'  => $this->member->balance,
            ];

            if (! $skipBalanceUpdate) {
                try {
                    $settleResult = DB::transaction(function () use ($session, $txn, $status, $payout, $roundId, $txnId, $ismulti, $oldBalance) {
                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                        $member->increment($this->balances, $payout);
                        $member->refresh();

                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                                'balanceBefore' => (float)$oldBalance,
                                'balanceAfter'  => (float)$member->{$this->balances},
                            ];

                        $logData = [
                            'input'           => $txn,
                            'output'          => $param,
                            'company'         => $session['productId'] ?? '',
                            'game_user'       => $this->member->user_name,
                            'method'          => $status,
                            'response'        => 'in',
                            'amount'          => $payout,
                            'con_1'           => $txnId,
                            'con_2'           => $roundId,
                            'con_3'           => $ismulti,
                            'con_4'           => null,
                            'before_balance'  => $oldBalance,
                            'after_balance'   => $member->{$this->balances},
                            'date_create'     => $this->now->toDateTimeString(),
                            'expireAt'        => $this->expireAt,
                        ];

                        return [
                            'ok'              => true,
                            'param'           => $param,
                            'logData'         => $logData,
                            'member_balance'  => (float)$member->{$this->balances},
                        ];
                    }, 1);
                } catch (\Throwable $e) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                            'message' => $e->getMessage(),
                        ];
                    break;
                }
            } else {
                // ไม่อัปเดตยอด แต่ตอบสำเร็จ + เตรียม log
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter'  => (float)$this->member->balance,
                    ];

                $settleResult = [
                    'ok'              => true,
                    'param'           => $param,
                    'logData'         => [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $payout,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => $ismulti,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $this->member->balance,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ],
                    'member_balance'  => $this->member->balance,
                ];
            }

            if (! $settleResult['ok']) {
                $param = $settleResult['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                break;
            }

            // 4) เขียน log settle + ผูก con_4
            $settleId = $this->createGameLog($settleResult['logData'])->id;
            $param    = $settleResult['param'];

            if ($transactionType === 'BY_ROUND') {
                foreach ($relatedLogs as $rl) {
                    $rl->con_4 = ($status ?? 'SETTLE') . '_' . $settleId;
                    $rl->save();
                }
            } elseif ($openLog) {
                $openLog->con_4 = ($status ?? 'SETTLE') . '_' . $settleId;
                $openLog->save();
            }

            // LogSeamless
//            LogSeamless::log(
//                $session['productId'] ?? '',
//                $this->member->user_name,
//                $txn,
//                $oldBalance,
//                $settleResult['member_balance']
//            );
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function adjustBets(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'adjustbetmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId   = $txn['id']      ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status']  ?? null;
            $newBet  = (float) ($txn['betAmount'] ?? 0.0);

            // หา base log ของรายการนี้ (อิงของเดิม)
            $origLog = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->latest('created_at')
                ->first();

            if (! $origLog) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $session['productId'] ?? '',
                    20001,
                    $this->member->balance
                );
                break;
            }

            $origBet = (float) $origLog->amount;
            $diff    = $newBet - $origBet; // >0 = ต้องตัดเพิ่ม, <0 = คืนเงิน

            try {
                $txResult = DB::transaction(function () use ($diff, $newBet, $session, $txn, $status, $txnId, $roundId, $oldBalance) {
                    // ล็อก member เพื่อกันแข่งกัน
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($diff > 0) {
                        // ต้องตัดเพิ่ม diff: กันติดลบ
                        if ($member->{$this->balances} < $diff) {
                            return [
                                'ok'    => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                                'log'   => null,
                            ];
                        }
                        $member->decrement($this->balances, $diff);
                    } elseif ($diff < 0) {
                        // คืนเงิน
                        $member->increment($this->balances, abs($diff));
                    }
                    $member->refresh();

                    // response หลังปรับยอด
                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $session['productId'] ?? '',
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    // เตรียม log ราย txn (เขียนนอก TX)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $newBet, // บันทึกยอดใหม่ตามของเดิม
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'            => true,
                        'param'         => $param,
                        'log'           => $logData,
                        'after_balance' => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // เขียน log adjust + ลิงก์กลับ base log
                $adjustId = $this->createGameLog($txResult['log'])->id;

                $origLog->con_4 = 'ADJUSTBET_' . $adjustId;
                $origLog->save();

                // LogSeamless (นอก TX)
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txResult['after_balance']
//                );

                $param = $txResult['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        50001,
                        $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function cancelBets(Request $request)
    {
        $session = $request->all();
        $param   = [];
        $isArray = false;

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'cancelmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId     = $txn['id'] ?? null;
            $roundId   = $txn['roundId'] ?? null;
            $status    = $txn['status'] ?? null; // เช่น CANCELLED / REJECT
            $txnType   = $txn['transactionType'] ?? 'BY_TRANSACTION';
            $reqAmount = (float) ($txn['betAmount'] ?? 0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN';

            // กันซ้ำ: เคย cancel รายการนี้แล้วหรือยัง
            $exists = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // หา base logs ที่เป็นต้นตอเงินเดิมพันจะถูกยกเลิก
            if ($txnType === 'BY_ROUND') {
                $logs = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $logMethod) // WAITING หรือ OPEN
                    ->where('con_2', $roundId)
                    ->get();

                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                $baseAmount = (float) $logs->sum('amount'); // ยอดที่เคยหักรวม
                $isArray    = true;
            } else {
                $logs = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $logMethod)
                    ->where('con_1', $txnId)
                    ->latest('created_at')->limit(1)->get();

                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                $baseAmount = (float) $logs[0]->amount; // ยอดที่เคยหักของรายการนี้
                $isArray    = false;
            }

            // ทำยอดเงินภายใต้ TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $reqAmount, $baseAmount, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // ตรรกะตามของเดิม:
                    // - ถ้า reqAmount > baseAmount : decrement(baseAmount) แล้ว increment(reqAmount)
                    // - ถ้า reqAmount <= baseAmount : increment(reqAmount)
                    if ($reqAmount > $baseAmount) {
                        $newBal = $member->{$this->balances} - $baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok'    => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            ];
                        }
                        $member->decrement($this->balances, $baseAmount);
                        $member->increment($this->balances, $reqAmount);
                    } else {
                        $member->increment($this->balances, $reqAmount);
                    }

                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $session['productId'] ?? '',
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $reqAmount,
                        'con_1'           => $txn['id'] ?? null,
                        'con_2'           => $txn['roundId'] ?? null,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logData'         => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log cancel (นอก TX)
                $logId = $this->createGameLog($txRes['logData'])->id;

                // อัปเดต con_4 ของ base logs
                if ($isArray) {
                    foreach ($logs as $lg) {
                        $lg->con_4 = ($status ?? 'CANCEL') . '_' . $logId;
                        $lg->save();
                    }
                } else {
                    $logs[0]->con_4 = ($status ?? 'CANCEL') . '_' . $logId;
                    $logs[0]->save();
                }

                // LogSeamless (นอก TX)
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        50001,
                        $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function rollback(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'rollbackmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ((array) ($session['txns'] ?? []) as $txn) {
            $status   = $txn['status'] ?? 'ROLLBACK';
            $txnType  = $txn['transactionType'] ?? 'BY_TRANSACTION';
            $txnId    = $txn['id'] ?? null;
            $roundId  = $txn['roundId'] ?? null;

            // --- ของเดิม: BY_ROUND เท่านั้นที่เช็กซ้ำ ROLLBACK ก่อน ---
            if ($txnType === 'BY_ROUND') {
                $isDup = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $status)   // ROLLBACK
                    ->where('con_1', $txnId)
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->exists();

                if ($isDup) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                    break;
                }
            }

            // --- หา baseLog ตามของเดิม ---
            if ($txnType === 'BY_ROUND') {
                $baseLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (! $baseLog) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }
            } else { // BY_TRANSACTION
                $baseLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_1', $txnId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (! $baseLog) {
                    // ของเดิม: ไม่พบ baseLog -> 20002
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                    break;
                }
            }

            // --- คำนวณยอด rollback ตามของเดิม ---
            $rollbackAmount = ($baseLog->method === 'SETTLED')
                ? (float) ($txn['payoutAmount'] ?? 0)
                : (float) ($txn['betAmount'] ?? 0);

            try {
                // 1) ปรับยอด (TX + lockForUpdate)
                $txRes = DB::transaction(function () use ($session, $txn, $status, $rollbackAmount, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // ของเดิม: ไม่เช็กติดลบ (decrement ได้)
                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $session['productId'] ?? '',
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    // ส่งต่อข้อมูลไปเขียน log นอก TX
                    return [
                        'param'          => $param,
                        'member_balance' => (float) $member->{$this->balances},
                    ];
                }, 1);

                // 2) เขียน rollback log (นอก TX) เพื่อให้ Mongo ไม่ไปอยู่ใน TX MySQL
                $logId = $this->createGameLog([
                    'input'           => $txn,
                    'output'          => $txRes['param'],
                    'company'         => $session['productId'] ?? '',
                    'game_user'       => $this->member->user_name,
                    'method'          => $status, // ROLLBACK
                    'response'        => 'in',
                    'amount'          => $rollbackAmount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => $oldBalance,
                    'after_balance'   => $txRes['member_balance'],
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ])->id;

                // 3) ผูก baseLog -> con_4 ชี้ไปยัง rollback log (บันทึกผ่าน instance ตรง ๆ)
                $baseLog->con_4 = $status . '_' . $logId;
                $baseLog->save();

                // 4) เคลียร์ WAITING/OPEN ที่ชี้ไป baseLog เดิม (เหมือนของเดิม)
                GameLogProxy::where('con_4', $baseLog->method . '_' . $baseLog->id)
                    ->whereIn('method', ['WAITING', 'OPEN'])
                    ->where('company', $session['productId'] ?? '')
                    ->where('game_user', $this->member->user_name)
                    ->update(['con_4' => null]);

                // 5) seamless log (นอก TX)
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        50001,
                        $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function winRewards(Request $request)
    {
        $session = $request->all();
        Log::channel('gamelog')->debug("Start Winreward-----------", ['session' => $session]);

        $param = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'winrewardmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null;
            $payout  = (float) ($txn['payoutAmount'] ?? 0);

            // กันซ้ำตามเดิม
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            try {
                // ปรับยอดใน TX + lockForUpdate (เร็ว/นิ่ง)
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($payout > 0) {
                        $member->increment($this->balances, $payout);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $session['productId'] ?? '',
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    // เตรียม log (เขียนนอก TX)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $payout,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logData'         => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                    break;
                }

                // เขียน log นอก TX
                $this->createGameLog($txRes['logData']);

                // Seamless log (นอก TX)
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        50001,
                        $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function voidSettled(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'voidsettledmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null; // เช่น VOID_SETTLED
            $type    = $txn['transactionType'] ?? 'BY_TRANSACTION';

            // กันซ้ำ
            $duplicate = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->exists();

            if ($duplicate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // หา SETTLED ต้นทาง
            if ($type === 'BY_ROUND') {
                $settledLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'SETTLED')
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();
            } else {
                $settledLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'SETTLED')
                    ->where('con_1', $txnId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();
            }

            if (! $settledLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                break;
            }

            // คำนวณยอดสุทธิที่จะปรับกลับ: คืน bet และหัก payout
            $betAmount = (float) ($txn['betAmount'] ?? 0);     // จะเพิ่ม
            $payout    = (float) ($txn['payoutAmount'] ?? 0);  // จะลด
            $netDelta  = $betAmount - $payout;                 // + เพิ่ม, - ลด, 0 คงเดิม

            try {
                // ปรับยอดใน TX + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $txn, $status, $netDelta, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // ป้องกันติดลบหลังปรับ
                    $candidate = (float) $member->{$this->balances} + $netDelta;
                    if ($candidate < 0) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                        ];
                    }

                    if ($netDelta > 0) {
                        $member->increment($this->balances, $netDelta);
                    } elseif ($netDelta < 0) {
                        $member->decrement($this->balances, abs($netDelta));
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $session['productId'] ?? '',
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    // เตรียม log (เขียนนอก TX)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $netDelta, // เก็บเป็น net เพื่ออ่านย้อนหลังง่าย
                        'con_1'           => $txn['id'] ?? null,
                        'con_2'           => $txn['roundId'] ?? null,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logData'         => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log นอก TX
                $logId = $this->createGameLog($txRes['logData'])->id;

                // ปิดปลายทาง SETTLED โดยผูก con_4
                $settledLog->con_4 = ($status ?? 'VOID_SETTLED') . '_' . $logId;
                $settledLog->save();

                // Seamless log นอก TX
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        50001,
                        $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function placeTips(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'placetipmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId      = $txn['id'] ?? null;
            $roundId    = $txn['roundId'] ?? null;
            $status     = $txn['status'] ?? null; // โดยปกติ 'TIPS'
            $amount     = (float) ($txn['betAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            // กันซ้ำตามเดิม
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance
                );
                break;
            }

            // ไม่อัปเดตยอด: ตอบสำเร็จ + เขียน log ไว้เฉย ๆ
            if ($skipUpdate) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance
                    ) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];

                $this->createGameLog([
                    'input'           => $txn,
                    'output'          => $param,
                    'company'         => $session['productId'] ?? '',
                    'game_user'       => $this->member->user_name,
                    'method'          => $status,
                    'response'        => 'in',
                    'amount'          => $amount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => $oldBalance,
                    'after_balance'   => $this->member->balance,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);

                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $this->member->balance
                );

                continue;
            }

            // อัปเดตยอดใน TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // กันติดลบ
                    if ($member->{$this->balances} < $amount) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}
                            ),
                        ];
                    }

                    if ($amount > 0) {
                        $member->decrement($this->balances, $amount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $amount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logData'         => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log นอก TX
                $this->createGameLog($txRes['logData']);

                // Seamless log นอก TX
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function cancelTips(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'canceltipmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null;           // สถานะยกเลิกทิป
            $amount  = (float) ($txn['betAmount'] ?? 0); // ยอดทิปที่จะคืน

            // กันซ้ำ: เคย cancel สำหรับ txn นี้แล้วหรือยัง
            $exists = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance
                );
                break;
            }

            // ต้องมีต้นทางเป็น TIPS ที่ยังไม่ถูกปิด con_4
            $tipLog = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'TIPS')
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->whereNull('con_4')
                ->latest('created_at')
                ->first();

            if (! $tipLog) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance
                );
                break;
            }

            // คืนยอดทิปใน TX + lockForUpdate (การคืนเงินไม่ทำให้ติดลบอยู่แล้ว)
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($amount > 0) {
                        $member->increment($this->balances, $amount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $amount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logData'         => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                    break;
                }

                // เขียน log ยกเลิกทิป (นอก TX)
                $logId = $this->createGameLog($txRes['logData'])->id;

                // ปิดปลายทาง log ต้นทาง TIPS
                $tipLog->con_4 = ($status ?? 'CANCEL_TIP') . '_' . $logId;
                $tipLog->save();

                // LogSeamless (นอก TX)
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $txn,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
    public function adjustBalance(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เปิดหัว
        $mainLog = $this->createGameLog([
            'input'           => $session,
            'output'          => $param,
            'company'         => $session['productId'] ?? '',
            'game_user'       => $this->member->user_name,
            'method'          => 'adjustbalancemain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $session['productId'] ?? null,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);

        foreach ($txns as $item) {
            $refId  = $item['refId'] ?? null;
            $status = $item['status'] ?? null;            // 'DEBIT' | 'CREDIT'
            $amount = (float) ($item['amount'] ?? 0);

            // กันซ้ำตามเดิม
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'ADJUSTBALANCE')
                ->where('con_1', $refId)
                ->where('con_2', $refId)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance
                );
                break;
            }

            try {
                // ปรับยอดใน TX + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $item, $status, $amount, $refId, $oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($status === 'DEBIT') {
                        // กันเครดิตติดลบ
                        if (($member->{$this->balances} - $amount) < 0) {
                            return [
                                'ok'    => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            ];
                        }
                        if ($amount > 0) {
                            $member->decrement($this->balances, $amount);
                        }
                    } else { // CREDIT
                        if ($amount > 0) {
                            $member->increment($this->balances, $amount);
                        }
                    }

                    $member->refresh();

                    // รูปแบบ response ของเดิม
                    $param = [
                        'id'              => $session['id'] ?? null,
                        'statusCode'      => 0,
                        'currency'        => 'THB',
                        'productId'       => $session['productId'] ?? '',
                        'username'        => $this->member->user_name,
                        'balanceBefore'   => (float) $oldBalance,
                        'balanceAfter'    => (float) $member->{$this->balances},
                        'timestampMillis' => $this->now->getTimestampMs(),
                    ];

                    // เตรียม log สองรายการเหมือนเดิม: ADJUSTBALANCE และ OPEN (เขียนนอก TX)
                    $baseLog = [
                        'input'           => $item,
                        'output'          => $param,
                        'company'         => $session['productId'] ?? '',
                        'game_user'       => $this->member->user_name,
                        'response'        => 'in',
                        'amount'          => $amount,
                        'con_1'           => $refId,
                        'con_2'           => $refId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $oldBalance,
                        'after_balance'   => $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'logs'            => [
                            array_merge($baseLog, ['method' => 'ADJUSTBALANCE']),
                            array_merge($baseLog, ['method' => 'OPEN']),
                        ],
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log นอก TX
                foreach ($txRes['logs'] as $lg) {
                    GameLogProxy::create($lg);
                }

                // Seamless log นอก TX
//                LogSeamless::log(
//                    $session['productId'] ?? '',
//                    $this->member->user_name,
//                    $item,
//                    $oldBalance,
//                    $txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

}
