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

class NewCommonV3Controller extends AppBaseController
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

        $param = $this->responseData(
            $session['id'],
            $this->member->user_name,
            $session['productId'],
            0,
            $this->member->balance
        );

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

    // --- ทุกฟังก์ชันต่อไปนี้ ใช้ Redis Service แทน Mongo ---

    public function placeBets(Request $request)
    {
        $session = $request->all();
        $param   = [];
        $txns    = (array) ($session['txns'] ?? []);

        if (! $this->member) {
            return $this->responseData(
                $session['id'] ?? null,
                $session['username'] ?? '',
                $session['productId'] ?? '',
                10001
            );
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;                // ใช้ Redis service
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $oldBalance = (float) $this->member->balance;
        $totalBet   = 0.0;
        foreach ($txns as $t) $totalBet += (float) ($t['betAmount'] ?? 0);

        // A) main log (เร็ว-เบา)
        $mainLogId = $glog->saveGameLogToRedis([
            'input'          => $session,
            'output'         => [],
            'company'        => $company,
            'game_user'      => $gameUser,
            'method'         => 'betmain',
            'response'       => 'in',
            'amount'         => (float) $totalBet,
            'con_1'          => $session['id'] ?? null,
            'con_2'          => $company,
            'con_3'          => null,
            'con_4'          => null,
            'before_balance' => $oldBalance,
            'after_balance'  => (float) $this->member->balance,
            'date_create'    => $this->now->toDateTimeString(),
            'expireAt'       => $this->expireAt,
        ]);

        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // B) กันซ้ำและหักเงินทีละ txn
        foreach ($txns as $txn) {
            $txnId     = $txn['id']      ?? null;
            $roundId   = $txn['roundId'] ?? null;
            $betAmount = (float) ($txn['betAmount'] ?? 0);
            $status    = (string) ($txn['status'] ?? 'BET');   // ชื่อ method ของ log รายการ

            // B1) idempotency: กันยิงซ้ำด้วย con_1(+con_2)
            $onceOk = $glog->reserveOnce(
                $status,
                $company,
                $gameUser,
                $txnId,
                $roundId,
                300 // TTL วินาที
            );
            if (! $onceOk) {
                // ยิงซ้ำ → ตอบ 20002 ใช้ยอดปัจจุบันแล้วจบ
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20002,
                    (float) $this->member->balance
                );
                break;
            }

            // B2) หักเงินแบบ TX สั้น ๆ
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $current = (float) $member->{$this->balances};

                    if ($betAmount > 0) {
                        if ($current < $betAmount) {
                            // ยอดไม่พอ
                            return [
                                'ok'     => false,
                                'param'  => $this->responseData(
                                    $session['id'] ?? null, $session['username'] ?? '', $company, 10002, $oldBalance
                                ),
                                'log'    => null,
                                'after'  => $current,
                            ];
                        }
                        $member->decrement($this->balances, $betAmount);
                        $member->refresh();
                        $after = (float) $member->{$this->balances};
                    } else {
                        $after = $current;
                    }

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => (float) $oldBalance,
                            'balanceAfter'  => (float) $after,
                        ];

                    // เตรียม log ย่อย (จะบันทึกนอก TX)
                    $log = [
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $company,
                        'game_user'      => $gameUser,
                        'method'         => $status,
                        'response'       => 'in',
                        'amount'         => (float) $betAmount,
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => null,
                        'con_4'          => null,
                        'before_balance' => (float) $oldBalance,
                        'after_balance'  => (float) $after,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ];

                    return [
                        'ok'    => true,
                        'param' => $param,
                        'log'   => $log,
                        'after' => (float) $after,
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // B3) บันทึก log ย่อยลง Redis (นอก TX)
                $glog->saveGameLogToRedis($txResult['log']);

                // เก็บค่า param ล่าสุดเพื่อตอบรวม
                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param ?: $this->responseData(
            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float) $this->member->balance
        ));
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
        $company  = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns       = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // main log ตามสไตล์ไฟล์นี้
        $mainLogId = $glog->saveGameLogToRedis([
            'input'          => $session,
            'output'         => [],
            'company'        => $company,
            'game_user'      => $gameUser,
            'method'         => 'settlemain',
            'response'       => 'in',
            'amount'         => 0,
            'con_1'          => $session['id'] ?? null,
            'con_2'          => $company,
            'con_3'          => null,
            'con_4'          => null,
            'before_balance' => (float)$oldBalance,
            'after_balance'  => (float)$this->member->balance,
            'date_create'    => $this->now->toDateTimeString(),
            'expireAt'       => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ===== Helpers: index OPEN แบบ O(1) =====
        $openKeyOf = function (string $roundId) use ($company) {
            return "gl:open:{$company}:{$this->member->code}:{$roundId}";
        };

        foreach ($txns as $txn) {
            $txnId    = $txn['id']       ?? null;
            $roundId  = (string)($txn['roundId'] ?? '');
            $betAmt   = (float)($txn['betAmount'] ?? 0);
            $payout   = (float)($txn['payoutAmount'] ?? 0);
            $status   = (string)($txn['status'] ?? 'SETTLED'); // ชื่อ method ของ log ปิดรอบ

            // กันซ้ำระดับ txn (con_1+con_2)
            $onceOk = $glog->reserveOnce($status, $company, $gameUser, $txnId, $roundId, 300);
            if (! $onceOk) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                );
                break;
            }

            // 1) เช็คว่ามี OPEN อยู่ก่อนแล้วไหม (BY_ROUND เงื่อนไขหลักคือ con_2 และต้อง con_4 = null)
            $openKey = $roundId !== '' ? $openKeyOf($roundId) : null;
            $hasOpen = $openKey ? (bool) \Illuminate\Support\Facades\Redis::get($openKey) : false;

            // 2) เคสไม่มี OPEN แต่ provider ส่ง bet + payout มาพร้อม (บางค่ายยิงจบในทีเดียว)
            $needCombinedPlaceAndSettle = (!$hasOpen && $betAmt > 0);

            try {
                // ทำยอดแบบ TX สั้น
                $settleResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmt, $payout, $needCombinedPlaceAndSettle, $company,$oldBalance) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $before = (float)$member->{$this->balances};

                    // 2.1 ถ้าไม่มี OPEN แต่มี betAmount → ต้องตัดก่อน (single-state combined)
                    if ($needCombinedPlaceAndSettle) {
                        if ($betAmt <= 0 || $before < $betAmt) {
                            // เงินไม่พอสำหรับหัก bet
                            return [
                                'ok'    => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$oldBalance
                                ),
                            ];
                        }
                        $member->decrement($this->balances, $betAmt);
                        $member->refresh();
                    }

                    // 2.2 จ่าย payout ถ้ามี (ทั้งสองกรณี: มี OPEN อยู่แล้ว หรือเป็น combined)
                    if ($payout > 0) {
                        $member->increment($this->balances, $payout);
                        $member->refresh();
                    }

                    $after = (float)$member->{$this->balances};

                    // ตอบกลับ
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$after
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter'  => (float)$after,
                        ];

                    // เตรียม log ปิดรอบ
                    $closeLog = [
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $company,
                        'game_user'      => $this->member->user_name,
                        'method'         => $status,     // 'SETTLED'
                        'response'       => 'in',
                        'amount'         => (float)$payout,
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => $needCombinedPlaceAndSettle ? 'COMBINED' : 'BY_ROUND',
                        'con_4'          => null,        // จะไปปิด base (ถ้ามี) ด้านนอก
                        'before_balance' => (float)$oldBalance,
                        'after_balance'  => (float)$after,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ];

                    // ถ้าเป็น combined และอยากเก็บ log OPEN ไว้ด้วย (ไม่จำเป็นต่อ performance):
                    $openLog = null;
                    if ($needCombinedPlaceAndSettle) {
                        $openLog = [
                            'input'          => ['betAmount' => $betAmt, 'roundId' => $roundId, 'id' => $txnId],
                            'output'         => [],
                            'company'        => $company,
                            'game_user'      => $this->member->user_name,
                            'method'         => 'OPEN',
                            'response'       => 'in',
                            'amount'         => (float)$betAmt,
                            'con_1'          => $txnId,
                            'con_2'          => $roundId,
                            'con_3'          => 'OPEN',
                            'con_4'          => null,
                            'before_balance' => (float)$before,
                            'after_balance'  => $payout > 0 ? ($after - $payout) : (float)$after, // หลังหัก bet ก่อนจ่าย payout
                            'date_create'    => $this->now->toDateTimeString(),
                            'expireAt'       => $this->expireAt,
                        ];
                    }

                    return [
                        'ok'       => true,
                        'param'    => $param,
                        'openLog'  => $openLog,
                        'closeLog' => $closeLog,
                    ];
                }, 1);

                if (! $settleResult['ok']) {
                    $param = $settleResult['param'];
                    break;
                }

                // เขียน log นอก TX
                // 3) ถ้าเป็น combined → อาจบันทึก OPEN ก่อน แล้วค่อย SETTLED
                if (!empty($settleResult['openLog'])) {
                    $openId = $glog->saveGameLogToRedis($settleResult['openLog']);
                    if ($roundId !== '') {
                        \Illuminate\Support\Facades\Redis::setex($openKeyOf($roundId), 3600, $openId);
                    }
                }

                $settledId = $glog->saveGameLogToRedis($settleResult['closeLog']);

                // 4) ถ้ามี OPEN เดิมอยู่ → ปิดมัน (BY_ROUND: con_2 && con_4=null)
                if ($hasOpen && $openKey) {
                    $baseOpenId = \Illuminate\Support\Facades\Redis::get($openKey);
                    if ($baseOpenId) {
                        $glog->updateLogField($baseOpenId, 'con_4', 'SETTLED_' . $settledId, $gameUser, $company);
                        \Illuminate\Support\Facades\Redis::del($openKey);
                    }
                }

                $param = $settleResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param ?: $this->responseData(
            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance
        ));
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
        $company  = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns       = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // A) main log
        $mainLogId = $glog->saveGameLogToRedis([
            'input'          => $session,
            'output'         => [],
            'company'        => $company,
            'game_user'      => $gameUser,
            'method'         => 'rollbackmain',
            'response'       => 'in',
            'amount'         => 0,
            'con_1'          => $session['id'] ?? null,
            'con_2'          => $company,
            'con_3'          => null,
            'con_4'          => null,
            'before_balance' => (float)$oldBalance,
            'after_balance'  => (float)$this->member->balance,
            'date_create'    => $this->now->toDateTimeString(),
            'expireAt'       => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // -------- helpers (ค้นหา base ที่ยังไม่ปิด) --------
        $fetchBaseMany = function (array $cond) use ($glog, $gameUser, $company): array {
            // ฐานที่อาจต้อง rollback: BET/OPEN/WAITING/PLACED/TIPS/SETTLED/REFUND (แล้วแต่ฟลว์)
            $methods = ['BET', 'OPEN', 'WAITING', 'PLACED', 'TIPS', 'SETTLED', 'REFUND'];
            $out = [];
            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, $cond + [
                        'limit' => 20,
                        'order' => 'desc',
                    ]);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue; // ปิดแล้วไม่เอา
                    $out[] = $it;
                }
            }
            return $out;
        };
        $fetchBaseOne = function (array $cond) use ($fetchBaseMany): ?array {
            $items = $fetchBaseMany($cond);
            return $items[0] ?? null;
        };

        foreach ($txns as $txn) {
            $txnId   = $txn['id']      ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = (string)($txn['status'] ?? 'ROLLBACK'); // method ของ log
            $reqAmt  = (float)($txn['payoutAmount'] ?? $txn['amount'] ?? 0);
            $betAmt = (float)($txn['betAmount'] ?? $txn['amount'] ?? 0);
            // B) กันซ้ำระดับเมธอด
            $onceOk = $glog->reserveOnce($status, $company, $gameUser, $txnId, $roundId, 300);
            if (! $onceOk) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                );
                break;
            }

            // C) หา base
            $base = null;
            if ($roundId !== null) {
                $base = $fetchBaseOne(['con_2' => (string)$roundId]);
            }
            if (!$base && $txnId !== null) {
                $base = $fetchBaseOne(['con_1' => (string)$txnId]);
            }
            if (!$base) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance
                );
                break;
            }

            // D) ตัดสินใจ “ทิศทางเงิน” ตามฐาน
            $baseMethod = strtoupper((string)($base['method'] ?? ''));
            // สมมติ:
            // - ถ้า base เป็น BET/OPEN/PLACED → rollback = คืนเงินให้ผู้เล่น (+reqAmt)
            // - ถ้า base เป็น SETTLED/REFUND (เคยจ่ายไปก่อน) → rollback = ดึงคืน (-reqAmt) หรือ 0 ถ้าไม่ต้องแตะ
            $direction = 0; // +1 เพิ่มเงิน, -1 หักเงิน, 0 ไม่เปลี่ยน
            if (in_array($baseMethod, ['OPEN', 'WAITING'], true)) {
                $direction = +1;
            } elseif (in_array($baseMethod, ['SETTLED','REFUND'], true)) {
                $direction = ($reqAmt > 0) ? -1 : 0;
            }

            // E) ทำยอดแบบ TX สั้น
            try {
                $txRes = DB::transaction(function () use ($session, $company, $txn, $txnId, $roundId, $reqAmt, $direction,$oldBalance) {
                    $member  = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $before  = (float)$member->{$this->balances};


                    if($reqAmt > 0){
                        $member->decrement($this->balances, $reqAmt);
                    }

                    $member->refresh();
                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter'  => (float)$after,
                        ];

                    $log = [
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $company,
                        'game_user'      => $this->member->user_name,
                        'method'         => 'ROLLBACK',
                        'response'       => 'in',
                        'amount'         => (float)$reqAmt * ($direction >= 0 ? +1 : -1),
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => null,
                        'con_4'          => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance'  => (float)$after,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'member_balance' => (float)$after,
                        'log'            => $log,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // F) บันทึก rollback log + ปิด base ด้วย con_4 = "ROLLBACK_<logId>"
                $rbId = $glog->saveGameLogToRedis($txRes['log']);
                if (!empty($base['log_id'])) {
                    $glog->updateLogField($base['log_id'], 'con_4', 'ROLLBACK_' . $rbId, $gameUser, $company);
                }

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param ?: $this->responseData(
            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance
        ));
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
//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
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

//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
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
//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $this->member->balance);
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
//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
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

//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txRes['member_balance']);
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
//                LogSeamless::log($company, $gameUser, $item, $oldBalance, $txRes['member_balance']);
                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelBets(Request $request)
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
        $glog     = $this->gameLogRedis; // ใช้ Redis service
        $company  = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $txns       = (array)($session['txns'] ?? []);
        $oldBalance = (float)$this->member->balance;

        // A) main log
        $mainLogId = $glog->saveGameLogToRedis([
            'input'          => $session,
            'output'         => [],
            'company'        => $company,
            'game_user'      => $gameUser,
            'method'         => 'cancelmain',
            'response'       => 'in',
            'amount'         => 0,
            'con_1'          => $session['id'] ?? null,
            'con_2'          => $company,
            'con_3'          => null,
            'con_4'          => null,
            'before_balance' => (float)$oldBalance,
            'after_balance'  => (float)$this->member->balance,
            'date_create'    => $this->now->toDateTimeString(),
            'expireAt'       => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // -------- helpers: หา base ที่ยังไม่ปิด (con_4 = null) เพื่อตัดสินใจคืนเงิน --------
        $fetchBaseMany = function (array $cond) use ($glog, $gameUser, $company): array {
            // ฐานที่ยกเลิกได้ปกติคือ BET/OPEN/PLACED/WAITING/TIPS ที่ยังไม่ปิด
            $methods = ['BET','OPEN','PLACED','WAITING','TIPS'];
            $out = [];
            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, $cond + [
                        'limit' => 20,
                        'order' => 'desc',
                    ]);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $out[] = $it;
                }
            }
            return $out;
        };
        $fetchBaseOne = function (array $cond) use ($fetchBaseMany): ?array {
            $items = $fetchBaseMany($cond);
            return $items[0] ?? null;
        };

        foreach ($txns as $txn) {
            $txnId     = $txn['id']      ?? null;
            $roundId   = $txn['roundId'] ?? null;
            $betAmount = (float)($txn['betAmount'] ?? 0); // ถ้า provider ไม่ส่งมา ให้ 0 แล้วพยายามดึงจาก base แทน
            $status    = (string)($txn['status'] ?? 'CANCEL'); // method ของ log

            // B) กันซ้ำระดับเมธอด (con_1 + con_2)
            $onceOk = $glog->reserveOnce($status, $company, $gameUser, $txnId, $roundId, 300);
            if (! $onceOk) {
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance
                );
                break;
            }

            // C) หา base
            $base = null;
            if ($roundId !== null) {
                $base = $fetchBaseOne(['con_2' => (string)$roundId]);
            }
            if (!$base && $txnId !== null) {
                $base = $fetchBaseOne(['con_1' => (string)$txnId]);
            }
            if (!$base) {
                // ไม่เจอรายการให้ยกเลิก
                $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance
                );
                break;
            }

            // D) กำหนดจำนวนเงินที่จะคืน:
            // - ถ้า provider ส่ง betAmount มา ใช้เลย
            // - ถ้าไม่ส่ง ให้ลองเดาจาก base['amount'] (กรณี base เป็น BET/OPEN/PLACED)
            if ($betAmount <= 0) {
                $baseMethod = strtoupper((string)($base['method'] ?? ''));
                if (in_array($baseMethod, ['BET','OPEN','PLACED','WAITING','TIPS'], true)) {
                    $betAmount = (float)($base['amount'] ?? 0);
                }
            }

            // E) คืนเงินแบบ TX สั้น (เฉพาะ base ที่ยังไม่จ่ายผลและยังไม่ปิด)
            try {
                $txRes = DB::transaction(function () use ($session, $company, $txn, $txnId, $roundId, $betAmount) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                    $before = (float)$member->{$this->balances};

                    if ($betAmount > 0) {
                        $member->increment($this->balances, $betAmount);
                    }
                    $member->refresh();
                    $after = (float)$member->{$this->balances};

                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => (float)$before,
                            'balanceAfter'  => (float)$after,
                        ];

                    $log = [
                        'input'          => $txn,
                        'output'         => $param,
                        'company'        => $company,
                        'game_user'      => $this->member->user_name,
                        'method'         => 'CANCEL',
                        'response'       => 'in',
                        'amount'         => (float)$betAmount, // คืนเงินเป็น +amount
                        'con_1'          => $txnId,
                        'con_2'          => $roundId,
                        'con_3'          => null,
                        'con_4'          => null,
                        'before_balance' => (float)$before,
                        'after_balance'  => (float)$after,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ];

                    return [
                        'ok'    => true,
                        'param' => $param,
                        'log'   => $log,
                    ];
                }, 1);

                // F) บันทึก CANCEL + ปิด base ด้วย con_4 = "CANCEL_<logId>"
                $cancelId = $glog->saveGameLogToRedis($txRes['log']);
                if (!empty($base['log_id'])) {
                    $glog->updateLogField($base['log_id'], 'con_4', 'CANCEL_' . $cancelId, $gameUser, $company);
                }

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                // ถ้า TX พัง ให้ตอบ error ทันที
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param ?: $this->responseData(
            $session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance
        ));
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

//                LogSeamless::log($company, $gameUser, $txn, $oldBalance, $txResult['after_balance']);
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