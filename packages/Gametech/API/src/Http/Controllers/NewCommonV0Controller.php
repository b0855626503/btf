<?php

namespace Gametech\API\Http\Controllers;

use App\Services\GameLogRedisService;
use Gametech\API\Traits\LogSeamless;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

class NewCommonV0Controller extends AppBaseController
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
    protected $gameLogRedis;

    public function __construct(
        BankPaymentRepository $repository,
        MemberRepository      $memberRepo,
        GameUserRepository    $gameUserRepo,
        Request               $request,
        GameLogRedisService   $gameLogRedis
    )
    {
        $this->_config = $request->input('_config');
        $this->middleware('api');
        $this->repository = $repository;
        $this->memberRepository = $memberRepo;
        $this->gameUserRepository = $gameUserRepo;
        $this->request = $request;
        $this->gameLogRedis = $gameLogRedis;

        $this->now = now();


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

    public function getBalance(Request $request)
    {


        return response()->json(['succes' => true]);
    }

    // --- ทุกฟังก์ชันต่อไปนี้ ใช้ Redis Service แทน Mongo ---

    public function placeBets(Request $request)
    {
        $session = $request->all();
        $param = [];
        $txns = (array)($session['txns'] ?? []);

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;                  // <-- ใช้ service ฝั่ง Redis
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $oldBalance = (float)$this->member->balance;
        $amount = collect($txns)->sum(fn($t) => (float)($t['betAmount'] ?? 0));

        // ===== main log (response=in) ลง Redis =====
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [], // อัปเดตตอนจบ
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'betmain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null, // << ตามกติกา: ยกเว้น settleBets นอกนั้นเป็น null
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        // ปิด main log
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper: กันซ้ำแบบ AND (con_1 + con_2) และ con_3 ต้องเป็น null
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 10,
                'offset' => 0,
                'order' => 'desc',
            ]);
            foreach ($res['items'] as $it) {
                $c3 = $it['con_3'] ?? null;
                $c4 = $it['con_4'] ?? null;
                if (($it['response'] ?? '') === 'in' && $c3 === null && ($c4 === null || $c4 === '' || $c4 === 'null')) {
                    return true;
                }
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;            // เช่น OPEN / WAITING / ...
            $betAmount = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำ (AND: con_1 & con_2, และ con_3 ต้อง null)
            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            // 2) ถ้าเป็น OPEN → ข้ามหัก ถ้ามี WAITING คู่เดียวกันอยู่แล้ว (AND + con_3=null)
            if ($status === 'OPEN' && $txnId !== null && $roundId !== null) {
                if ($isDup('WAITING', (string)$txnId, (string)$roundId)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$this->member->balance,
                        ];

                    // log ย่อย (ไม่แตะ balance)
                    $glog->saveGameLogToRedis([
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $betAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null, // << null ตามกติกา
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);

                    break;
                }
            }

            // 3) ข้ามการอัปเดตยอด แต่ต้องเขียนล็อก
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];

                $glog->saveGameLogToRedis([
                    'input' => $txn,
                    'output' => $param,
                    'company' => $company,
                    'game_user' => $gameUser,
                    'method' => $status,
                    'response' => 'in',
                    'amount' => $betAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null, // << null
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);

                break;
            }

            // 4) หักยอดแบบ TX + lockForUpdate (DB) แต่ล็อกเหตุการณ์ลง Redis
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $current = (float)$member->{$this->balances};
                    $after = $current - $betAmount;

                    if ($after < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current),
                            'log' => null,
                            'member_balance' => $current,
                        ];
                    }

                    if ($betAmount > 0) {
                        $member->decrement($this->balances, $betAmount);
                        $member->refresh();
                        $after = (float)$member->{$this->balances};
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    // เตรียม log ย่อย (จะบันทึกนอก TX)
                    $log = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $betAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null, // << null
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'log' => $log,
                        'member_balance' => (float)$after,
                    ];
                }, 1);

                if (!$txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // log ย่อยลง Redis
                $glog->saveGameLogToRedis($txResult['log']);

                // (มีระบบ seamless ก็เก็บไว้ได้)
                LogSeamless::log(
                    $company,
                    $gameUser,
                    $txn,
                    $oldBalance,
                    $txResult['member_balance']
                );

                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }

    public function settleBets__(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis; // Redis only
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;
        $amount = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // main log (con_3=null)
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'settledmain',
            'response' => 'in',
            'amount' => (float)$amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ---------- helpers ----------
        $dupSettledByTxn = function (?string $con1) use ($glog, $gameUser, $company): bool {
            if ($con1 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_1' => (string)$con1,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };
        $dupSettledByRound = function (?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_2' => (string)$con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };
        $getOpenOrWaiting = function (array $cond) use ($glog, $gameUser, $company): array {
            $merge = [];
            foreach (['OPEN', 'WAITING'] as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, array_merge($cond, [
                    'limit' => 20,
                    'order' => 'desc',
                ]));
                foreach (($res['items'] ?? []) as $it) {
                    // ฟังก์ชันอื่น con_3 = null และยังไม่ถูกปิด
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_3'] ?? null) !== null) continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $merge[] = $it;
                }
            }
            return $merge;
        };

        foreach ($txns as $txn) {
            // flags → ใช้คำนวณ $ismulti
            $isSingleState = (bool)($txn['isSingleState'] ?? false);
            $skipBalanceUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);
            $isFeature = (bool)($txn['isFeature'] ?? false);
            $isFeatureBuy = (bool)($txn['isFeatureBuy'] ?? false);
            $isEndRound = array_key_exists('isEndRound', $txn) ? (bool)$txn['isEndRound'] : true;
            $ismulti = ($isFeature || $isFeatureBuy || !$isEndRound);

            $transactionType = (string)($txn['transactionType'] ?? 'BY_TRANSACTION'); // BY_TRANSACTION | BY_ROUND
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $payout = (float)($txn['payoutAmount'] ?? 0);
            $betAmt = (float)($txn['betAmount'] ?? 0);

            // ----- A) กันซ้ำ SETTLED ก่อนเสมอ -----
            if ($transactionType === 'BY_ROUND') {
                if ($dupSettledByRound($roundId)) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                    );
                    break;
                }
            } else { // BY_TRANSACTION
                if ($dupSettledByTxn($txnId)) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                    );
                    break;
                }
            }

            // ----- B) หา base OPEN/WAITING (ไว้ผูก con_4 หลัง SETTLED) -----
            if ($transactionType === 'BY_ROUND') {
                $baseLogs = $getOpenOrWaiting(['con_2' => (string)$roundId]);
            } else {
                // เอาทั้งคู่ (ผูกกับ txn+round เพื่อแม่น) แต่ถ้าไม่มี ให้ fallback หาแต่ con_1
                $baseLogs = $getOpenOrWaiting([
                    'con_1' => (string)$txnId,
                    'con_2' => (string)$roundId,
                ]);
                if (empty($baseLogs)) {
                    $baseLogs = $getOpenOrWaiting(['con_1' => (string)$txnId]);
                }
            }

            if (empty($baseLogs) && !$isSingleState) {
                // ไม่ใช่ single-state ต้องมีฐานเดิม
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance
                );
                break;
            }

            // ----- C) single-state → ถ้ายังไม่มี OPEN และไม่ skip ให้หัก bet แล้วเปิด OPEN -----
            if ($isSingleState) {
                // ตรวจ OPEN คู่นี้มีหรือยัง (AND con_1+con_2)
                $dupOpen = false;
                $chk = $glog->queryGameLogs($gameUser, $company, 'OPEN', [
                    'con_1' => (string)$txnId,
                    'con_2' => (string)$roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($chk['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) {
                        $dupOpen = true;
                        break;
                    }
                }

                if (!$dupOpen) {
                    if (!$skipBalanceUpdate) {
                        try {
                            $res = DB::transaction(function () use ($betAmt, $session, $company) {
                                $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                                $newBal = (float)$member->{$this->balances} - (float)$betAmt;
                                if ($newBal < 0) {
                                    return [
                                        'ok' => false,
                                        'param' => $this->responseData(
                                            $session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}
                                        ),
                                    ];
                                }
                                if ($betAmt > 0) {
                                    $member->decrement($this->balances, $betAmt);
                                    $member->refresh();
                                }
                                return ['ok' => true, 'bal' => (float)$member->{$this->balances}];
                            }, 1);

                            if (!$res['ok']) {
                                $param = $res['param'];
                                break;
                            }
                        } catch (\Throwable $e) {
                            $param = $this->responseData(
                                    $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                                ) + ['message' => $e->getMessage()];
                            break;
                        }
                    }

                    // เปิด OPEN (con_3=null)
                    $glog->saveGameLogToRedis([
                        'input' => $txn,
                        'output' => [],
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => 'OPEN',
                        'response' => 'in',
                        'amount' => (float)$betAmt,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);

                    // รีหา base ใหม่หลังเปิด OPEN
                    $baseLogs = $getOpenOrWaiting(['con_1' => (string)$txnId, 'con_2' => (string)$roundId]);
                }
            }

            // ----- D) เครดิต payout + เขียน SETTLED (con_3 = $ismulti) -----
            $settleRes = [
                'ok' => true,
                'param' => null,
                'member_balance' => (float)$this->member->balance,
                'log' => null,
            ];

            if (!$skipBalanceUpdate) {
                try {
                    $settleRes = DB::transaction(function () use ($session, $txn, $txnId, $roundId, $payout, $oldBalance, $company, $gameUser, $ismulti) {
                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                        if ($payout > 0) $member->increment($this->balances, $payout);
                        $member->refresh();

                        $after = (float)$member->{$this->balances};
                        $param = $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                            ) + [
                                'balanceBefore' => (float)$oldBalance,
                                'balanceAfter' => (float)$after,
                            ];

                        $log = [
                            'input' => $txn,
                            'output' => $param,
                            'company' => $company,
                            'game_user' => $gameUser,
                            'method' => 'SETTLED',
                            'response' => 'in',
                            'amount' => (float)$payout,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => (bool)$ismulti,
                            'con_4' => null,
                            'before_balance' => (float)$oldBalance,
                            'after_balance' => (float)$after,
                            'date_create' => $this->now->toDateTimeString(),
                            'expireAt' => $this->expireAt,
                        ];

                        return [
                            'ok' => true,
                            'param' => $param,
                            'member_balance' => $after,
                            'log' => $log,
                        ];
                    }, 1);
                } catch (\Throwable $e) {
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                        ) + ['message' => $e->getMessage()];
                    break;
                }
            } else {
                // ไม่อัปเดตยอด แต่ออก res + เตรียม log
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance
                    ) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];

                $settleRes = [
                    'ok' => true,
                    'param' => $param,
                    'member_balance' => (float)$this->member->balance,
                    'log' => [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => 'SETTLED',
                        'response' => 'in',
                        'amount' => (float)$payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => (bool)$ismulti,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ],
                ];
            }

            if (!$settleRes['ok']) {
                $param = $settleRes['param'];
                break;
            }

            // ----- E) บันทึก SETTLED + ปิด base ด้วย con_4 = "SETTLED_<id>" -----
            $settledId = $glog->saveGameLogToRedis($settleRes['log']);
            foreach ($baseLogs as $b) {
                $glog->updateLogField($b['log_id'], 'con_4', 'SETTLED_' . $settledId, $gameUser, $company);
            }

            LogSeamless::log($company, $gameUser, $txn, (float)$oldBalance, (float)$settleRes['member_balance']);
            $param = $settleRes['param'];
        }

        return $finalize($param);
    }

    public function settleBets(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis; // Redis only
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        $amount = 0.0;
        foreach ($txns as $t) { $amount += (float) ($t['payoutAmount'] ?? 0); }

        // main log (ตามเดิมใช้ settlemain) — con_3 = null
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'settlemain',
            'response'        => 'in',
            'amount'          => (float) $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => (float) $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ---------- helpers ----------
        $normBool = function ($v): bool {
            return $v === true || $v === 1 || $v === '1' || $v === 'true';
        };

        // ดึง "ล่าสุด 1 แถว" ของ method ที่ระบุ
        $latestOne = function (string $method, array $cond) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, $method, $cond + ['limit'=>20,'order'=>'desc']);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                return $it; // ล่าสุด
            }
            return null;
        };

        // BY_ROUND: หา "อะไรก็ได้" ที่ con_2 = $roundId และ con_4 = null (ไม่สน method, ไม่สน con_3)
        // NOTE: query ของ Redis ต้องระบุ method เราจึงรวมจากหลาย method ที่เป็น base ของ BET
        $anyByRoundCon4Null = function (string $roundId) use ($glog, $gameUser, $company): array {
            $methods = ['OPEN', 'WAITING','SETTLED']; // ไม่สน method ⇒ รวม method ที่เป็นฐาน BET
            $seen    = [];
            $out     = [];
            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_2' => (string) $roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null)   continue; // ต้องยังไม่ปิด
                    $id = $it['log_id'] ?? null;
                    if ($id && !isset($seen[$id])) {
                        $seen[$id] = true;
                        $out[] = $it; // ไม่กรอง con_3
                    }
                }
            }
            return $out;
        };

        // BY_ROUND: หา SETTLED ของรอบ (ใช้เช็ก duplicate non-multi) — ต้อง con_4 = null (ไม่สน con_3 ตอนดึง)
        $latestSettledByRoundCon4Null = function (string $roundId) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_2' => (string) $roundId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null)   continue; // ต้องยังไม่ปิด
                return $it; // ล่าสุดที่ยังไม่ปิด
            }
            return null;
        };

        // BY_TRANSACTION: หา SETTLED ของ txn (กันซ้ำ) — ต้อง con_4 = null (ไม่สน con_3)
        $latestSettledByTxnCon4Null = function (string $txnId) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_1' => (string) $txnId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null)   continue;
                return $it;
            }
            return null;
        };

        foreach ($txns as $txn) {
            // flags / fields
            $isSingleState     = (bool) ($txn['isSingleState'] ?? false);
            $skipBalanceUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);
            $isFeature         = (bool) ($txn['isFeature'] ?? false);
            $isFeatureBuy      = (bool) ($txn['isFeatureBuy'] ?? false);
            $isEndRound        = array_key_exists('isEndRound', $txn) ? (bool) $txn['isEndRound'] : true;
            $ismulti           = ($isFeature || $isFeatureBuy || ! $isEndRound);

            $transactionType   = (string) ($txn['transactionType'] ?? 'BY_TRANSACTION');
            $status            = (string) ($txn['status'] ?? 'SETTLED');

            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $payout  = (float) ($txn['payoutAmount'] ?? 0);
            $betAmt  = (float) ($txn['betAmount'] ?? 0);

            // =========================================================
            // [1] single-state: เช็กก่อนเสมอ (ไม่ขึ้นกับ BY อะไรทั้งนั้น)
            //     - ดู OPEN ล่าสุด 1 แถว ด้วย AND con_1+con_2 (ไม่สน con_4)
            //       ถ้ามี → duplicate (20002)
            //     - ถ้าไม่มี → (ถ้าไม่ skip) หัก bet และ "สร้าง" OPEN (con_3=null, con_4=null)
            // =========================================================
            if ($isSingleState && $txnId !== null && $roundId !== null) {
                $dupOpen = $latestOne('OPEN', [
                    'con_1' => (string) $txnId,
                    'con_2' => (string) $roundId,
                ]);
                if ($dupOpen) {
                    $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $session['productId'] ?? '',
                        20002,
                        $this->member->balance
                    );
                    break;
                }

                if (! $skipBalanceUpdate && $betAmt > 0) {
                    try {
                        $res = DB::transaction(function () use ($betAmt, $session) {
                            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                            $newBal = (float) $member->{$this->balances} - (float) $betAmt;
                            if ($newBal < 0) {
                                return [
                                    'ok'    => false,
                                    'param' => $this->responseData(
                                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, (float) $member->{$this->balances}
                                    ),
                                ];
                            }
                            $member->decrement($this->balances, $betAmt);
                            $member->refresh();
                            return ['ok'=>true];
                        }, 1);
                        if (! $res['ok']) { $param = $res['param']; break; }
                    } catch (\Throwable $e) {
                        $param = $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance
                            ) + ['message' => $e->getMessage()];
                        break;
                    }
                }

                $glog->saveGameLogToRedis([
                    'input'           => $txn,
                    'output'          => [],
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => 'OPEN',
                    'response'        => 'in',
                    'amount'          => (float) $betAmt,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $oldBalance,
                    'after_balance'   => (float) $this->member->balance,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);
            }

            // =========================================================
            // [2] แยกตาม $transactionType
            // =========================================================
            $baseLogs = [];

            if ($transactionType === 'BY_ROUND') {
                // (ปรับตามสเปกใหม่) หา con_2=$roundId และ con_4=null "ไม่สน method และ con_3"
                if (! $roundId) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }
                $baseLogs = $anyByRoundCon4Null((string) $roundId);
                if (empty($baseLogs)) {
                    // ไม่มี BET ใดๆ ของรอบที่ยังไม่ปิด
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                // กันซ้ำเฉพาะกรณี ($ismulti=false && $skipBalanceUpdate=false)
                if (! $ismulti && ! $skipBalanceUpdate) {
                    $dup = $latestSettledByRoundCon4Null((string) $roundId); // ดึงตัวล่าสุด (con_4=null)
                    if ($dup && $normBool($dup['con_3'] ?? false) === false) {
                        // เคย SETTLED non-multi ไปแล้ว
                        $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance
                        );
                        break;
                    }
                }
                // ถ้า $ismulti = true → ข้ามการกันซ้ำ

            } else { // BY_TRANSACTION
                // หา OPEN ของ txn ล่าสุด 1 รายการ (ไม่สน con_3, con_4)
                if (! $txnId) {
                    return $finalize($param);
                }
                $openLatest = $latestOne('OPEN', ['con_1' => (string) $txnId]);
                if (! $openLatest) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    return $finalize($param);
                }
                // เอามาเป็นฐานผูก con_4 เฉพาะถ้ายังไม่ปิด
                if (($openLatest['con_4'] ?? null) === null) {
                    $baseLogs = [ $openLatest ];
                }

                // ถ้าไม่ skip ⇒ กันซ้ำ SETTLED ด้วย con_1 และ con_4=null (ไม่สน con_3)
                if (! $skipBalanceUpdate) {
                    $dup = $latestSettledByTxnCon4Null((string) $txnId);
                    if ($dup) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                        return $finalize($param);
                    }
                }
            }

            // =========================================================
            // [3] ถ้าไม่ skip ⇒ อัปเดตยอด + เขียน SETTLED (con_3 = $ismulti)
            // =========================================================
            if (! $skipBalanceUpdate) {
                try {
                    $txRes = DB::transaction(function () use ($session, $payout) {
                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                        if ($payout > 0) { $member->increment($this->balances, $payout); }
                        $member->refresh();

                        $param = $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, (float) $member->{$this->balances}
                            ) + [
                                'balanceBefore' => (float) $this->member->balance,
                                'balanceAfter'  => (float) $member->{$this->balances},
                            ];

                        return ['param'=>$param, 'after'=>(float) $member->{$this->balances}];
                    }, 1);

                    $settledId = $glog->saveGameLogToRedis([
                        'input'           => $txn,
                        'output'          => $txRes['param'],
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => 'SETTLED',
                        'response'        => 'in',
                        'amount'          => (float) $payout,
                        'con_1'           => $txn['id'] ?? null,
                        'con_2'           => $txn['roundId'] ?? null,
                        'con_3'           => (bool) $ismulti,
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $txRes['after'],
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ]);

                    // ผูก con_4 ให้ทุกฐานที่ยังไม่ปิด
                    foreach ($baseLogs as $b) {
                        if (!empty($b['log_id']) && ($b['con_4'] ?? null) === null) {
                            $glog->updateLogField($b['log_id'], 'con_4', 'SETTLED_' . $settledId, $gameUser, $company);
                        }
                    }

                    LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['after']);
                    $param = $txRes['param'];
                } catch (\Throwable $e) {
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance
                        ) + ['message' => $e->getMessage()];
                    break;
                }
            } else {
                // skip ⇒ ไม่อัปเดตยอด ไม่เขียน SETTLED แต่ออก 0 ให้
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance
                    ) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];
            }
        }

        return $finalize($param);
    }


    public function winRewards(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'winrewardmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // AND duplicate checker per status
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
            ]);
            foreach ($res['items'] as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $payout = (float)($txn['payoutAmount'] ?? 0);

            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    if ($payout > 0) $member->increment($this->balances, $payout);
                    $member->refresh();
                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status, // เช่น WIN_REWARD
                        'response' => 'in',
                        'amount' => $payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,     // << null
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                $glog->saveGameLogToRedis($txRes['logData']);
                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }

    public function voidSettled(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'voidsettledmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 10,
            ]);
            foreach ($res['items'] as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? 'VOID_SETTLED';
            $type = $txn['transactionType'] ?? 'BY_TRANSACTION'; // 'BY_ROUND' | 'BY_TRANSACTION'

            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            // หา SETTLED ต้นทาง
            $filters = ($type === 'BY_ROUND')
                ? ['con_2' => (string)$roundId, 'limit' => 20]
                : ['con_1' => (string)$txnId, 'limit' => 20];

            $settled = $glog->queryGameLogs($gameUser, $company, 'SETTLED', $filters);
            $settledItems = array_filter($settled['items'] ?? [], fn($it) => ($it['con_4'] ?? null) === null);
            $src = reset($settledItems) ?: null;
            if (!$src) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, $this->member->balance);
                break;
            }
            $srcLogId = $src['log_id'];

            $betAmount = (float)($txn['betAmount'] ?? 0);
            $payout = (float)($txn['payoutAmount'] ?? 0);
            $netDelta = $betAmount - $payout; // คืน bet (+), หัก payout (-)

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $netDelta, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    $candidate = (float)$member->{$this->balances} + $netDelta;
                    if ($candidate < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $member->{$this->balances}),
                        ];
                    }

                    if ($netDelta > 0) $member->increment($this->balances, $netDelta);
                    elseif ($netDelta < 0) $member->decrement($this->balances, abs($netDelta));
                    $member->refresh();

                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $netDelta, // เก็บ net
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log void แล้วผูก con_4 เข้า SETTLED ต้นทาง
                $voidLogId = $glog->saveGameLogToRedis($txRes['logData']);
                $glog->updateLogField($srcLogId, 'con_4', ($status ?? 'VOID_SETTLED') . '_' . $voidLogId, $gameUser, $company);

                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }

    public function placeTips(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'placetipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
            ]);
            foreach ($res['items'] as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? 'TIPS';
            $amount = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];
                $glog->saveGameLogToRedis([
                    'input' => $txn,
                    'output' => $param,
                    'company' => $company,
                    'game_user' => $gameUser,
                    'method' => $status,
                    'response' => 'in',
                    'amount' => $amount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);
                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $this->member->balance);
                continue;
            }

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($member->{$this->balances} < $amount) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $member->{$this->balances}),
                        ];
                    }

                    if ($amount > 0) $member->decrement($this->balances, $amount);
                    $member->refresh();
                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                $glog->saveGameLogToRedis($txRes['logData']);
                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + ['message' => $e->getMessage()];
                break;
            }
        }

        $glog->updateLogField($mainLogId, 'output', $param, $gameUser, $company);
        return $param;
    }

    public function cancelTips(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'canceltipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
            ]);
            foreach ($res['items'] as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? 'CANCEL_TIP';
            $amount = (float)($txn['betAmount'] ?? 0);

            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            // หา TIPS ต้นทาง (con_4 ยังว่าง)
            $tips = $glog->queryGameLogs($gameUser, $company, 'TIPS', [
                'con_1' => (string)$txnId,
                'con_2' => (string)$roundId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            $src = null;
            foreach ($tips['items'] as $it) {
                if (($it['con_4'] ?? null) === null) {
                    $src = $it;
                    break;
                }
            }
            if (!$src) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, $this->member->balance);
                break;
            }
            $srcLogId = $src['log_id'];

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    if ($amount > 0) $member->increment($this->balances, $amount);
                    $member->refresh();

                    $after = (float)$member->{$this->balances};
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                $cancelLogId = $glog->saveGameLogToRedis($txRes['logData']);
                $glog->updateLogField($srcLogId, 'con_4', ($status ?? 'CANCEL_TIP') . '_' . $cancelLogId, $gameUser, $company);

                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function adjustBalance(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'adjustbalancemain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDup = function (?string $refId) use ($glog, $gameUser, $company): bool {
            if (!$refId) return false;
            $res = $glog->queryGameLogs($gameUser, $company, 'ADJUSTBALANCE', [
                'con_1' => (string)$refId,
                'con_2' => (string)$refId,
                'limit' => 10,
            ]);
            foreach ($res['items'] as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) return true;
            }
            return false;
        };

        foreach ($txns as $item) {
            $refId = $item['refId'] ?? null;
            $status = $item['status'] ?? null;           // 'DEBIT' | 'CREDIT'
            $amount = (float)($item['amount'] ?? 0);

            if ($isDup($refId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            try {
                $txRes = DB::transaction(function () use ($session, $item, $status, $amount, $refId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($status === 'DEBIT') {
                        if (($member->{$this->balances} - $amount) < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $member->{$this->balances}),
                            ];
                        }
                        if ($amount > 0) $member->decrement($this->balances, $amount);
                    } else { // CREDIT
                        if ($amount > 0) $member->increment($this->balances, $amount);
                    }

                    $member->refresh();
                    $after = (float)$member->{$this->balances};

                    // รูปแบบ response
                    $param = [
                        'id' => $session['id'] ?? null,
                        'statusCode' => 0,
                        'currency' => 'THB',
                        'productId' => $company,
                        'username' => $gameUser,
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$after,
                        'timestampMillis' => $this->now->getTimestampMs(),
                    ];

                    $baseLog = [
                        'input' => $item,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $refId,
                        'con_2' => $refId,
                        'con_3' => null, // << null
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logs' => [
                            array_merge($baseLog, ['method' => 'ADJUSTBALANCE']),
                            array_merge($baseLog, ['method' => 'OPEN']),
                        ],
                        'member_balance' => $after,
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                foreach ($txRes['logs'] as $lg) $glog->saveGameLogToRedis($lg);
                LogSeamless::log($company, $gameUser, $item, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelBets_(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;  // Redis only
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // main log (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'cancelmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // -------- helpers (Redis) --------
        $dupCancel = function (string $status, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = (string)($txn['status'] ?? 'CANCELLED');      // CANCELLED | REJECT
            $txnType = (string)($txn['transactionType'] ?? 'BY_TRANSACTION');
            $reqAmount = (float)($txn['betAmount'] ?? 0.0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN';

            // กันซ้ำรายการ cancel (AND: con_1 + con_2)
            if ($dupCancel($status, $txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                );
                break;
            }

            // หา base logs
            $baseLogs = [];
            $baseAmount = 0.0;
            $isArray = false;

            if ($txnType === 'BY_ROUND') {
                if (!$roundId) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }

                // รวมทั้งหมดของรอบ (เหมือนของเดิม: ไม่กรอง con_4)
                $res = $glog->queryGameLogs($gameUser, $company, $logMethod, [
                    'con_2' => (string)$roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                $items = array_filter(($res['items'] ?? []), fn($it) => (($it['response'] ?? '') === 'in'));
                if (!$items) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }

                $baseLogs = array_values($items);
                $baseAmount = array_reduce($baseLogs, fn($c, $it) => $c + (float)($it['amount'] ?? 0), 0.0);
                $isArray = true;
            } else {
                if (!$txnId) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }

                // ดูด้วย con_1 = txnId เท่านั้น (ไม่ผูก con_2) และเอาตัว "ล่าสุด"
                $res = $glog->queryGameLogs($gameUser, $company, $logMethod, [
                    'con_1' => (string)$txnId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                $items = [];
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    $items[] = $it;
                }
                if (!$items) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }

                usort($items, fn($a, $b) => strcmp((string)($b['date_create'] ?? ''), (string)($a['date_create'] ?? '')));
                $baseLogs = [$items[0]];                     // ล่าสุด 1 แถว
                $baseAmount = (float)($items[0]['amount'] ?? 0);
                $isArray = false;
            }

            // ทำยอดเงินใน TX ตามสูตรเดิม
            try {
                $txRes = DB::transaction(function () use ($session, $reqAmount, $baseAmount, $oldBalance, $company, $gameUser, $txn, $status) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($reqAmount > $baseAmount) {
                        $newBal = (float)$member->{$this->balances} - (float)$baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}
                                ),
                            ];
                        }
                        if ($baseAmount > 0) {
                            $member->decrement($this->balances, $baseAmount);
                        }
                        if ($reqAmount > 0) {
                            $member->increment($this->balances, $reqAmount);
                        }
                    } else {
                        if ($reqAmount > 0) {
                            $member->increment($this->balances, $reqAmount);
                        }
                    }

                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // log สำหรับ Redis (input ใช้ $txn ของแถวนี้ตรงๆ)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,               // CANCELLED | REJECT
                        'response' => 'in',
                        'amount' => (float)$reqAmount,
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // เขียน log CANCELLED/REJECT บน Redis
                $cancelId = $glog->saveGameLogToRedis($txRes['logData']);

                // อัปเดต con_4 ของ base logs ให้ปิดด้วยสถานะนี้
                if ($isArray) {
                    foreach ($baseLogs as $lg) {
                        $glog->updateLogField($lg['log_id'], 'con_4', ($status ?: 'CANCEL') . '_' . $cancelId, $gameUser, $company);
                    }
                } else {
                    $glog->updateLogField($baseLogs[0]['log_id'], 'con_4', ($status ?: 'CANCEL') . '_' . $cancelId, $gameUser, $company);
                }

                // LogSeamless (ถ้าต้องใช้)
                 LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelBets(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;  // Redis only
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // main log
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'cancelmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // -------- helpers --------
        $dupCancel = function (string $status, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false; // กันซ้ำต้องมีทั้งคู่
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue; // ถูกปิดแล้วไม่ถือว่าซ้ำ
                return true;
            }
            return false;
        };

        // ดึง OPEN/WAITING แบบ “พร้อมใช้” (คัดกรองแล้ว)
        $fetchBaseMany = function (string $method, array $cond) use ($glog, $gameUser, $company): array {
            $res = $glog->queryGameLogs($gameUser, $company, $method, $cond + [
                    'limit' => 20,          // << ลดเพดาน
                    'order' => 'desc',
                ]);
            $out = [];
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_3'] ?? null) !== null) continue; // ฟังก์ชันนี้ con_3 ต้อง null
                if (($it['con_4'] ?? null) !== null) continue; // ต้องยังไม่ถูกปิด
                $out[] = $it;
            }
            return $out;
        };

        $fetchBaseOne = function (string $method, array $cond) use ($fetchBaseMany): ?array {
            $items = $fetchBaseMany($method, $cond);
            return $items[0] ?? null; // ได้มาเรียง desc แล้ว ไม่ต้อง usort ซ้ำ
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = (string)($txn['status'] ?? 'CANCELLED');  // CANCELLED | REJECT
            $txnType = (string)($txn['transactionType'] ?? 'BY_TRANSACTION');
            $reqAmount = (float)($txn['betAmount'] ?? 0.0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN';

            // 1) กันซ้ำ
            if ($dupCancel($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // 2) หา base logs ให้แคบที่สุด
            $baseLogs = [];
            $baseAmount = 0.0;
            $isArray = false;

            if ($txnType === 'BY_ROUND') {
                if ($roundId === null) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }
                $baseLogs = $fetchBaseMany($logMethod, ['con_2' => (string)$roundId]);
                if (!$baseLogs) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }
                foreach ($baseLogs as $b) {
                    $baseAmount += (float)($b['amount'] ?? 0);
                }
                $isArray = true;
            } else {
                if ($txnId === null) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }
                // จับคู่ (con_1, con_2) ก่อน ถ้าไม่เจอค่อย fallback con_1
                $one = null;
                if ($roundId !== null) {
                    $one = $fetchBaseOne($logMethod, ['con_1' => (string)$txnId, 'con_2' => (string)$roundId]);
                }
                if (!$one) $one = $fetchBaseOne($logMethod, ['con_1' => (string)$txnId]);
                if (!$one) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }
                $baseLogs = [$one];
                $baseAmount = (float)($one['amount'] ?? 0);
                $isArray = false;
            }

            // 3) ทำยอดใน TX
            try {
                $txRes = DB::transaction(function () use ($session, $reqAmount, $baseAmount, $oldBalance, $company, $gameUser, $txn, $status) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($reqAmount > $baseAmount) {
                        $newBal = (float)$member->{$this->balances} - (float)$baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
                            ];
                        }
                        if ($baseAmount > 0) $member->decrement($this->balances, $baseAmount);
                        if ($reqAmount > 0) $member->increment($this->balances, $reqAmount);
                    } else {
                        if ($reqAmount > 0) $member->increment($this->balances, $reqAmount);
                    }

                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => (float)$reqAmount,
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logData' => $logData,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // 4) เขียน log และปิด base
                $cancelId = $glog->saveGameLogToRedis($txRes['logData']);

                if ($isArray) {
                    foreach ($baseLogs as $lg) {
                        $glog->updateLogField($lg['log_id'], 'con_4', ($status ?: 'CANCEL') . '_' . $cancelId, $gameUser, $company);
                    }
                } else {
                    $glog->updateLogField($baseLogs[0]['log_id'], 'con_4', ($status ?: 'CANCEL') . '_' . $cancelId, $gameUser, $company);
                }

                 LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function rollback(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis; // ใช้ Redis service เท่านั้น
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $oldBalance = (float)$this->member->balance;

        // main log (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'rollbackmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // -------- helpers (Redis) --------
        $dupRollbackByRound = function (string $status, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'con_1' => (string)$con1,
                'con_2' => (string)$con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };

        $findBaseByRound = function (string $roundId) use ($glog, $gameUser, $company): ?array {
            $cands = [];
            foreach (['SETTLED', 'REFUND'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_2' => $roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $cands[] = $it;
                }
            }
            if (!$cands) return null;
            usort($cands, fn($a, $b) => strcmp((string)($b['date_create'] ?? ''), (string)($a['date_create'] ?? '')));
            return $cands[0];
        };

        $findBaseByTxn = function (string $txnId) use ($glog, $gameUser, $company): ?array {
            $cands = [];
            foreach (['SETTLED', 'REFUND'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_1' => $txnId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $cands[] = $it;
                }
            }
            if (!$cands) return null;
            usort($cands, fn($a, $b) => strcmp((string)($b['date_create'] ?? ''), (string)($a['date_create'] ?? '')));
            return $cands[0];
        };

        $clearWaitingOpenPointingTo = function (string $tag) use ($glog, $gameUser, $company): void {
            foreach (['WAITING', 'OPEN'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'limit' => 20,
                    'order' => 'desc',
                    // ถ้า service รองรับ con4='notnull' จะช่วยกรองได้; ถ้าไม่รองรับก็กรองเองข้างล่าง
                ]);
                Log::channel('gamelog')->debug('rollback ล้างค่า ของการ bet open- waiting' , [ 'betlog' =>$r ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) === $tag) {
                        $glog->updateLogField($it['log_id'], 'con_4', null, $gameUser, $company);
                    }
                }
            }
        };

        foreach ((array)($session['txns'] ?? []) as $txn) {
            $status = (string)($txn['status'] ?? 'ROLLBACK');
            $txnType = (string)($txn['transactionType'] ?? 'BY_TRANSACTION'); // BY_TRANSACTION | BY_ROUND
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;

            // --- BY_ROUND เท่านั้นที่เช็กซ้ำ ROLLBACK (ของเดิม) ---
            if ($txnType === 'BY_ROUND') {
                Log::channel('gamelog')->debug('rollback by BY_ROUND chk dup',[ 'txid' => $txnId , 'roundid' => $roundId ]);
                if ($dupRollbackByRound($status, $txnId, $roundId)) {
                    Log::channel('gamelog')->debug('rollback by BY_ROUND พบ dup 2002', ['dup' => $dupRollbackByRound($status, $txnId, $roundId) , 'txid' => $txnId , 'roundid' => $roundId]);
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                    );
                    break;
                }
            }

            // --- หา baseLog ตามของเดิม ---
            $baseLog = null;
            if ($txnType === 'BY_ROUND') {
                Log::channel('gamelog')->debug('rollback by BY_ROUND ', ['txid' => $txnId , 'roundid' => $roundId]);

                if (!$roundId) {
                    Log::channel('gamelog')->debug('rollback by BY_ROUND ไม่มี $roundId '.$roundId , ['txid' => $txnId , 'roundid' => $roundId]);
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance
                    );
                    break;
                }
                $baseLog = $findBaseByRound((string)$roundId);
                Log::channel('gamelog')->debug('rollback by BY_ROUND หา baselog  '.$roundId , ['txid' => $txnId , 'roundid' => $roundId]);

                if (!$baseLog) {

                    Log::channel('gamelog')->debug('rollback by BY_ROUND ไม่มี baselog 20001 '.$roundId , ['txid' => $txnId , 'roundid' => $roundId]);
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance
                    );
                    break;
                }
            } else { // BY_TRANSACTION
                if (!$txnId) {
                    Log::channel('gamelog')->debug('rollback by tran ไม่มี txid ', ['txid' => $txnId , 'roundid' => $roundId]);

                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                    );
                    break;
                }
                $baseLog = $findBaseByTxn((string)$txnId); // <<< ดูด้วย con_1 เท่านั้น (ไม่ใช้ con_2) ตามของเดิม
                Log::channel('gamelog')->debug('rollback by tran หา baselog',['baselog' => $baseLog , 'txid' => $txnId , 'roundid' => $roundId]);
                if (!$baseLog) {
                    Log::channel('gamelog')->debug('rollback by tran ไม่พบ baselog = 20002',['txid' => $txnId , 'roundid' => $roundId]);
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                    );
                    break;
                }
            }

            // --- คำนวณยอด rollback ตามของเดิม ---
            $rollbackAmount = ($baseLog['method'] === 'SETTLED')
                ? (float)($txn['payoutAmount'] ?? 0)
                : (float)($txn['betAmount'] ?? 0);

            try {
                // 1) ปรับยอด (ไม่เช็คติดลบ) + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $rollbackAmount, $oldBalance, $company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    return [
                        'param' => $param,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                // 2) เขียน rollback log บน Redis (นอก TX)
                $rbId = $glog->saveGameLogToRedis([
                    'input' => $txn,
                    'output' => $txRes['param'],
                    'company' => $company,
                    'game_user' => $gameUser,
                    'method' => $status, // ROLLBACK
                    'response' => 'in',
                    'amount' => (float)$rollbackAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$txRes['member_balance'],
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);

                // 3) ผูก baseLog → con_4 ชี้ไปยัง rollback log
                $baseTag = $baseLog['method'] . '_' . $baseLog['log_id'];
                Log::channel('gamelog')->debug('rollback by tran อัพเดท settled ค่า con_4 '.$baseLog['log_id'].' ด้วยค่า'.$status . '_' . $rbId,['settle' => $baseLog , 'txid' => $txnId , 'roundid' => $roundId]);
                $glog->updateLogField($baseLog['log_id'], 'con_4', $status . '_' . $rbId, $gameUser, $company);

                // 4) เคลียร์ WAITING/OPEN ที่เคยชี้ไปหา base เดิม ให้เป็น null (ตามของเดิม)
                $clearWaitingOpenPointingTo($baseTag);

                // 5) seamless log (ไว้ตามต้องการ)
                 LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function rollback_(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $oldBalance = (float)$this->member->balance;

        // main log
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'rollbackmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helpers
        $dupRollbackByRound = function (string $status, ?string $txnId, ?string $roundId) use ($glog, $gameUser, $company): bool {
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'con_1' => (string)$txnId,
                'con_2' => (string)$roundId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };
        $findBase = function (array $methods, array $cond) use ($glog, $gameUser, $company): ?array {
            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, $cond + [
                        'limit' => 20,
                        'order' => 'desc',
                    ]);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue; // ยังไม่ถูกปิด
                    return $it; // เอาตัวล่าสุด
                }
            }
            return null;
        };
        $clearPointersToBase = function (array $base) use ($glog, $gameUser, $company): void {
            $pointer = ($base['method'] ?? '') . '_' . ($base['log_id'] ?? '');
            if ($pointer === '_') return;

            foreach (['WAITING', 'OPEN'] as $m) {
                // กรองด้วย con_2 / con_1 ของ base เพื่อลดงาน
                $cond = [];
                if (isset($base['con_2'])) $cond['con_2'] = (string)$base['con_2'];
                if (isset($base['con_1'])) $cond['con_1'] = (string)$base['con_1'];

                $res = $glog->queryGameLogs($gameUser, $company, $m, $cond + ['limit' => 20, 'order' => 'desc']);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== $pointer) continue;
                    $glog->updateLogField($it['log_id'], 'con_4', null, $gameUser, $company);
                }
            }
        };

        foreach ((array)($session['txns'] ?? []) as $txn) {
            $status = (string)($txn['status'] ?? 'ROLLBACK');
            $txnType = (string)($txn['transactionType'] ?? 'BY_TRANSACTION');
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;

            // ของเดิม: BY_ROUND เท่านั้นที่กันซ้ำ ROLLBACK ก่อน
            if ($txnType === 'BY_ROUND') {
                if ($dupRollbackByRound($status, $txnId, $roundId)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                    break;
                }
            }

            // หา base ตามของเดิม (ล่าสุด, ยังไม่ปิด)
            if ($txnType === 'BY_ROUND') {
                $base = $findBase(['REFUND', 'SETTLED'], ['con_2' => (string)$roundId]);
                if (!$base) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                    break;
                }
            } else {
                $base = $findBase(['REFUND', 'SETTLED'], ['con_1' => (string)$txnId]);
                if (!$base) {
                    // ของเดิม: ไม่พบ base ใน BY_TRANSACTION → 20002
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                    break;
                }
            }

            // คำนวณยอด rollback
            $rollbackAmount = (strtoupper((string)($base['method'] ?? '')) === 'SETTLED')
                ? (float)($txn['payoutAmount'] ?? 0)
                : (float)($txn['betAmount'] ?? 0);

            try {
                // ปรับยอด (ของเดิม: allow ติดลบ)
                $txRes = DB::transaction(function () use ($session, $txn, $status, $rollbackAmount, $oldBalance, $company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($rollbackAmount > 0) $member->decrement($this->balances, $rollbackAmount);
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    return [
                        'param' => $param,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                // เขียน rollback log (นอก TX)
                $logId = $glog->saveGameLogToRedis([
                    'input' => $txn,
                    'output' => $txRes['param'],
                    'company' => $company,
                    'game_user' => $gameUser,
                    'method' => $status,
                    'response' => 'in',
                    'amount' => (float)$rollbackAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$txRes['member_balance'],
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);

                // ผูก base → con_4 = "ROLLBACK_<logId>"
                $glog->updateLogField($base['log_id'], 'con_4', $status . '_' . $logId, $gameUser, $company);

                // เคลียร์ WAITING/OPEN ที่ชี้ไป base เดิม
                $clearPointersToBase($base);

                // optional seamless
                 LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function adjustBets(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // main log
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [],
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'adjustbetmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => (float)$oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // หา "ฐานล่าสุด" ของคู่นี้จาก ADJUST > OPEN > WAITING (response=in, con_3=null, con_4=null)
        $findLatestBase = function (string $con1, string $con2) use ($glog, $gameUser, $company): ?array {
            $pickLatest = function (array $items, string $con2): ?array {
                $cands = array_filter($items, fn($it) => (($it['response'] ?? '') === 'in')
                    && (($it['con_3'] ?? null) === null)
                    && (($it['con_4'] ?? null) === null)
                    && (string)($it['con_2'] ?? '') === (string)$con2
                );
                if (!$cands) return null;
                usort($cands, fn($a, $b) => strcmp((string)($b['date_create'] ?? ''), (string)($a['date_create'] ?? '')));
                return $cands[0];
            };

            // ลอง ADJUST ก่อน
            $r = $glog->queryGameLogs($gameUser, $company, 'ADJUST', [
                'con_1' => $con1,
                'con_2' => $con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            if ($b = $pickLatest($r['items'] ?? [], $con2)) return $b;

            // ถัดไป OPEN
            $r = $glog->queryGameLogs($gameUser, $company, 'OPEN', [
                'con_1' => $con1,
                'con_2' => $con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            if ($b = $pickLatest($r['items'] ?? [], $con2)) return $b;

            // สุดท้าย WAITING
            $r = $glog->queryGameLogs($gameUser, $company, 'WAITING', [
                'con_1' => $con1,
                'con_2' => $con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            if ($b = $pickLatest($r['items'] ?? [], $con2)) return $b;

            return null;
        };

        // กันซ้ำแบบ idempotent สำหรับ ADJUST: มี ADJUST เดิมที่ amount = newBet, con_4=null อยู่แล้ว
        $hasSameAdjust = function (string $con1, string $con2, float $newBet) use ($glog, $gameUser, $company): bool {
            $res = $glog->queryGameLogs($gameUser, $company, 'ADJUST', [
                'con_1' => $con1,
                'con_2' => $con2,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_3'] ?? null) !== null) continue;
                if (($it['con_4'] ?? null) !== null) continue;
                if ((float)($it['amount'] ?? 0) === (float)$newBet) return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = (string)($txn['id'] ?? '');
            $roundId = (string)($txn['roundId'] ?? '');
            $status = (string)($txn['status'] ?? 'ADJUST');  // โดยปกติเป็น ADJUST
            $newBet = (float)max(0, (float)($txn['betAmount'] ?? 0)); // กันค่าติดลบ

            if ($txnId === '' || $roundId === '') {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            // มี ADJUST เดิมที่ค่าเท่ากันแล้ว? → ถือว่า idempotent
            if ($hasSameAdjust($txnId, $roundId, $newBet)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // หา base ล่าสุด (ADJUST > OPEN > WAITING)
            $base = $findLatestBase($txnId, $roundId);
            if (!$base) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            $baseLogId = (string)($base['log_id'] ?? '');
            $baseAmount = (float)($base['amount'] ?? 0.0);
            $diff = $newBet - $baseAmount;

            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $diff, $newBet, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($diff > 0) {
                        if ((float)$member->{$this->balances} < $diff) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
                            ];
                        }
                        $member->decrement($this->balances, $diff);
                    } elseif ($diff < 0) {
                        $member->increment($this->balances, abs($diff));
                    }
                    $member->refresh();

                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

                    // log ADJUST เก็บค่า "ยอดใหม่" (newBet) ไว้ที่ amount
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,          // 'ADJUST'
                        'response' => 'in',
                        'amount' => (float)$newBet,  // บันทึกยอดใหม่ เป็น state ล่าสุด
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,             // ตามกติกา
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$after,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'log' => $logData,
                        'after_balance' => $after,
                    ];
                }, 1);

                if (!$txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // เขียน ADJUST ใหม่ และ "ปิด" base เดิม (ชี้ con_4 ไปยัง ADJUST ใหม่นี้)
                $newAdjId = $glog->saveGameLogToRedis($txResult['log']);
                if ($baseLogId !== '') {
                    $glog->updateLogField($baseLogId, 'con_4', 'ADJUST_' . $newAdjId, $gameUser, $company);
                }

                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txResult['after_balance']);
                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


}