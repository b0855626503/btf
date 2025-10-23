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

class NewCommonV6Controller extends AppBaseController
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

        $query = DB::table('members')->where('user_name', $username)->where('enable', 'Y');
//        $query = MemberProxy::without('bank')->where('user_name', $username)->where('enable', 'Y');
        if ($token) {
            $query->where('session_id', $token);
        }

        $this->member = $query->first();
    }

    public function UpdateBalance(string $code, float $amount, bool $allowNegative = false): array
    {
        return DB::transaction(function () use ($code, $amount, $allowNegative) {
            // ถ้าไม่อนุญาตให้ติดลบ -> ต้อง guard ด้วย balance >= abs($amount) ตอนลด
            $query = DB::table('members')->where('code', $code);

            if ($amount < 0 && !$allowNegative) {
                $query->where('balance', '>=', abs($amount));
            }

            $affected = $query->update([
                'balance' => DB::raw("balance + ({$amount})"),
                'updated_at' => now(),
            ]);

            if ($affected === 0) {
                return ['ok' => false, 'reason' => 'INSUFFICIENT_FUNDS_OR_NOT_FOUND'];
            }

            $balance = (float)DB::table('members')
                ->where('code', $code)
                ->value('balance');

            return ['ok' => true, 'balance' => $balance];
        }, 3);
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
        $session  = $request->all();
        $param    = [];
        $txns     = (array)($session['txns'] ?? []);

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;
        $company  = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        // before ของ "ทั้งฟังก์ชัน"
        $oldBalance = (float)$this->member->balance;
        $finalAfter = $oldBalance; // จะอัปเดตตามรอบที่สำเร็จ

        $amount = collect($txns)->sum(fn($t) => (float)($t['betAmount'] ?? 0));

        // ===== main log (response=in) ลง Redis =====
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [], // จะอัปเดตตอนจบ
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'betmain',
            'response'        => 'in',
            'amount'          => $amount,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => $oldBalance,
            'after_balance'   => (float)$this->member->balance,
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
            $txnId      = $txn['id'] ?? null;
            $roundId    = $txn['roundId'] ?? null;
            $status     = $txn['status'] ?? null;            // เช่น OPEN / WAITING / ...
            $betAmount  = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำ (AND: con_1 & con_2, และ con_3 ต้อง null)
            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $finalAfter);
                break;
            }

            // 2) ถ้าเป็น OPEN → ข้ามหัก ถ้ามี WAITING คู่เดียวกันอยู่แล้ว (AND + con_3=null)
            if ($status === 'OPEN' && $txnId !== null && $roundId !== null) {
                if ($isDup('WAITING', (string)$txnId, (string)$roundId)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $finalAfter) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter'  => (float)$finalAfter,
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
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => (float)$oldBalance,
                        'after_balance'   => (float)$finalAfter,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ]);

                    break;
                }
            }

            // 3) ข้ามการอัปเดตยอด แต่ต้องเขียนล็อก
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $finalAfter) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter'  => (float)$finalAfter,
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
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float)$oldBalance,
                    'after_balance'   => (float)$finalAfter,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                ]);

                break;
            }

            // 4) หักยอดแบบ TX (DB::table) + เขียน log หลัง commit
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $company, $gameUser) {
                    $code = $this->member->code;
                    $col  = $this->balances;
                    $bet  = (float) $betAmount;

                    // guard กันติดลบ + UPDATE เดียว (ไม่แตะ updated_at)
                    if ($bet > 0) {
                        $affected = DB::table('members')
                            ->where('code', $code)
                            ->where($col, '>=', $bet)
                            ->update([
                                $col => DB::raw("$col - ({$bet})"),
                            ]);

                        if ($affected === 0) {
                            // เงินไม่พอหรือไม่พบผู้ใช้ → ส่งยอดปัจจุบันกลับ
                            $current = (float) DB::table('members')->where('code', $code)->value($col);
                            return [
                                'ok'             => false,
                                'param'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current),
                                'member_balance' => $current,
                            ];
                        }
                    }

                    // อ่านยอดหลังอัปเดต
                    $after     = (float) DB::table('members')->where('code', $code)->value($col);
                    $beforeTxn = $after + $bet; // ก่อนหักของ txn นี้

                    // param ต่อรอบ (แต่จะคืนค่าไฟนอลที่นอกลูป)
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance, // ก่อนของทั้งฟังก์ชัน (ตาม requirement)
                            'balanceAfter'  => (float)$after,      // จะถูกทับด้วยค่าไฟนอลนอกลูป
                        ];

                    // เตรียม child-log (ยังไม่เขียน จนกว่า commit)
                    $log = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $bet,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => (float) $beforeTxn, // before ของ "รอบนี้"
                        'after_balance'   => (float) $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'log'            => $log,
                        'member_balance' => (float) $after,
                        'before_txn'     => (float) $beforeTxn,
                    ];
                }, 1);

                if (!$txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // อัปเดต "after ของทั้งฟังก์ชัน" ให้เป็นค่าล่าสุด
                $finalAfter = (float) $txResult['member_balance'];

                // เขียน log หลัง commit เท่านั้น
                DB::afterCommit(function () use ($glog, $txResult, $company, $gameUser, $txn) {
                    $glog->saveGameLogToRedis($txResult['log']);
                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $txn,
                        (float) $txResult['before_txn'],
                        (float) $txResult['member_balance']
                    );
                });

                // ค่า param ระหว่างลูป (จะถูก finalize ท้ายฟังก์ชันด้วย before/after ที่ถูก)
                $param = $txResult['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$finalAfter
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        // สร้างผลลัพธ์สุดท้ายให้ provider:
        // - balanceBefore = $oldBalance (ค่าแรกที่เข้ามาในฟังก์ชัน)
        // - balanceAfter  = $finalAfter (ค่าท้ายสุดหลังวนทำงานทั้งหมด)
        if (empty($param)) {
            // กันเคสไม่มี txn หรือไม่เข้าเงื่อนไขในลูป
            $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$finalAfter) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter'  => (float)$finalAfter,
                ];
        } else {
            // บังคับทับค่า before/after ให้ตรง requirement
            $param['balanceBefore'] = (float)$oldBalance;
            $param['balanceAfter']  = (float)$finalAfter;
        }

        return $finalize($param);
    }


    public function settleBets(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (!$this->member) {
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

        $txns = (array) ($session['txns'] ?? []);

        // ===== before ของทั้งชุด (fix) =====
        $code        = $this->member->code;
        $col         = $this->balances;
        $batchBefore = (float) DB::table('members')->where('code', $code)->value($col);

        $sumPayout = 0.0;
        foreach ($txns as $t) { $sumPayout += (float) ($t['payoutAmount'] ?? 0); }

        // ===== main log (settlemain) — จะใส่ output ตอนจบ =====
        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'settlemain',
            'response'        => 'in',
            'amount'          => (float) $sumPayout,
            'con_1'           => $session['id'] ?? null,
            'con_2'           => $company,
            'con_3'           => null,
            'con_4'           => null,
            'before_balance'  => (float) $batchBefore,
            'after_balance'   => (float) $batchBefore,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ---------- helpers ----------
        $normBool = fn ($v): bool => $v === true || $v === 1 || $v === '1' || $v === 'true';

        $latestOne = function (string $method, array $cond) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, $method, $cond + ['limit'=>20,'order'=>'desc']);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                return $it; // ล่าสุด
            }
            return null;
        };

        // BY_ROUND: อะไรก็ได้ con_2=round และ con_4=null
        $anyByRoundCon4Null = function (string $roundId) use ($glog, $gameUser, $company): array {
            $methods = ['OPEN','WAITING','SETTLED'];
            $seen=[]; $out=[];
            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, [
                    'con_2' => (string) $roundId,
                    'limit' => 20,
                    'order' => 'desc',
                ]);
                foreach (($res['items'] ?? []) as $it) {
                    if (($it['response'] ?? '') !== 'in') continue;
                    if (($it['con_4'] ?? null) !== null) continue;
                    $id = $it['log_id'] ?? null;
                    if ($id && !isset($seen[$id])) { $seen[$id]=true; $out[]=$it; }
                }
            }
            return $out;
        };

        $latestSettledByRoundCon4Null = function (string $roundId) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_2' => (string) $roundId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return $it;
            }
            return null;
        };

        $latestSettledByTxnCon4Null = function (string $txnId) use ($glog, $gameUser, $company): ?array {
            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', [
                'con_1' => (string) $txnId,
                'limit' => 20,
                'order' => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue;
                return $it;
            }
            return null;
        };

        // ===== PRE-PASS: เตรียมงานให้ครบก่อนเข้า TX =====
        $toApplyPayout = [];                    // รายการที่จะเครดิต payout จริง
        $toBindBases   = [];                    // key=txnId|roundId => [baseId หรือ '__NEW_OPEN__']
        $needOpenLog   = [];                    // key=txnId|roundId => true ถ้าต้องสร้าง OPEN ใหม่ในชุดนี้ (single-state)
        $noopOnly      = true;                  // ถ้ามีงานจริงอย่างน้อย 1 ชิ้นจะ false

        foreach ($txns as $txn) {
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
            $bindKey = (string) ($txnId ?? '') . '|' . (string) ($roundId ?? '');

            // A) single-state: ถ้ามี OPEN อยู่แล้ว ⇒ ใช้เป็นฐาน (อย่าตีเป็น duplicate)
            if ($isSingleState && $txnId !== null && $roundId !== null) {
                $dupOpen = $latestOne('OPEN', ['con_1'=>(string)$txnId,'con_2'=>(string)$roundId]);
                if ($dupOpen) {
                    // แปลว่าเคย OPEN ไปแล้วจากคำสั่งก่อนหน้า/ก่อนหน้านี้ ⇒ ใช้เป็นฐานได้
                    if (($dupOpen['con_4'] ?? null) === null) {
                        $toBindBases[$bindKey][] = $dupOpen['log_id'];
                    }
                } else {
                    // ยังไม่มี OPEN ใน Redis ⇒ ถ้าไม่ skip และ betAmt>0 ให้หัก bet แล้วเราจะ "สร้าง OPEN" เองหลัง commit
                    if (!$skipBalanceUpdate && $betAmt > 0) {
                        $affected = DB::table('members')
                            ->where('code', $code)
                            ->where($col, '>=', $betAmt)
                            ->update([$col => DB::raw("$col - ({$betAmt})")]);
                        if ($affected === 0) {
                            $current = (float) DB::table('members')->where('code', $code)->value($col);
                            $err = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current);
                            return $finalize($err);
                        }
                        $needOpenLog[$bindKey] = true;               // ต้องสร้าง OPEN log ให้ txn นี้
                        $toBindBases[$bindKey][] = '__NEW_OPEN__';   // placeholder สำหรับผูก con_4 ภายหลัง
                    }
                }
            }

            // B) เลือกฐานตามชนิด (BY_ROUND/BY_TRANSACTION) — อย่าตี duplicate ง่ายไป
            if ($transactionType === 'BY_ROUND') {
                if (!$roundId) continue;
                $bases = $anyByRoundCon4Null((string)$roundId);
                if (!empty($bases)) {
                    foreach ($bases as $b) {
                        if (!empty($b['log_id']) && ($b['con_4'] ?? null) === null) {
                            $toBindBases[$bindKey][] = $b['log_id'];
                        }
                    }
                }
                if (!$ismulti && !$skipBalanceUpdate) {
                    $dup = $latestSettledByRoundCon4Null((string)$roundId);
                    if ($dup && $normBool($dup['con_3'] ?? false) === false) {
                        // เคย SETTLED non-multi ไปแล้วบนรอบเดียวกัน → อย่าทำซ้ำ txn นี้
                        continue;
                    }
                }
            } else { // BY_TRANSACTION
                if (!$txnId) continue;
                // ถ้ายังไม่มีฐาน และไม่ใช่ single-state ที่เราจะสร้าง OPEN เอง → ล้มเหลว (ไม่ทำ txn นี้)
                if (empty($toBindBases[$bindKey])) {
                    $openLatest = $latestOne('OPEN', ['con_1'=>(string)$txnId]);
                    if ($openLatest && ($openLatest['con_4'] ?? null) === null) {
                        $toBindBases[$bindKey][] = $openLatest['log_id'];
                    } elseif (!isset($needOpenLog[$bindKey])) {
                        // ไม่มี OPEN เลย และเราไม่ได้จะสร้าง OPEN เอง
                        continue;
                    }
                }
                if (!$skipBalanceUpdate) {
                    $dup = $latestSettledByTxnCon4Null((string)$txnId);
                    if ($dup) {
                        // เคย SETTLED ไปแล้ว (ยังไม่ผูก con_4) ⇒ อย่าซ้ำ
                        continue;
                    }
                }
            }

            // C) mark ว่ามีงานจริง และต้องเครดิต payout
            $toApplyPayout[] = [
                'txn'      => $txn,
                'txnId'    => $txnId,
                'roundId'  => $roundId,
                'payout'   => $payout,
                'ismulti'  => (bool) ($ismulti),
            ];
            $noopOnly = false;
        }

        // ไม่มีงานจริงเลย ⇒ ถ้ามีการลองยิงซ้ำจะคืน 20002, ถ้าเงียบจริง ๆ คืน 0 พร้อม before=after
        if ($noopOnly) {
            $codeOut = empty($txns) ? 0 : 20002;
            $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, $codeOut, (float) $batchBefore
                ) + [
                    'balanceBefore' => (float) $batchBefore,
                    'balanceAfter'  => (float) $batchBefore,
                ];
            return $finalize($param);
        }

        // ===== ONE-BATCH / ONE-TRANSACTION =====
        try {
            $result = DB::transaction(function () use ($session, $company, $gameUser, $toApplyPayout, $toBindBases, $needOpenLog, $batchBefore, $col, $code) {
                $now          = $this->now->toDateTimeString();
                $runningAfter = (float) DB::table('members')->where('code', $code)->value($col);

                $childLogs  = [];   // OPEN (ใหม่ถ้ามี) + SETTLED ทั้งหมด
                $seamless   = [];   // [(before, after, txn)]
                $sumSettled = 0.0;

                // 1) สร้าง OPEN logs ใหม่สำหรับ single-state ที่เราหัก bet ไปแล้ว
                foreach ($needOpenLog as $bindKey => $_) {
                    // หา txn ที่ตรง key เพื่อดึง betAmount/fields
                    foreach ($toApplyPayout as $entry) {
                        $k = ($entry['txnId'] ?? '') . '|' . ($entry['roundId'] ?? '');
                        if ($k !== $bindKey) continue;

                        $txn    = $entry['txn'];
                        $betAmt = (float) ($txn['betAmount'] ?? 0);
                        $after  = (float) DB::table('members')->where('code', $code)->value($col);
                        $before = $after + $betAmt;
                        $runningAfter = $after;

                        $childLogs[] = [
                            'input'           => $txn,
                            'output'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                                    'balanceBefore' => (float) $before,
                                    'balanceAfter'  => (float) $after,
                                ],
                            'company'         => $company,
                            'game_user'       => $gameUser,
                            'method'          => 'OPEN',
                            'response'        => 'in',
                            'amount'          => (float) $betAmt,
                            'con_1'           => $entry['txnId'] ?? null,
                            'con_2'           => $entry['roundId'] ?? null,
                            'con_3'           => null,
                            'con_4'           => null,
                            'before_balance'  => (float) $before,
                            'after_balance'   => (float) $after,
                            'date_create'     => $now,
                            'expireAt'        => $this->expireAt,
                            '__bind_key'      => $bindKey, // ไว้ map ผูก con_4 ภายหลัง
                        ];
                        $seamless[] = [$before, $after, $txn];
                        break;
                    }
                }

                // 2) เครดิต payout ต่อ txn
                foreach ($toApplyPayout as $entry) {
                    $txn     = $entry['txn'];
                    $payout  = (float) ($entry['payout'] ?? 0);
                    $ismulti = (bool)  ($entry['ismulti'] ?? false);
                    $bindKey = ($entry['txnId'] ?? '') . '|' . ($entry['roundId'] ?? '');

                    if ($payout > 0) {
                        DB::table('members')
                            ->where('code', $code)
                            ->update([$col => DB::raw("$col + ({$payout})")]);
                    }

                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $payout;
                    $runningAfter = $after;
                    $sumSettled  += $payout;

                    $childLogs[] = [
                        'input'           => $txn,
                        'output'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                                'balanceBefore' => (float) $before,
                                'balanceAfter'  => (float) $after,
                            ],
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => 'SETTLED',
                        'response'        => 'in',
                        'amount'          => (float) $payout,
                        'con_1'           => $entry['txnId'] ?? null,
                        'con_2'           => $entry['roundId'] ?? null,
                        'con_3'           => (bool) $ismulti,
                        'con_4'           => null,
                        'before_balance'  => (float) $before,
                        'after_balance'   => (float) $after,
                        'date_create'     => $now,
                        'expireAt'        => $this->expireAt,
                        '__bind_key'      => $bindKey,
                    ];
                    $seamless[] = [$before, $after, $txn];
                }

                // 3) main param (before = แรก, after = ล่าสุด)
                $paramMain = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 0, $runningAfter
                    ) + [
                        'balanceBefore' => (float) $batchBefore,
                        'balanceAfter'  => (float) $runningAfter,
                    ];

                // main summary log (เขียนหลัง commit)
                $mainLog = [
                    'input'           => ['txns' => $toApplyPayout ? array_column($toApplyPayout, 'txn') : []],
                    'output'          => $paramMain,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => 'settlemain',
                    'response'        => 'in',
                    'amount'          => (float) $sumSettled,
                    'con_1'           => $session['id'] ?? null,
                    'con_2'           => $company,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $batchBefore,
                    'after_balance'   => (float) $runningAfter,
                    'date_create'     => $now,
                    'expireAt'        => $this->expireAt,
                ];

                return [
                    'ok'             => true,
                    'param'          => $paramMain,
                    'childLogs'      => $childLogs,
                    'mainLog'        => $mainLog,
                    'bindBases'      => $toBindBases,    // key => [base ids / '__NEW_OPEN__']
                    'needOpenLog'    => $needOpenLog,    // key => true ถ้าสร้าง OPEN ใหม่
                    'seamlessPairs'  => $seamless,
                    'before_balance' => (float) $batchBefore,
                    'member_balance' => (float) $runningAfter,
                ];
            }, 3);

            if (!$result['ok']) {
                return $finalize($result['param']);
            }

            // ===== เขียน log + ผูก con_4 + seamless หลัง commit =====
            DB::afterCommit(function () use ($glog, $result, $company, $gameUser) {
                $openIdsByKey    = []; // key => openLogId (สร้างใหม่ในชุดนี้)
                $settledIdsByKey = []; // key => settledLogId

                foreach ($result['childLogs'] as $lg) {
                    $bindKey = $lg['__bind_key'] ?? null;
                    $method  = $lg['method'] ?? '';
                    unset($lg['__bind_key']);

                    $id = $glog->saveGameLogToRedis($lg);

                    if ($method === 'OPEN'    && $bindKey) $openIdsByKey[$bindKey]    = $id;
                    if ($method === 'SETTLED' && $bindKey) $settledIdsByKey[$bindKey] = $id;
                }

                // main summary
                $glog->saveGameLogToRedis($result['mainLog']);

                // ผูก con_4 ของ base → SETTLED
                foreach ($result['bindBases'] as $key => $baseIds) {
                    $sid = $settledIdsByKey[$key] ?? null;
                    if (!$sid) continue;

                    foreach ($baseIds as $baseId) {
                        if ($baseId === '__NEW_OPEN__') {
                            $oid = $openIdsByKey[$key] ?? null;
                            if ($oid) { $glog->updateLogField($oid, 'con_4', 'SETTLED_' . $sid, $gameUser, $company); }
                        } else {
                            $glog->updateLogField($baseId, 'con_4', 'SETTLED_' . $sid, $gameUser, $company);
                        }
                    }
                }

                // seamless ต่อรายการ
                foreach ($result['seamlessPairs'] as [$before, $after, $txnPayload]) {
                    LogSeamless::log($company, $gameUser, $txnPayload, (float) $before, (float) $after);
                }
            });

            return $finalize($result['param']); // statusCode=0 พร้อม before/after ที่ถูก

        } catch (\Throwable $e) {
            \Log::channel('gamelog')->error('settleBets failed', [
                'user'     => $gameUser,
                'company'  => $company,
                'session'  => $session['id'] ?? null,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            $err = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                ) + ['message' => $e->getMessage()];
            return $finalize($err);
        }
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
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $company, $gameUser) {
                    $code = $this->member->code;
                    $col  = $this->balances;
                    $pay  = (float) $payout;

                    // 1) เติมยอดแบบ UPDATE เดียว (เร็วกว่า Eloquent)
                    if ($pay > 0) {
                        DB::table('members')
                            ->where('code', $code)
                            ->update([
                                $col         => DB::raw("$col + {$pay}"),
                                'date_update' => now(),
                            ]);
                    }

                    // 2) อ่านยอดหลังอัปเดต (ยังอยู่ใน TX)
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $pay; // คำนวณย้อนให้แม่น ไม่อิงตัวแปรนอก TX

                    // 3) response ที่จะส่งกลับหน้าเกม
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => $before,
                            'balanceAfter'  => $after,
                        ];

                    // 4) เตรียม payload log (อย่าเขียนตอนยังไม่ commit)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status, // เช่น WIN_REWARD / SETTLED
                        'response'        => 'in',
                        'amount'          => $pay,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'logData'        => $logData,
                        'member_balance' => $after,
                        'before_balance' => $before,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // 5) เขียน Redis/Seamless หลัง commit จริงเท่านั้น
                DB::afterCommit(function () use ($glog, $txRes, $company, $gameUser, $txn) {
                    $glog->saveGameLogToRedis($txRes['logData']);
                    LogSeamless::log($company, $gameUser, $txn, (float) $txRes['before_balance'], (float) $txRes['member_balance']);
                });

                // 6) ส่งกลับให้ฝั่งเกม
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance
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

        if (!$this->member) {
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

        $txns = (array) ($session['txns'] ?? []);

        // ====== ยอดแรกตอนเข้าฟังก์ชัน (before ของทั้งชุด) ======
        $batchBefore = (float) DB::table('members')->where('code', $this->member->code)->value($this->balances);

        // main log (voidsettledmain) — con_3 = null
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
            'before_balance'  => (float) $batchBefore,
            'after_balance'   => (float) $batchBefore,
            'date_create'     => $this->now->toDateTimeString(),
            'expireAt'        => $this->expireAt,
        ]);
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ---------- helpers ----------
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null || $con2 === null) return false;
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'con_1'  => (string) $con1,
                'con_2'  => (string) $con2,
                'limit'  => 10,
                'order'  => 'desc',
            ]);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') === 'in' && ($it['con_3'] ?? null) === null && ($it['con_4'] ?? null) === null) {
                    return true;
                }
            }
            return false;
        };

        // หา SETTLED ต้นทางที่ยังไม่ปิด (con_4 = null) ตามชนิด
        $findSourceSettled = function (string $type, ?string $txnId, ?string $roundId) use ($glog, $gameUser, $company): ?array {
            $filters = $type === 'BY_ROUND'
                ? ['con_2' => (string) $roundId, 'limit' => 20, 'order' => 'desc']
                : ['con_1' => (string) $txnId , 'limit' => 20, 'order' => 'desc'];

            $res = $glog->queryGameLogs($gameUser, $company, 'SETTLED', $filters);
            foreach (($res['items'] ?? []) as $it) {
                if (($it['response'] ?? '') !== 'in') continue;
                if (($it['con_4'] ?? null) !== null) continue; // ต้องยังไม่ปิด
                return $it; // ล่าสุดที่ยังไม่ปิด
            }
            return null;
        };

        // ====== PRE-FILTER: คัด txn ที่ทำได้จริง + เก็บ noop (dup/ไม่เจอ source) ======
        $toApply  = [];    // รายการที่จะปรับยอดจริง และต้องผูก con_4
        $noopLogs = [];    // สำหรับ trace กรณีข้าม

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = (string) ($txn['status'] ?? 'VOID_SETTLED');
            $type    = (string) ($txn['transactionType'] ?? 'BY_TRANSACTION'); // 'BY_ROUND' | 'BY_TRANSACTION'

            // กันซ้ำด้วย VOID_SETTLED + (con_1, con_2) + con_3=null + con_4=null
            if ($isDup($status, $txnId, $roundId)) {
                $noopLogs[] = ['txn' => $txn, 'note' => 'dup'];
                continue;
            }

            // ต้องมี source SETTLED ที่ยังไม่ปิด
            $src = $findSourceSettled($type, $txnId, $roundId);
            if (!$src) {
                $noopLogs[] = ['txn' => $txn, 'note' => 'no-source-settled'];
                continue;
            }

            // เตรียมข้อมูลสำหรับทำงานจริง
            $betAmount = (float) ($txn['betAmount']    ?? 0);
            $payout    = (float) ($txn['payoutAmount'] ?? 0);
            $netDelta  = $betAmount - $payout; // คืน bet (+), หัก payout (-)

            $toApply[] = [
                'txn'        => $txn,
                'src_log_id' => $src['log_id'] ?? null,
                'netDelta'   => $netDelta,
                'status'     => $status,
            ];
        }

        // ถ้าไม่มีอะไรต้องทำจริง → ตอบกลับทันที (20002 เมื่อมี noop, ไม่งั้น 0)
        if (empty($toApply)) {
            $param = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company,
                    !empty($noopLogs) ? 20002 : 0,
                    (float) $batchBefore
                ) + [
                    'balanceBefore' => (float) $batchBefore,
                    'balanceAfter'  => (float) $batchBefore,
                ];

            // บันทึก noop logs (ปลอดภัยเพราะไม่มี DB write)
            foreach ($noopLogs as $n) {
                $txn = $n['txn'];
                $glog->saveGameLogToRedis([
                    'input'           => $txn,
                    'output'          => $param,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => (string) ($txn['status'] ?? 'VOID_SETTLED'),
                    'response'        => 'in',
                    'amount'          => (float) (($txn['betAmount'] ?? 0) - ($txn['payoutAmount'] ?? 0)), // net ที่ร้องมา
                    'con_1'           => $txn['id'] ?? null,
                    'con_2'           => $txn['roundId'] ?? null,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $batchBefore,
                    'after_balance'   => (float) $batchBefore,
                    'date_create'     => $this->now->toDateTimeString(),
                    'expireAt'        => $this->expireAt,
                    'note'            => $n['note'] ?? null,
                ]);
            }

            return $finalize($param);
        }

        // ====== ONE-BATCH / ONE-TRANSACTION ======
        try {
            $result = DB::transaction(function () use ($session, $company, $gameUser, $toApply, $batchBefore) {
                $code = $this->member->code;
                $col  = $this->balances;
                $now  = $this->now->toDateTimeString();

                $runningAfter = $batchBefore;

                $childLogs   = []; // เก็บ payload สำหรับเขียนหลัง commit
                $bindings    = []; // mapping: src_log_id -> void_log_id (จะเติมหลัง save)
                $seamless    = []; // [(before, after, txn)]

                foreach ($toApply as $item) {
                    $txn        = $item['txn'];
                    $srcLogId   = $item['src_log_id'];
                    $netDelta   = (float) $item['netDelta'];
                    $status     = (string) ($item['status'] ?? 'VOID_SETTLED');

                    // อัปเดตยอดด้วย DB::table + guard ตอนหัก (net < 0)
                    if ($netDelta < 0) {
                        $need = abs($netDelta);
                        $aff = DB::table('members')
                            ->where('code', $code)
                            ->where($col, '>=', $need)
                            ->update([$col => DB::raw("$col - ({$need})")]);
                        if ($aff === 0) {
                            $current = (float) DB::table('members')->where('code', $code)->value($col);
                            return [
                                'ok'    => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current
                                ),
                            ];
                        }
                    } elseif ($netDelta > 0) {
                        DB::table('members')
                            ->where('code', $code)
                            ->update([$col => DB::raw("$col + ({$netDelta})")]);
                    } else {
                        // net = 0 ⇒ ไม่แตะยอด แต่ยังคงสร้าง log VOID_SETTLED ไว้เป็นรอย
                    }

                    // ยอดหลังของ txn นี้
                    $after      = (float) DB::table('members')->where('code', $code)->value($col);
                    $beforeTxn  = $after - $netDelta; // ย้อนกลับก่อนอัปเดต (ใช้ได้ทั้ง + และ -)
                    $runningAfter = $after;

                    // สร้าง payload ของ child log (เขียนหลัง commit)
                    $childLogs[] = [
                        'input'           => $txn,
                        'output'          => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                                'balanceBefore' => (float) $beforeTxn,
                                'balanceAfter'  => (float) $after,
                            ],
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,           // 'VOID_SETTLED'
                        'response'        => 'in',
                        'amount'          => (float) $netDelta, // เก็บ net
                        'con_1'           => $txn['id'] ?? null,
                        'con_2'           => $txn['roundId'] ?? null,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => (float) $beforeTxn,
                        'after_balance'   => (float) $after,
                        'date_create'     => $now,
                        'expireAt'        => $this->expireAt,
                        '__src_log_id'    => $srcLogId,        // แนบไว้เพื่อไปผูก con_4 หลัง save
                    ];

                    $seamless[] = [$beforeTxn, $after, $txn];
                }

                // main param ของทั้งแบตช์
                $paramMain = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 0, $runningAfter
                    ) + [
                        'balanceBefore' => (float) $batchBefore,
                        'balanceAfter'  => (float) $runningAfter,
                    ];

                // main summary log (จะเขียนหลัง commit)
                $mainLog = [
                    'input'           => ['txns' => array_column($toApply, 'txn')],
                    'output'          => $paramMain,
                    'company'         => $company,
                    'game_user'       => $gameUser,
                    'method'          => 'voidsettledmain',
                    'response'        => 'in',
                    'amount'          => (float) array_sum(array_map(fn($c) => (float)$c['amount'], $childLogs)),
                    'con_1'           => $session['id'] ?? null,
                    'con_2'           => $company,
                    'con_3'           => null,
                    'con_4'           => null,
                    'before_balance'  => (float) $batchBefore,
                    'after_balance'   => (float) $runningAfter,
                    'date_create'     => $now,
                    'expireAt'        => $this->expireAt,
                ];

                return [
                    'ok'             => true,
                    'param'          => $paramMain,
                    'childLogs'      => $childLogs,
                    'mainLog'        => $mainLog,
                    'seamless'       => $seamless,
                    'before_balance' => (float) $batchBefore,
                    'member_balance' => (float) $runningAfter,
                ];
            }, 3);

            if (!$result['ok']) {
                return $finalize($result['param']); // 10002 (เงินไม่พอ) พร้อมยอดล่าสุด
            }

            // ====== เขียน log + ผูก con_4 + seamless หลัง commit ======
            DB::afterCommit(function () use ($glog, $result, $company, $gameUser) {
                // 1) child logs และเก็บ mapping src -> void
                $mapping = []; // src_log_id => void_log_id
                foreach ($result['childLogs'] as $lg) {
                    $srcId = $lg['__src_log_id'] ?? null;
                    unset($lg['__src_log_id']);
                    $voidId = $glog->saveGameLogToRedis($lg);
                    if ($srcId) {
                        $mapping[$srcId] = $voidId;
                    }
                }

                // 2) main summary
                $glog->saveGameLogToRedis($result['mainLog']);

                // 3) ผูก con_4 ของ source SETTLED → VOID_SETTLED_x
                foreach ($mapping as $srcLogId => $voidLogId) {
                    $glog->updateLogField($srcLogId, 'con_4', 'VOID_SETTLED_' . $voidLogId, $gameUser, $company);
                }

                // 4) seamless ต่อรายการ
                foreach ($result['seamless'] as [$before, $after, $txn]) {
                    LogSeamless::log($company, $gameUser, $txn, (float) $before, (float) $after);
                }
            });

            return $finalize($result['param']); // statusCode=0 + before/after ครบ

        } catch (\Throwable $e) {
            \Log::channel('gamelog')->error('voidSettled failed', [
                'user'     => $gameUser,
                'company'  => $company,
                'session'  => $session['id'] ?? null,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            $err = $this->responseData(
                    $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                ) + ['message' => $e->getMessage()];
            return $finalize($err);
        }
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
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $company, $gameUser) {
                    $code = $this->member->code;
                    $col  = $this->balances;
                    $amt  = (float) $amount;

                    // 1) หักยอดแบบเร็วสุด: UPDATE เดียว + guard กันติดลบ
                    $affected = DB::table('members')
                        ->where('code', $code)
                        ->where($col, '>=', $amt)
                        ->update([
                            $col         => DB::raw("$col - {$amt}"),
                            'date_update' => now(),
                        ]);

                    if ($affected === 0) {
                        // เงินไม่พอหรือไม่พบผู้ใช้ → ส่งยอดปัจจุบันกลับ
                        $current = (float) DB::table('members')->where('code', $code)->value($col);
                        return [
                            'ok'    => false,
                            'param' => $this->responseData(
                                $session['id'] ?? null, $session['username'] ?? '', $company, 10002, $current
                            ),
                        ];
                    }

                    // 2) อ่านยอดหลังอัปเดต (ยังอยู่ใน TX)
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after + $amt; // คำนวณย้อนให้แม่น

                    // 3) response สำหรับฝั่งเกม
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => $before,
                            'balanceAfter'  => $after,
                        ];

                    // 4) payload สำหรับ log (จะเขียนหลัง commit)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => $amt,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'logData'        => $logData,
                        'member_balance' => $after,
                        'before_balance' => $before,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // 5) เขียน Redis/Seamless หลัง commit จริงเท่านั้น (กัน log นำหน้า DB)
                DB::afterCommit(function () use ($glog, $txRes, $company, $gameUser, $txn) {
                    $glog->saveGameLogToRedis($txRes['logData']);
                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $txn,
                        (float) $txRes['before_balance'],
                        (float) $txRes['member_balance']
                    );
                });

                // 6) ส่งกลับให้ฝั่งเกม
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance
                    ) + ['message' => $e->getMessage()];
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
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $company, $gameUser) {
                    $code = $this->member->code;
                    $col  = $this->balances;
                    $amt  = (float) $amount;

                    // [1] เติมยอดแบบเร็วสุด: UPDATE เดียว (ไม่ใช้ lockForUpdate/ increment)
                    if ($amt > 0) {
                        DB::table('members')
                            ->where('code', $code)
                            ->update([
                                $col         => DB::raw("$col + ({$amt})"),
                                'date_update' => now(),
                            ]);
                    }

                    // [2] อ่านยอดหลังอัปเดตใน TX เดียว
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $amt; // คำนวณย้อน แม่นกว่าหยิบตัวแปรนอก TX

                    // [3] response ส่งกลับให้เกม
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => $before,
                            'balanceAfter'  => $after,
                        ];

                    // [4] เตรียม payload log (จะเขียนหลัง commit เท่านั้น)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,          // เช่น CANCEL_TIP / ADJUST
                        'response'        => 'in',
                        'amount'          => $amt,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'logData'        => $logData,
                        'member_balance' => $after,
                        'before_balance' => $before,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // [5] เขียน Redis + ผูก con_4 + seamless "หลัง commit" เพื่อกัน log นำหน้า DB
                DB::afterCommit(function () use ($glog, $txRes, $srcLogId, $status, $gameUser, $company, $txn) {
                    $cancelLogId = $glog->saveGameLogToRedis($txRes['logData']);

                    if (!empty($srcLogId)) {
                        $glog->updateLogField(
                            $srcLogId,
                            'con_4',
                            (($status ?? 'CANCEL_TIP') . '_' . $cancelLogId),
                            $gameUser,
                            $company
                        );
                    }

                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $txn,
                        (float) $txRes['before_balance'],
                        (float) $txRes['member_balance']
                    );
                });

                // [6] ส่งผลลัพธ์ให้ฝั่งเกม
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance
                    ) + ['message' => $e->getMessage()];
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
                $txRes = DB::transaction(function () use ($session, $item, $status, $amount, $refId, $company, $gameUser) {
                    $code  = $this->member->code;
                    $col   = $this->balances;
                    $amt   = (float) $amount;
                    $delta = ($status === 'DEBIT') ? -$amt : $amt; // DEBIT = -, CREDIT = +

                    // [1] ปรับยอดแบบ UPDATE เดียว (เร็ว + อะตอมมิก)
                    if ($delta !== 0.0) {
                        $q = DB::table('members')->where('code', $code);

                        // ถ้าเป็น DEBIT ให้ guard กันติดลบ
                        if ($delta < 0) {
                            $q->where($col, '>=', abs($delta));
                        }

                        $affected = $q->update([
                            $col         => DB::raw("$col + ($delta)"),
                            'date_update' => now(),
                        ]);

                        // DEBIT แล้วเงินไม่พอ/ไม่พบผู้ใช้ → ตอบ 10002 พร้อมยอดปัจจุบัน
                        if ($affected === 0 && $delta < 0) {
                            $current = (float) DB::table('members')->where('code', $code)->value($col);
                            return [
                                'ok'    => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null,
                                    $session['username'] ?? '',
                                    $company,
                                    10002,
                                    $current
                                ),
                            ];
                        }
                    }

                    // [2] อ่านยอดหลังอัปเดต (ยังอยู่ใน TX)
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $delta; // คำนวณย้อนจากผลจริง

                    // [3] response ตามรูปแบบเดิม
                    $param = [
                        'id'              => $session['id'] ?? null,
                        'statusCode'      => 0,
                        'currency'        => 'THB',
                        'productId'       => $company,
                        'username'        => $gameUser,
                        'balanceBefore'   => $before,
                        'balanceAfter'    => $after,
                        'timestampMillis' => $this->now->getTimestampMs(),
                    ];

                    // [4] base log (amount เก็บเป็นจำนวนบวกตามเดิม)
                    $baseLog = [
                        'input'           => $item,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'response'        => 'in',
                        'amount'          => $amt,        // คงพฤติกรรมเดิม: เก็บจำนวนบวก
                        'con_1'           => $refId,
                        'con_2'           => $refId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'logs'           => [
                            array_merge($baseLog, ['method' => 'ADJUSTBALANCE']),
                            array_merge($baseLog, ['method' => 'OPEN']),
                        ],
                        'before_balance' => $before,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // [5] เขียน Redis + Seamless หลัง commit จริงเท่านั้น (กัน log นำหน้า DB/กัน rollback ทิ้ง log)
                DB::afterCommit(function () use ($glog, $txRes, $company, $gameUser, $item) {
                    foreach ((array) $txRes['logs'] as $lg) {
                        $glog->saveGameLogToRedis($lg);
                    }
                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $item,
                        (float) $txRes['before_balance'],
                        (float) $txRes['member_balance']
                    );
                });

                // [6] ส่งผลลัพธ์กลับ
                $param = $txRes['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance
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
                $txRes = DB::transaction(function () use ($session, $reqAmount, $baseAmount, $company, $gameUser, $txn, $status) {
                    $code  = $this->member->code;
                    $col   = $this->balances;
                    $req   = (float) $reqAmount;
                    $base  = (float) $baseAmount;

                    // คำนวณ delta แบบรวดเดียว: (req > base) => req - base, else => req  (ไม่ติดลบ)
                    $delta = ($req > $base) ? ($req - $base) : $req; // >= 0 เสมอ

                    // [1] UPDATE เดียว (เร็ว + อะตอมมิก) — ถ้าไม่มีการเปลี่ยนแปลงก็ไม่ยิง
                    if ($delta !== 0.0) {
                        DB::table('members')
                            ->where('code', $code)
                            ->update([
                                $col         => DB::raw("$col + ({$delta})"),
                                'date_update' => now(),
                            ]);
                    }

                    // [2] อ่านยอดหลังอัปเดต (อยู่ใน TX)
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $delta; // คำนวณย้อนจากผลจริงใน TX

                    // [3] response
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => $before,
                            'balanceAfter'  => $after,
                        ];

                    // [4] payload log (เก็บ amount = req ตามพฤติกรรมเดิม)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,
                        'response'        => 'in',
                        'amount'          => (float) $reqAmount, // คงเดิม: เก็บ req
                        'con_1'           => $txn['id'] ?? null,
                        'con_2'           => $txn['roundId'] ?? null,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'logData'        => $logData,
                        'before_balance' => $before,
                        'member_balance' => $after,
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // [5] เขียน Redis + ปิด base + seamless หลัง commit จริงเท่านั้น
                DB::afterCommit(function () use ($glog, $txRes, $isArray, $baseLogs, $status, $gameUser, $company, $txn) {
                    $cancelId = $glog->saveGameLogToRedis($txRes['logData']);

                    if (!empty($isArray) && $isArray) {
                        foreach ((array) $baseLogs as $lg) {
                            if (!empty($lg['log_id'])) {
                                $glog->updateLogField($lg['log_id'], 'con_4', (($status ?: 'CANCEL') . '_' . $cancelId), $gameUser, $company);
                            }
                        }
                    } else {
                        if (!empty($baseLogs[0]['log_id'])) {
                            $glog->updateLogField($baseLogs[0]['log_id'], 'con_4', (($status ?: 'CANCEL') . '_' . $cancelId), $gameUser, $company);
                        }
                    }

                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $txn,
                        (float) $txRes['before_balance'],
                        (float) $txRes['member_balance']
                    );
                });

                // [6] ส่งผลลัพธ์กลับ
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
                $txResult = DB::transaction(function () use ($session, $txn, $status, $diff, $newBet, $txnId, $roundId, $company, $gameUser) {
                    $code = $this->member->code;
                    $col  = $this->balances;

                    // delta ที่จะ "บวกเข้าบัญชี": diff>0 = หักเงิน → delta = -diff, diff<0 = เติมเงิน → delta = +abs(diff)
                    $delta = (float) (0 - $diff); // เท่ากับ -$diff

                    // [1] UPDATE เดียว (เร็ว + อะตอมมิก)
                    if ($delta !== 0.0) {
                        $q = DB::table('members')->where('code', $code);
                        if ($delta < 0) {
                            // เคสหักยอด: guard กันติดลบ
                            $q->where($col, '>=', abs($delta));
                        }

                        $affected = $q->update([
                            $col         => DB::raw("$col + ({$delta})"),
                            'date_update' => now(),
                        ]);

                        if ($delta < 0 && $affected === 0) {
                            // เงินไม่พอ/ไม่พบผู้ใช้ → ส่งยอดปัจจุบันกลับ
                            $current = (float) DB::table('members')->where('code', $code)->value($col);
                            return [
                                'ok'    => false,
                                'param' => $this->responseData(
                                    $session['id'] ?? null,
                                    $session['username'] ?? '',
                                    $company,
                                    10002,
                                    $current
                                ),
                            ];
                        }
                    }

                    // [2] อ่านยอดหลังอัปเดต (ยังอยู่ใน TX)
                    $after  = (float) DB::table('members')->where('code', $code)->value($col);
                    $before = $after - $delta; // after = before + delta

                    // [3] response
                    $param = $this->responseData(
                            $session['id'] ?? null, $session['username'] ?? '', $company, 0, $after
                        ) + [
                            'balanceBefore' => $before,
                            'balanceAfter'  => $after,
                        ];

                    // [4] payload สำหรับ log (amount เก็บ "ยอดใหม่" ตามที่ต้องการ)
                    $logData = [
                        'input'           => $txn,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'method'          => $status,          // 'ADJUST'
                        'response'        => 'in',
                        'amount'          => (float) $newBet,  // เก็บยอดใหม่
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => $before,
                        'after_balance'   => $after,
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'             => true,
                        'param'          => $param,
                        'log'            => $logData,
                        'before_balance' => $before,
                        'after_balance'  => $after,
                    ];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // [5] เขียน Redis + ปิด base เดิม + seamless "หลัง commit" เท่านั้น
                DB::afterCommit(function () use ($glog, $txResult, $company, $gameUser, $txn, $baseLogId) {
                    $newAdjId = $glog->saveGameLogToRedis($txResult['log']);
                    if (!empty($baseLogId)) {
                        $glog->updateLogField($baseLogId, 'con_4', 'ADJUST_' . $newAdjId, $gameUser, $company);
                    }
                    LogSeamless::log(
                        $company,
                        $gameUser,
                        $txn,
                        (float) $txResult['before_balance'],
                        (float) $txResult['after_balance']
                    );
                });

                // [6] ส่งผลลัพธ์ให้เกม
                $param = $txResult['param'];

            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }

        }

        return $finalize($param);
    }


}