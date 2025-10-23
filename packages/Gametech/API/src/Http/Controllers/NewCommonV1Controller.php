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

class NewCommonV1Controller extends AppBaseController
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

        $productId = session('productId');
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

    public function getBalance(Request $request)
    {
        $session = $request->all();
        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 30001);
        }
        $param = $this->responseData($session['id'], $this->member->user_name, $session['productId'], 0, $this->member->balance);

        $this->gameLogRedis->saveGameLogToRedis([
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
        $session = $request->all();
        $param   = [];
        $txns    = (array) ($session['txns'] ?? []);

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;                  // <-- ใช้ service ฝั่ง Redis
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $oldBalance = (float) $this->member->balance;
        $amount     = collect($txns)->sum(fn ($t) => (float) ($t['betAmount'] ?? 0));

        // ===== main log (response=in) ลง Redis =====
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [], // อัปเดตตอนจบ
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'betmain',
            'response'        => 'in',
            'amount'          => $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null, // << ตามกติกา: ยกเว้น settleBets นอกนั้นเป็น null
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
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
                'con_1'  => (string) $con1,
                'con_2'  => (string) $con2,
                'limit'  => 10,
                'offset' => 0,
                'order'  => 'desc',
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
            $txnId      = $txn['id'] ?? null;
            $roundId    = $txn['roundId'] ?? null;
            $status     = $txn['status'] ?? null;            // เช่น OPEN / WAITING / ...
            $betAmount  = (float) ($txn['betAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำ (AND: con_1 & con_2, และ con_3 ต้อง null)
            if ($isDup((string) $status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            // 2) ถ้าเป็น OPEN → ข้ามหัก ถ้ามี WAITING คู่เดียวกันอยู่แล้ว (AND + con_3=null)
            if ($status === 'OPEN' && $txnId !== null && $roundId !== null) {
                if ($isDup('WAITING', (string) $txnId, (string) $roundId)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $this->member->balance,
                        ];

                    // log ย่อย (ไม่แตะ balance)
                    $glog->saveGameLogToRedis([
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $betAmount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null, // << null ตามกติกา
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $this->member->balance,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ]);

                    break;
                }
            }

            // 3) ข้ามการอัปเดตยอด แต่ต้องเขียนล็อก
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];

                $glog->saveGameLogToRedis([
                    'input'           => $txn,
                    'output'          => $param,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => $status,
                    'response'        => 'in',
                    'amount'          => $betAmount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null, // << null
                    'con_4'           => null,
                    'before_balance'  => (float) $oldBalance,
                    'after_balance'   => (float) $this->member->balance,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);

                break;
            }

            // 4) หักยอดแบบ TX + lockForUpdate (DB) แต่ล็อกเหตุการณ์ลง Redis
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $company, $gameUser) {
                    $member  = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $current = (float) $member->{$this->balances};
                    $after   = $current - $betAmount;

                    if ($after < 0) {
                        return [
                            'ok'             => false,
                            'param'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current),
                            'log'            => null,
                            'member_balance' => $current,
                        ];
                    }

                    if ($betAmount > 0) {
                        $member->decrement($this->balances, $betAmount);
                        $member->refresh();
                        $after = (float) $member->{$this->balances};
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $after,
                        ];

                    // เตรียม log ย่อย (จะบันทึกนอก TX)
                    $log = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $betAmount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null, // << null
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'log'            => $log,
                        'member_balance' => (float) $after,
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // log ย่อยลง Redis
                $glog->saveGameLogToRedis($txResult['log']);

                // (มีระบบ seamless ก็เก็บไว้ได้)
//                LogSeamless::log(
//                    $company,
//                    $gameUser,
//                    $txn,
//                    $oldBalance,
//                    $txResult['member_balance']
//                );

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
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;
        $amount     = collect($txns)->sum(fn ($t) => (float) ($t['payoutAmount'] ?? 0));

        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'settledmain',
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

        $isDupSettled = function (?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null)   continue; // กันซ้ำเฉพาะที่ยังไม่ถูกปิด
                return true;
            }
            return false;
        };

        $getOpenOrWaiting = function (string $con1, string $con2) use ($glog, $gameUser, $company): array {
            $fetch = function (string $method) use ($glog, $gameUser, $company, $con1, $con2) {
                $r = $glog->queryGameLogs($gameUser, $company, $method, [
                    'con_1' => $con1,
                    'con_2' => $con2,
                    'limit' => 100,
                    'order' => 'desc',
                ]);
                $out = [];
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_3'] ?? null) !== null)   continue; // OPEN/WAITING ต้อง con_3=null
                    if (($it['con_4'] ?? null) !== null)   continue; // ยังไม่ปิด
                    $out[] = $it;
                }
                return $out;
            };
            return array_merge($fetch('OPEN'), $fetch('WAITING'));
        };

        foreach ($txns as $txn) {
            $isSingleState     = (bool) ($txn['isSingleState'] ?? false);
            $skipBalanceUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);
            $isFeature         = (bool) ($txn['isFeature'] ?? false);
            $isFeatureBuy      = (bool) ($txn['isFeatureBuy'] ?? false);
            $isEndRound        = array_key_exists('isEndRound', $txn) ? (bool) $txn['isEndRound'] : true;
            $ismulti           = ($isFeature || $isFeatureBuy || ! $isEndRound);

            $transactionType   = (string) ($txn['transactionType'] ?? 'BY_TRANSACTION');

            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $payout  = (float) ($txn['payoutAmount'] ?? 0);
            $betAmt  = (float) ($txn['betAmount'] ?? 0);

            // 1) กันซ้ำของ SETTLED (ไม่ผูกกับ con_3; ใช้ method+con1+con2+con4=null)
            if ($isDupSettled($txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                );
                break;
            }

            // 2) หา base OPEN/WAITING (ไว้ปิดทีหลัง)
            $baseLogs = [];
            if ($txnId && $roundId) {
                if ($transactionType === 'BY_ROUND') {
                    $r1 = $glog->queryGameLogs($gameUser, $company, 'OPEN',   ['con_2' => (string) $roundId, 'limit' => 200, 'order' => 'desc']);
                    $r2 = $glog->queryGameLogs($gameUser, $company, 'WAITING',['con_2' => (string) $roundId, 'limit' => 200, 'order' => 'desc']);
                    $ok = fn($it) => (($it['response'] ?? '') === 'in') && (($it['con_3'] ?? null) === null) && (($it['con_4'] ?? null) === null);
                    $baseLogs = array_values(array_filter(array_merge($r1['items'] ?? [], $r2['items'] ?? []), $ok));
                } else {
                    $baseLogs = $getOpenOrWaiting((string) $txnId, (string) $roundId);
                }
            }
            // ---- เงื่อนไขสำคัญ ----
            if (empty($baseLogs) && ! $isSingleState) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance
                );
                break;
            }

            // 3) single-state: ถ้ายังไม่มี OPEN และไม่ skip → หัก bet ก่อน + เปิดรอย OPEN (con_3=null)
            if ($isSingleState) {
                $dupOpen = false;
                $chk = $glog->queryGameLogs($gameUser, $company, 'OPEN', [
                    'con_1' => (string) $txnId,
                    'con_2' => (string) $roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($chk['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) {
                        $dupOpen = true; break;
                    }
                }

                if (! $dupOpen) {
                    if (! $skipBalanceUpdate) {
                        try {
                            $res = DB::transaction(function () use ($betAmt, $session, $company) {
                                $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                                $newBal = (float) $member->{$this->balances} - (float) $betAmt;
                                if ($newBal < 0) {
                                    return [
                                        'ok'    => false,
                                        'param' => $this->responseData(
                                            $session['id'] ?? null,
                                            $session['username'] ?? '',
                                            $company,
                                            10002,
                                            (float) $member->{$this->balances}
                                        ),
                                    ];
                                }
                                if ($betAmt > 0) {
                                    $member->decrement($this->balances, $betAmt);
                                    $member->refresh();
                                }
                                return ['ok' => true, 'bal' => (float) $member->{$this->balances}];
                            }, 1);

                            if (! $res['ok']) {
                                $param = $res['param'];
                                break;
                            }
                        } catch (\Throwable $e) {
                            $param = $this->responseData(
                                    $session['id'] ?? null,
                                    $session['username'] ?? '',
                                    $company,
                                    50001,
                                    (float) $this->member->balance
                                ) + ['message' => $e->getMessage()];
                            break;
                        }
                    }

                    // เปิดรอย OPEN ไว้ (con_3=null)
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

                    // รีเฟรชฐาน
                    $baseLogs = $getOpenOrWaiting((string) $txnId, (string) $roundId);
                }
            }

            // 4) เครดิต payout + เขียน SETTLED (con_3 = $ismulti)
            $settleRes = [
                'ok'             => true,
                'param'          => null,
                'member_balance' => (float) $this->member->balance,
                'log'            => null,
            ];

            if (! $skipBalanceUpdate) {
                try {
                    $settleRes = DB::transaction(function () use ($session, $txn, $txnId, $roundId, $payout, $oldBalance, $company, $gameUser, $ismulti) {
                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                        if ($payout > 0) $member->increment($this->balances, $payout);
                        $member->refresh();

                        $after = (float) $member->{$this->balances};
                        $param = $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                            ) + [
                                'balanceBefore' => (float) $oldBalance,
                                'balanceAfter'  => (float) $after,
                            ];

                        $log = [
                            'input'           => $txn,
                            'output'          => $param,
                            'company'         => $company,
                            'game_user'       => $gameUser,
                            'method'          => 'SETTLED',
                            'response'        => 'in',
                            'amount'          => (float) $payout,
                            'con_1'           => $txnId,
                            'con_2'           => $roundId,
                            'con_3'           => (bool) $ismulti,
                            'con_4'           => null,
                            'before_balance'  => (float) $oldBalance,
                            'after_balance'   => (float) $after,
                            'date_create'     => $this->now->toDateTimeString(),
                            'expireAt'        => $this->expireAt,
                        ];

                        return [
                            'ok'             => true,
                            'param'          => $param,
                            'member_balance' => $after,
                            'log'            => $log,
                        ];
                    }, 1);
                } catch (\Throwable $e) {
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                        ) + ['message' => $e->getMessage()];
                    break;
                }
            } else {
                // ไม่อัปเดตยอด แต่ออก res + เตรียม log
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float) $this->member->balance
                    ) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];

                $settleRes = [
                    'ok'             => true,
                    'param'          => $param,
                    'member_balance' => (float) $this->member->balance,
                    'log'            => [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => 'SETTLED',
                        'response'        => 'in',
                        'amount'          => (float) $payout,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => (bool) $ismulti,
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $this->member->balance,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ],
                ];
            }

            if (! $settleRes['ok']) {
                $param = $settleRes['param'];
                break;
            }

            // 5) เขียน SETTLED ลง Redis แล้วปิดฐาน OPEN/WAITING ด้วย con_4 = "SETTLED_<id>"
            $settledId = $glog->saveGameLogToRedis($settleRes['log']);
            foreach ($baseLogs as $b) {
                $glog->updateLogField($b['log_id'], 'con_4', 'SETTLED_' . $settledId, $gameUser, $company);
            }

            // (ถ้ามี LogSeamless ให้ใส่ตรงนี้)
            // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $settleRes['member_balance']);
            $param = $settleRes['param'];
        }

        return $finalize($param);
    }

    public function adjustBets(Request $request)
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
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;
        $amount     = collect($txns)->sum(fn ($t) => (float) ($t['adjustAmount'] ?? 0));

        // main log (response=in) ลง Redis
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'adjustbetmain',
            'response'        => 'in',
            'amount'          => $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper: กันซ้ำ adjust (method+con_1+con_2+con_4=null)
        $isDupAdjust = function (?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, 'ADJUSTED', [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null)   continue;
                return true;
            }
            return false;
        };

        // หา log base ของ bet (OPEN/WAITING) เพื่อปิด con_4 ใน adjust
        $getBaseBet = function (string $con1, string $con2) use ($glog, $gameUser, $company): array {
            $fetch = function (string $method) use ($glog, $gameUser, $company, $con1, $con2) {
                $r = $glog->queryGameLogs($gameUser, $company, $method, [
                    'con_1' => $con1,
                    'con_2' => $con2,
                    'limit' => 100,
                    'order' => 'desc',
                ]);
                $out = [];
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_3'] ?? null) !== null)   continue;
                    if (($it['con_4'] ?? null) !== null)   continue;
                    $out[] = $it;
                }
                return $out;
            };
            return array_merge($fetch('OPEN'), $fetch('WAITING'));
        };

        foreach ($txns as $txn) {
            $txnId      = $txn['id'] ?? null;
            $roundId    = $txn['roundId'] ?? null;
            $status     = $txn['status'] ?? null; // เช่น ADJUSTED
            $adjustAmount = (float) ($txn['adjustAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำ ADJUSTED
            if ($isDupAdjust($txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                );
                break;
            }

            // 2) หา base bet (OPEN/WAITING)
            $baseLogs = [];
            if ($txnId && $roundId) {
                $baseLogs = $getBaseBet((string) $txnId, (string) $roundId);
            }
            if (empty($baseLogs)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance
                );
                break;
            }

            // 3) ข้ามการอัปเดตยอด แต่ต้องเขียนล็อก
            if ($skipUpdate) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float) $this->member->balance
                    ) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];

                $adjustLog = [
                    'input'           => $txn,
                    'output'          => $param,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => 'ADJUSTED',
                    'response'        => 'in',
                    'amount'          => (float) $adjustAmount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $oldBalance,
                    'after_balance'   => (float) $this->member->balance,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ];
                $adjustedId = $glog->saveGameLogToRedis($adjustLog);

                foreach ($baseLogs as $b) {
                    $glog->updateLogField($b['log_id'], 'con_4', 'ADJUSTED_' . $adjustedId, $gameUser, $company);
                }

                break;
            }

            // 4) ปรับยอด balance แบบ TX + log ลง redis
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $txnId, $roundId, $adjustAmount, $oldBalance, $company, $gameUser) {
                    $member  = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $current = (float) $member->{$this->balances};
                    $after   = $current + $adjustAmount;

                    if ($after < 0) {
                        return [
                            'ok'             => false,
                            'param'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current),
                            'log'            => null,
                            'member_balance' => $current,
                        ];
                    }

                    if ($adjustAmount != 0) {
                        $member->increment($this->balances, $adjustAmount);
                        $member->refresh();
                        $after = (float) $member->{$this->balances};
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $after,
                        ];

                    $log = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => 'ADJUSTED',
                        'response'        => 'in',
                        'amount'          => (float) $adjustAmount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'log'            => $log,
                        'member_balance' => (float) $after,
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // log ลง Redis
                $adjustedId = $glog->saveGameLogToRedis($txResult['log']);

                foreach ($baseLogs as $b) {
                    $glog->updateLogField($b['log_id'], 'con_4', 'ADJUSTED_' . $adjustedId, $gameUser, $company);
                }

                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                    ) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelBets(Request $request)
    {
        $session = $request->all();
        $param   = [];
        $isArray = false;

        if (! $this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        // main log เปิดหัว (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'cancelmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper: กันซ้ำ CANCEL/REJECT (method+con_1+con_2+con_4=null)
        $isDupCancel = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue; // กันซ้ำเฉพาะที่ยังไม่ถูกปิด
                return true;
            }
            return false;
        };

        // หา log base ของ bet (OPEN/WAITING) เพื่อปิด con_4 ใน cancel
        $getBaseBet = function (string $method, ?string $txnId, ?string $roundId, string $txnType) use ($glog, $gameUser, $company) {
            if ($txnType === 'BY_ROUND') {
                $r = $glog->queryGameLogs($gameUser, $company, $method, [
                    'con_2' => (string) $roundId,
                    'limit' => 200,
                    'order' => 'desc',
                ]);
                $out = [];
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_3'] ?? null) !== null) continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $out[] = $it;
                }
                return $out;
            } else {
                $r = $glog->queryGameLogs($gameUser, $company, $method, [
                    'con_1' => (string) $txnId,
                    'limit' => 1,
                    'order' => 'desc',
                ]);
                $out = [];
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_3'] ?? null) !== null) continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $out[] = $it;
                }
                return $out;
            }
        };

        foreach ($txns as $txn) {
            $txnId     = $txn['id'] ?? null;
            $roundId   = $txn['roundId'] ?? null;
            $status    = $txn['status'] ?? null; // CANCELLED / REJECT
            $txnType   = $txn['transactionType'] ?? 'BY_TRANSACTION';
            $reqAmount = (float) ($txn['betAmount'] ?? 0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN';

            // 1) กันซ้ำ: เคย cancel รายการนี้แล้วหรือยัง
            if ($isDupCancel($status, $txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                );
                break;
            }

            // 2) หา base logs ที่เป็นต้นตอเงินเดิมพันจะถูกยกเลิก
            $logs = $getBaseBet($logMethod, $txnId, $roundId, $txnType);

            if (empty($logs)) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance
                );
                break;
            }

            $baseAmount = 0;
            if ($txnType === 'BY_ROUND') {
                $baseAmount = (float) array_sum(array_map(fn($l) => (float) ($l['amount'] ?? 0), $logs));
                $isArray = true;
            } else {
                $baseAmount = (float) ($logs[0]['amount'] ?? 0);
                $isArray = false;
            }

            // 3) ทำยอดเงินภายใต้ TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $reqAmount, $baseAmount, $oldBalance,$company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // ตรรกะ:
                    // - ถ้า reqAmount > baseAmount : decrement(baseAmount) แล้ว increment(reqAmount)
                    // - ถ้า reqAmount <= baseAmount : increment(reqAmount)
                    if ($reqAmount > $baseAmount) {
                        $newBal = $member->{$this->balances} - $baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok'    => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null,
                                    $session['username'] ?? '',
                                    $company,
                                    10002,
                                    $member->{$this->balances}
                                ),
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
                            $company,
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
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

                // เขียน log cancel (redis)
                $logId = $glog->saveGameLogToRedis($txRes['logData']);

                // อัปเดต con_4 ของ base logs
                if ($isArray) {
                    foreach ($logs as $lg) {
                        $glog->updateLogField($lg['log_id'], 'con_4', ($status ?? 'CANCEL') . '_' . $logId, $gameUser, $company);
                    }
                } else {
                    $glog->updateLogField($logs[0]['log_id'], 'con_4', ($status ?? 'CANCEL') . '_' . $logId, $gameUser, $company);
                }

                // LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function rollback(Request $request)
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
        $glog     = $this->gameLogRedis; // ใช้ Redis service เท่านั้น
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $oldBalance = (float) $this->member->balance;

        // main log (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'rollbackmain',
            'response'        => 'in',
            'amount'          => 0,
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

        // -------- helpers (Redis) --------
        $dupRollbackByRound = function (string $status, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 50,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null)   continue;
                return true;
            }
            return false;
        };

        $findBaseByRound = function (string $roundId) use ($glog, $gameUser, $company) {
            $cands = [];
            foreach (['SETTLED', 'REFUND'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_2' => $roundId,
                    'limit' => 100,
                    'order' => 'desc',
                ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null)   continue;
                    $cands[] = $it;
                }
            }
            if (!$cands) return null;
            usort($cands, fn($a,$b) => strcmp((string)($b['date_create'] ?? ''),(string)($a['date_create'] ?? '')));
            return $cands[0];
        };

        $findBaseByTxn = function (string $txnId) use ($glog, $gameUser, $company) {
            $cands = [];
            foreach (['SETTLED', 'REFUND'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_1' => $txnId,
                    'limit' => 100,
                    'order' => 'desc',
                ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null)   continue;
                    $cands[] = $it;
                }
            }
            if (!$cands) return null;
            usort($cands, fn($a,$b) => strcmp((string)($b['date_create'] ?? ''),(string)($a['date_create'] ?? '')));
            return $cands[0];
        };

        $clearWaitingOpenPointingTo = function (string $tag) use ($glog, $gameUser, $company) {
            foreach (['WAITING', 'OPEN'] as $m) {
                $r = $glog->queryGameLogs($gameUser, $company, $m, [
                    'limit' => 300,
                    'order' => 'desc',
                ]);
                foreach (($r['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) === $tag) {
                        $glog->updateLogField($it['log_id'], 'con_4', null, $gameUser, $company);
                    }
                }
            }
        };

        foreach ((array) ($session['txns'] ?? []) as $txn) {
            $status  = (string) ($txn['status'] ?? 'ROLLBACK');
            $txnType = (string) ($txn['transactionType'] ?? 'BY_TRANSACTION'); // BY_TRANSACTION | BY_ROUND
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;

            // --- BY_ROUND เท่านั้นที่เช็กซ้ำ ROLLBACK (ของเดิม) ---
            if ($txnType === 'BY_ROUND') {
                if ($dupRollbackByRound($status, $txnId, $roundId)) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                    );
                    break;
                }
            }

            // --- หา baseLog ตามของเดิม ---
            $baseLog = null;
            if ($txnType === 'BY_ROUND') {
                if (! $roundId) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance
                    );
                    break;
                }
                $baseLog = $findBaseByRound((string) $roundId);
                if (! $baseLog) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance
                    );
                    break;
                }
            } else { // BY_TRANSACTION
                if (! $txnId) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                    );
                    break;
                }
                $baseLog = $findBaseByTxn((string) $txnId); // <<< ดูด้วย con_1 เท่านั้น (ไม่ใช้ con_2) ตามของเดิม
                if (! $baseLog) {
                    $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance
                    );
                    break;
                }
            }

            // --- คำนวณยอด rollback ตามของเดิม ---
            $rollbackAmount = ($baseLog['method'] === 'SETTLED')
                ? (float) ($txn['payoutAmount'] ?? 0)
                : (float) ($txn['betAmount'] ?? 0);

            try {
                // 1) ปรับยอด (ไม่เช็คติดลบ) + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $rollbackAmount, $oldBalance, $company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float) $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    return [
                        'param'          => $param,
                        'member_balance' => (float) $member->{$this->balances},
                    ];
                }, 1);

                // 2) เขียน rollback log บน Redis (นอก TX)
                $rbId = $glog->saveGameLogToRedis([
                    'input'           => $txn,
                    'output'          => $txRes['param'],
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => $status, // ROLLBACK
                    'response'        => 'in',
                    'amount'          => (float) $rollbackAmount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $oldBalance,
                    'after_balance'   => (float) $txRes['member_balance'],
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);

                // 3) ผูก baseLog → con_4 ชี้ไปยัง rollback log
                $baseTag = $baseLog['method'] . '_' . $baseLog['log_id'];
                $glog->updateLogField($baseLog['log_id'], 'con_4', $status . '_' . $rbId, $gameUser, $company);

                // 4) เคลียร์ WAITING/OPEN ที่เคยชี้ไปหา base เดิม ให้เป็น null (ตามของเดิม)
                $clearWaitingOpenPointingTo($baseTag);

                // 5) seamless log (ไว้ตามต้องการ)
                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function winRewards(Request $request)
    {
        $session = $request->all();
        Log::channel('gamelog')->debug("Start Winreward-----------", ['session' => $session]);
        $param = [];

        if (! $this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;
        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        // main log เปิดหัว (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'winrewardmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper: กันซ้ำ winreward (method+con_1+con_2+con_4=null)
        $isDupWin = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
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
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null;
            $payout  = (float) ($txn['payoutAmount'] ?? 0);

            // กันซ้ำ
            if ($isDupWin($status, $txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $oldBalance, $company,$gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($payout > 0) {
                        $member->increment($this->balances, $payout);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
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
                    $param = $txRes['param'] ?? $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        10998,
                        (float) $this->member->balance
                    );
                    break;
                }

                // เขียน log นอก TX
                $glog->saveGameLogToRedis($txRes['logData']);

                // (มี LogSeamless ให้ใส่ตรงนี้)
                // LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function voidSettled(Request $request)
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
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;
        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        // main log เปิดหัว (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'voidsettledmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDupVoid = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return true;
            }
            return false;
        };

        $getSettled = function (string $txnType, ?string $txnId, ?string $roundId) use ($glog, $gameUser, $company) {
            if ($txnType === 'BY_ROUND') {
                $r = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                    'con_2' => (string) $roundId,
                    'limit' => 1,
                    'order' => 'desc',
                ]);
            } else {
                $r = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                    'con_1' => (string) $txnId,
                    'limit' => 1,
                    'order' => 'desc',
                ]);
            }
            foreach (($r['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return $it;
            }
            return null;
        };

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = $txn['status'] ?? null;
            $type    = $txn['transactionType'] ?? 'BY_TRANSACTION';

            if ($isDupVoid($status, $txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            $settledLog = $getSettled($type, $txnId, $roundId);

            if (! $settledLog) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20001,
                    (float) $this->member->balance
                );
                break;
            }

            $betAmount = (float) ($txn['betAmount'] ?? 0);
            $payout    = (float) ($txn['payoutAmount'] ?? 0);
            $netDelta  = $betAmount - $payout;

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $netDelta, $oldBalance, $company,$gameUser,$txnId,$roundId) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $candidate = (float) $member->{$this->balances} + $netDelta;
                    if ($candidate < 0) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null,
                                $session['username'] ?? '',
                                $company,
                                10002,
                                $member->{$this->balances}
                            ),
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
                            $company,
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $netDelta,
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

                $logId = $glog->saveGameLogToRedis($txRes['logData']);
                $glog->updateLogField($settledLog['log_id'], 'con_4', ($status ?? 'VOID_SETTLED') . '_' . $logId, $gameUser, $company);

                // LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function placeTips(Request $request)
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
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;
        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        // main log เปิดหัว (Redis)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'placetipmain',
            'response'        => 'in',
            'amount'          => 0,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float) $this->member->balance,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        $isDupTip = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1' => (string) $con1,
                'con_2' => (string) $con2,
                'limit' => 30,
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
            $txnId      = $txn['id'] ?? null;
            $roundId    = $txn['roundId'] ?? null;
            $status     = $txn['status'] ?? null;
            $amount     = (float) ($txn['betAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            if ($isDupTip($status, $txnId, $roundId)) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            if ($skipUpdate) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        0,
                        (float) $this->member->balance
                    ) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];

                $glog->saveGameLogToRedis([
                    'input'           => $txn,
                    'output'          => $param,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => $status,
                    'response'        => 'in',
                    'amount'          => $amount,
                    'con_1'           => $txnId,
                    'con_2'           => $roundId,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => $oldBalance,
                    'after_balance'   => (float) $this->member->balance,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);
                // LogSeamless::log($company, $gameUser, $txn, $oldBalance, $this->member->balance);
                continue;
            }

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $company,$gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($member->{$this->balances} < $amount) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null,
                                $session['username'] ?? '',
                                $company,
                                10002,
                                $member->{$this->balances}
                            ),
                        ];
                    }

                    if ($amount > 0) {
                        $member->decrement($this->balances, $amount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $member->{$this->balances},
                        ];

                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
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

                $glog->saveGameLogToRedis($txRes['logData']);
                // LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelTips(Request $request)
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

        // --- เตรียมตัวแปรหลักที่ต้องใช้ ---
        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;
        $company = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;
        $glog = $this->gameLogRedis;

        $amount = collect($txns)->sum(fn($t) => (float)($t['cancelTipAmount'] ?? 0));

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => $param,
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'canceltipmain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => (float) $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $cancelTipAmount = (float)($txn['cancelTipAmount'] ?? 0);
            $skipBalanceUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // --- กันซ้ำ ---
            $dup = $glog->hasDuplicate(
                $gameUser,
                $company,
                $status,
                $txnId,
                $roundId,
                'in'
            );

            if ($dup) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            // --- ไม่อัปเดตยอด แต่ต้องเขียน log ---
            if ($skipBalanceUpdate) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        0,
                        (float) $this->member->balance
                    ) + [
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
                    'amount' => $cancelTipAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => $oldBalance,
                    'after_balance' => (float) $this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);
                break;
            }

            // --- ปรับยอด + เขียน log ---
            try {
                $result = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $cancelTipAmount, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $newBalance = (float) $member->{$this->balances} + $cancelTipAmount;
                    if ($newBalance < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null,
                                $session['username'] ?? '',
                                $company,
                                10002,
                                (float)$member->{$this->balances}
                            ),
                            'log' => null,
                            'member_balance' => (float)$member->{$this->balances},
                        ];
                    }
                    if ($cancelTipAmount > 0) {
                        $member->increment($this->balances, $cancelTipAmount);
                        $member->refresh();
                    }

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float) $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];
                    $log = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $cancelTipAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => (float)$member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];
                    return [
                        'ok' => true,
                        'param' => $param,
                        'log' => $log,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$result['ok']) {
                    $param = $result['param'];
                    break;
                }

                $glog->saveGameLogToRedis($result['log']);
                $param = $result['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        $glog->updateLogField($mainLogId, 'output', $param, $gameUser, $company);
        return $param;
    }

    public function adjustBalance(Request $request)
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

        // --- เตรียมตัวแปรหลักที่ต้องใช้ ---
        $txns = (array)($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;
        $company = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;
        $glog = $this->gameLogRedis;

        $amount = collect($txns)->sum(fn($t) => (float)($t['adjustBalanceAmount'] ?? 0));

        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => $param,
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'adjustbalancemain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => (float) $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $adjustBalanceAmount = (float)($txn['adjustBalanceAmount'] ?? 0);
            $skipBalanceUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // --- กันซ้ำ ---
            $dup = $glog->hasDuplicate(
                $gameUser,
                $company,
                $status,
                $txnId,
                $roundId,
                'in'
            );

            if ($dup) {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            // --- ไม่อัปเดตยอด แต่ต้องเขียน log ---
            if ($skipBalanceUpdate) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        0,
                        (float) $this->member->balance
                    ) + [
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
                    'amount' => $adjustBalanceAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => $oldBalance,
                    'after_balance' => (float) $this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);
                break;
            }

            // --- ปรับยอด + เขียน log ---
            try {
                $result = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $adjustBalanceAmount, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $newBalance = (float) $member->{$this->balances} + $adjustBalanceAmount;
                    if ($newBalance < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null,
                                $session['username'] ?? '',
                                $company,
                                10002,
                                (float)$member->{$this->balances}
                            ),
                            'log' => null,
                            'member_balance' => (float)$member->{$this->balances},
                        ];
                    }
                    if ($adjustBalanceAmount != 0) {
                        $member->increment($this->balances, $adjustBalanceAmount);
                        $member->refresh();
                    }
                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float) $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];
                    $log = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $adjustBalanceAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => (float)$member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];
                    return [
                        'ok' => true,
                        'param' => $param,
                        'log' => $log,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$result['ok']) {
                    $param = $result['param'];
                    break;
                }

                $glog->saveGameLogToRedis($result['log']);
                $param = $result['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float) $this->member->balance
                    ) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        $glog->updateLogField($mainLogId, 'output', $param, $gameUser, $company);
        return $param;
    }
}