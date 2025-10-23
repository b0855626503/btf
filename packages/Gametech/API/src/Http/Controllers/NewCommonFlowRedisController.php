<?php

namespace Gametech\API\Http\Controllers;

use App\Services\GameLogRedisService;
use Illuminate\Support\Facades\DB;
use Gametech\API\Models\GameLogProxy;
use Gametech\API\Traits\LogSeamless;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class NewCommonFlowRedisController extends AppBaseController
{
    use LogSeamless;

    protected $gameLogRedis;
    protected $redis;
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
        GameLogRedisService   $gameLogRedis,
        Request               $request
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
        if (in_array($productId, [
            'UMBET', 'LALIKA', 'AFB1188', 'VIRTUAL_SPORT', 'COCKFIGHT', 'AMBSPORTBOOK', 'SABASPORTS',
            'SBO', 'AOG', 'FB_SPORT', 'DB SPORTS'
        ])) {
            $this->days = 7;
        }

        // NewCommonFlowRedisController::__construct()
        $this->expireAt = $this->now->copy()->addDays($this->days)->toISOString();

//        $this->expireAt = new UTCDateTime($this->now->copy()->addDays($this->days));

        $username = $request->input('username');
        $token = $request->input('token', $request->input('sessionToken'));

        $query = MemberProxy::without('bank')->where('user_name', $username)->where('enable', 'Y');
        if ($token) {
            $query->where('session_id', $token);
        }

        $this->member = $query->first();
    }

    // =================== LOG CREATE & UPDATE ===================

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

    protected function buildRedisKey($user, $company, $method, $find, $txnid, $roundId)
    {
        $parts = ['game', 'log', $user, $company, $method, $find];
        if ($find === 'both') {
            $parts[] = $txnid;
            $parts[] = $roundId;
        } elseif ($find === 'con_1') {
            $parts[] = $txnid;
        } else {
            $parts[] = $roundId;
        }
        $buildkey = implode(':', $parts);
        Log::channel('gamelog')->debug("Redis BUILD KEY FOR CREATE: $buildkey");
        return $buildkey;
    }

    protected function buildRedisKeySearch($user, $company, $method, $find, $txnid, $roundId)
    {
        $parts = ['game', 'log', $user, $company, $method, $find];
        if ($find === 'both') {
            $parts[] = $txnid;
            $parts[] = $roundId;
        } elseif ($find === 'con_1') {
            $parts[] = $txnid;
        } else {
            $parts[] = $roundId;
        }
        $buildkey = implode(':', $parts);
        Log::channel('gamelog')->debug("Redis BUILD KEY FOR SEARCH: $buildkey");
        return $buildkey;
    }

    protected function getLastLogId($action, $user, $company, $method, $find, $txnid, $roundId, $con_3 = null, $con_4 = false)
    {
        if ($action === 'bet') {
            $methods = strtoupper($method) === 'ALL' ? ['OPEN', 'WAITING'] : [strtoupper($method)];
        } elseif ($action === 'settled') {
            $methods = strtoupper($method) === 'ALL' ? ['OPEN', 'SETTLED', 'WAITING'] : [strtoupper($method)];

        } elseif ($action === 'settled_dup') {
            $methods = strtoupper($method) === 'ALL' ? ['SETTLED'] : [strtoupper($method)];

        } else {
            $methods = strtoupper($method) === 'ALL' ? [strtoupper($method)] : [strtoupper($method)];
        }

        $redisKeysMiss = [];
        foreach ($methods as $m) {
            $redisKey = $this->buildRedisKeySearch($user, $company, $m, $find, $txnid, $roundId);


            $ids = $this->redis->zrevrange($redisKey, 0, 5);

            if (empty($ids)) {
                Log::channel('gamelog')->debug("Redis MISS for key: $redisKey");
                $redisKeysMiss[] = $redisKey;
                continue;
            }
            foreach ($ids as $id) {
                $addonKey = "game:log:$user:$company:addon:$id";
                Log::channel('gamelog')->debug("CHECKING addonKey: $addonKey con_3=" . json_encode($con_3) . " con_4=" . json_encode($con_4));
                $addon = $this->redis->hgetall($addonKey);

                if (empty($addon)) {
                    Log::channel('gamelog')->debug("NOT FOUND addon for log_id=$id");
                    continue;
                }

                Log::channel('gamelog')->debug("FOUND addon for log_id=$id: " . json_encode($addon));

                $matchCon3 = false;
                if ($con_3 === null) {
                    $matchCon3 = ($addon['con_3'] ?? null) === 'null';
                } elseif ($con_3 === 'ALL') {
                    $matchCon3 = in_array($addon['con_3'] ?? '', ['OPEN', 'WAITING', 'true', 'false'], true);

                } elseif ($con_3 === 'bool') {
                    $matchCon3 = in_array($addon['con_3'] ?? '', ['true', 'false'], true);
                } elseif ($con_3 === 'none') {
                    $matchCon3 = true;
                } else {
                    $matchCon3 = ($addon['con_3'] ?? null) === $con_3;
                }

                $norm = function ($v) {
                    if ($v === null) return null;
                    $t = is_string($v) ? strtolower(trim($v)) : $v;
                    return ($t === '' || $t === 'null') ? null : (string)$v;
                };

                $matchCon4 = true;
                if ($con_4 !== false) {
                    $matchCon4 = $norm($addon['con_4'] ?? null) === $norm($con_4);
                }

                Log::channel('gamelog')->debug("matchCon3 for $con_3 - " . $addon['con_3'] . " log_id=$id: " . $matchCon3);
                Log::channel('gamelog')->debug("matchCon4 for $con_4 - " . $addon['con_4'] . " log_id=$id: " . $matchCon4);

                if ($matchCon3 && $matchCon4) {
                    Log::channel('gamelog')->debug("FOUND Match Seach addon for log_id=$id: " . json_encode($addon));
                    return $id;
                }
            }
        }

        if (!empty($redisKeysMiss)) {
            Log::channel('gamelog')->debug('Redis MISS keys: ' . implode(', ', $redisKeysMiss));
        }
        Log::channel('gamelog')->debug("Redis MISS (getLastLogId): user=$user company=$company method=$method con_3=" . json_encode($con_3) . " con_4=" . json_encode($con_4));

        return null;
    }

    protected function safeIncreaseBalance($amount)
    {
        return DB::transaction(function () use ($amount) {
            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
            $member->increment($this->balances, $amount);
            $this->member->refresh();
            return true;
        });
    }

    protected function safeDecrementBalance($amount)
    {
        return DB::transaction(function () use ($amount) {
            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
            if ($member->balance < $amount) {
                return false;
            }
            $member->decrement($this->balances, $amount);
            $this->member->refresh();
            return true;
        });
    }

    protected function getLogIds($action, $user, $company, $method, $find, $txnid, $roundId, $con_3 = null, $con_4 = false)
    {
        $logIds = [];
        if ($action === 'bet') {
            $methods = strtoupper($method) === 'ALL' ? ['OPEN', 'WAITING'] : [strtoupper($method)];
        } else if ($action === 'settled') {
            $methods = strtoupper($method) === 'ALL' ? ['OPEN', 'SETTLED', 'WAITING'] : [strtoupper($method)];
        } else {
            $methods = strtoupper($method) === 'ALL' ? [strtoupper($method)] : [strtoupper($method)];
        }

        Log::channel('gamelog')->debug('ค้นหาแบบ หลายรายการ ', [
            'action' => $action,
            'method' => $method,
            'con_1' => $txnid,
            'con_2' => $roundId,
            'con_3' => $con_3,
            'con_4' => $con_4,
            'get_method' => $methods
        ]);


        $redisKeysMiss = [];

        foreach ($methods as $m) {
            $redisKey = $this->buildRedisKeySearch($user, $company, $m, $find, $txnid, $roundId);

            Log::channel('gamelog')->debug('ค้นหาแบบ หลายรายการ  สร้างคีย์', [
                'action' => $action,
                'method' => $method,
                'con_1' => $txnid,
                'con_2' => $roundId,
                'con_3' => $con_3,
                'con_4' => $con_4,
                'get_method' => $methods,
                'loop as' => $m,
                'key' => $redisKey
            ]);
            $ids = $this->redis->zrevrange($redisKey, 0, -1);

            if (empty($ids)) {


                $redisKeysMiss[] = $redisKey;
                Log::channel('gamelog')->debug("ค้นหาแบบ หลายรายการ  Redis MISS for key : $redisKey", [
                    'action' => $action,
                    'method' => $method,
                    'con_1' => $txnid,
                    'con_2' => $roundId,
                    'con_3' => $con_3,
                    'con_4' => $con_4,
                    'get_method' => $methods,
                    'loop as' => $m,
                    'key' => $redisKey
                ]);

                continue;
            }

            Log::channel('gamelog')->debug("ค้นหาแบบ หลายรายการ  พบข้อมูล : $redisKey", [
                'action' => $action,
                'method' => $method,
                'key' => $redisKey,
                'ids' => $ids
            ]);


            foreach ($ids as $id) {
                $addonKey = "game:log:$user:$company:addon:$id";
                Log::channel('gamelog')->debug("CHECKING addonKey: $addonKey con_3=" . json_encode($con_3) . " con_4=" . json_encode($con_4));
                $addon = $this->redis->hgetall($addonKey);

                if (empty($addon)) {
                    Log::channel('gamelog')->debug("NOT FOUND addon for log_id=$id");
                    continue;
                }

                Log::channel('gamelog')->debug("FOUND addon for log_id=$id: " . json_encode($addon));
                $matchCon3 = false;
                if ($con_3 === null) {
                    $matchCon3 = ($addon['con_3'] ?? null) === 'null';
                } elseif ($con_3 === 'ALL') {

                    $matchCon3 = in_array($addon['con_3'] ?? '', ['OPEN', 'WAITING', 'true', 'false', '', null, 'null'], true);

                } elseif ($con_3 === 'bool') {
                    $matchCon3 = in_array($addon['con_3'] ?? '', ['true', 'false'], true);
                } elseif ($con_3 === 'none') {
                    $matchCon3 = true;
                } else {
                    $matchCon3 = ($addon['con_3'] ?? null) === $con_3;
                }

                $norm = function ($v) {
                    if ($v === null) return null;
                    $t = is_string($v) ? strtolower(trim($v)) : $v;
                    return ($t === '' || $t === 'null') ? null : (string)$v;
                };

                $matchCon4 = true;
                if ($con_4 !== false) {
                    $matchCon4 = $norm($addon['con_4'] ?? null) === $norm($con_4);
                }

                Log::channel('gamelog')->debug("matchCon3 for $con_3 - " . $addon['con_3'] . " log_id=$id: " . $matchCon3);
                Log::channel('gamelog')->debug("matchCon4 for $con_4 - " . $addon['con_4'] . " log_id=$id: " . $matchCon4);

                if ($matchCon3 && $matchCon4) {
                    Log::channel('gamelog')->debug("FOUND Match Seach addon for log_id=$id: " . json_encode($addon));
                    $logIds[] = $id;
                }

            }
        }
        return array_unique($logIds);
    }

    protected function getAddon($user, $company, $find)
    {
        $key = "game:log:$user:$company:addon:$find";
        $addon = $this->redis->hgetall($key);
        if (!empty($addon)) {
            return [
                'amount' => (float)$addon['amount'] ?? 0,
                'method' => $addon['method'] ?? '',
                'con_1' => $addon['con_1'] ?? '',
                'con_2' => $addon['con_2'] ?? '',
                'con_3' => $addon['con_3'] ?? '',
                'con_4' => $addon['con_4'] ?? '',
                'created_at' => $addon['created_at'] ?? '',
            ];
        }
        return null;
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

    public function placeBets(Request $request)
    {
        $session = $request->all();
        $param = [];
        $txns = (array)($session['txns'] ?? []);

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog = $this->gameLogRedis;
        $company = (string)($session['productId'] ?? '');
        $gameUser = (string)$this->member->user_name;

        $oldBalance = (float)$this->member->balance;
        $amount = collect($txns)->sum(fn($t) => (float)($t['betAmount'] ?? 0));

        // === main log (response=in) ลง Redis ===
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [], // จะอัปเดตตอนจบ
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'betmain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        // ปิดงาน: อัปเดต output ของ main log
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            // เขียนทั้ง main hash และ addon เผื่อ debug (รองรับใน service)
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper กันซ้ำแบบเทียบทั้ง con_1 + con_2 (ใช้ find แล้วกรองเอง)
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null && $con2 === null) return false;

            // ดึงจากดัชนี BY_TRANSACTION ก่อน (เร็วสุดเมื่อมี con_1)
            if ($con1) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_1' => $con1,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['con_2'] ?? '') === (string)$con2 && ($it['response'] ?? '') === 'in') {
                        return true;
                    }
                }
            }

            // เผื่อกรณีไม่มี con_1 (หรือไม่เจอ) ลองจาก BY_ROUND
            if ($con2) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_2' => $con2,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['con_1'] ?? '') === (string)$con1 && ($it['response'] ?? '') === 'in') {
                        return true;
                    }
                }
            }

            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;     // OPEN / WAITING / ...
            $betAmount = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำจาก Redis (method + con_1 + con_2)
            if ($isDup((string)$status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                break;
            }

            // 2) ถ้าเป็น OPEN → มี WAITING ค้างไหม (กันหักซ้ำ)
            if ($status === 'OPEN') {
                $waits = $glog->findGameLogs($gameUser, $company, 'WAITING', [
                    'con_1' => $txnId,
                    'limit' => 10,
                ]);

                $hasWaiting = false;
                foreach ($waits as $it) {
                    if (($it['con_2'] ?? '') === (string)$roundId && ($it['response'] ?? '') === 'in') {
                        $hasWaiting = true;
                        break;
                    }
                }

                if ($hasWaiting) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $this->member->balance) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$this->member->balance,
                        ];

                    // log ย่อยลง Redis
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
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);
                    break;
                }
            }

            // 3) ข้ามการอัปเดตยอด (แต่ยังล็อกลง Redis)
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
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);
                break;
            }

            // 4) หักยอดแบบ TX + LOCK
            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    $current = (float)$member->{$this->balances};
                    $newBal = $current - $betAmount;

                    if ($newBal < 0) {
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
                    }

                    $after = (float)$member->{$this->balances};
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, $after) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$after,
                        ];

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
                        'log' => $log,
                        'member_balance' => (float)$after,
                    ];
                }, 1);

                if (!$txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // บันทึก log ย่อยลง Redis
                $glog->saveGameLogToRedis($txResult['log']);

                // ตอบกลับล่าสุด
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


    public function placeBets_(Request $request)
    {
        Log::channel('gamelog')->debug("Start placebet-----------");

        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;
        $amount = collect($session['txns'])->sum('betAmount');

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'betmain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->gameLogRedis->saveGameLogToRedis($log);

        foreach ($session['txns'] as $txn) {

            $find = $this->getLastLogId('bet', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], $txn['status']);

            if ($find) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($txn['status'] === 'OPEN') {

                $waitingExists = $this->getLastLogId('bet', $this->member->user_name, $session['productId'], 'WAITING', 'both', $txn['id'], $txn['roundId'], $txn['status']);

                if ($waitingExists) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$this->member->balance,
                        ];
                    $this->gameLogRedis->saveGameLogToRedis([
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'],
                        'game_user' => $this->member->user_name,
                        'method' => $txn['status'],
                        'response' => 'in',
                        'amount' => $txn['betAmount'],
                        'con_1' => $txn['id'],
                        'con_2' => $txn['roundId'],
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);
                    break;
                }
            }

            $betAmount = $txn['betAmount'];
            $skipUpdate = $txn['skipBalanceUpdate'] ?? false;

            if (!$skipUpdate) {
                $newBalance = $this->member->balance - $betAmount;

//				if ($newBalance < 0) {
//					$param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
//					break;
//				}

                if (!$this->safeDecrementBalance($betAmount)) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }

            }

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $this->gameLogRedis->saveGameLogToRedis([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $betAmount,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => null,
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ]);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);
        Log::channel('gamelog')->debug('placeBets Complete Step');
        return $param;
    }

    public function settleBets(Request $request)
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
        $amount = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // === main log (response=in) ลง Redis ===
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [], // อัปเดตตอนจบ
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'settlemain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $company,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => (float)$this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // ช่วยดึง logs จาก Redis พร้อม log_id
        $pullLogs = function (string $method, array $opt) use ($glog, $gameUser, $company): array {
            $res = $glog->queryGameLogs($gameUser, $company, $method, array_merge([
                'mode' => 'list',
                'limit' => 200,
            ], $opt));
            return $res['logs'] ?? [];
        };

        foreach ($txns as $txn) {
            $isSingleState = (bool)($txn['isSingleState'] ?? false);
            $skipBalanceUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);
            $isFeature = (bool)($txn['isFeature'] ?? false);
            $isFeatureBuy = (bool)($txn['isFeatureBuy'] ?? false);
            $isEndRound = array_key_exists('isEndRound', $txn) ? (bool)$txn['isEndRound'] : true;
            $ismulti = ($isFeature || $isFeatureBuy || !$isEndRound);
            $transactionType = $txn['transactionType'] ?? 'BY_TRANSACTION';

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $payout = (float)($txn['payoutAmount'] ?? 0);
            $betAmt = (float)($txn['betAmount'] ?? 0);

            // ===== 1) single-state: หัก OPEN ก่อน (ถ้าไม่ skip) =====
            if ($isSingleState) {
                if (!$skipBalanceUpdate) {
                    // กันซ้ำ OPEN (method=OPEN + con_1 + con_2)
                    $dupOpen = $pullLogs('OPEN', [
                        'transaction_type' => 'BY_TRANSACTION',
                        'con_1' => $txnId,
                    ]);
                    $dupOpen = array_values(array_filter($dupOpen, fn($r) => ($r['con_2'] ?? '') === (string)$roundId && ($r['response'] ?? '') === 'in' && ($r['con_4'] ?? 'null') === 'null'
                    ));
                    if (!empty($dupOpen)) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                        break;
                    }

                    try {
                        $res = DB::transaction(function () use ($betAmt, $session, $txn, $txnId, $roundId, $oldBalance, $company) {
                            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                            $newBalance = (float)$member->{$this->balances} - $betAmt;
                            if ($newBalance < 0) {
                                return [
                                    'ok' => false,
                                    'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
                                ];
                            }

                            if ($betAmt > 0) {
                                $member->decrement($this->balances, $betAmt);
                                $member->refresh();
                            }

                            return [
                                'ok' => true,
                                'bal' => (float)$member->{$this->balances},
                            ];
                        }, 1);

                        if (!$res['ok']) {
                            $param = $res['param'];
                            break;
                        }

                        // log OPEN ลง Redis (นอก TX)
                        $glog->saveGameLogToRedis([
                            'input' => $txn,
                            'output' => [],
                            'company' => $company,
                            'game_user' => $gameUser,
                            'method' => 'OPEN',
                            'response' => 'in',
                            'amount' => $betAmt,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => null,
                            'con_4' => null,
                            'before_balance' => (float)$oldBalance,
                            'after_balance' => (float)$this->member->balance,
                            'date_create' => $this->now->toDateTimeString(),
                            'expireAt' => $this->expireAt,
                        ]);
                    } catch (\Throwable $e) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + [
                                'message' => $e->getMessage(),
                            ];
                        break;
                    }
                } else {
                    // ไม่หักยอด แต่สร้างรอย OPEN
                    $glog->saveGameLogToRedis([
                        'input' => $txn,
                        'output' => [],
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => 'OPEN',
                        'response' => 'in',
                        'amount' => $betAmt,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => null,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);
                }
            }

            // ===== 2) ตรวจ placeBets/OPEN ตาม transactionType =====
            $relatedLogIds = []; // สำหรับ BY_ROUND ไว้ไปผูก con_4 ทีหลัง
            $openLogId = null;

            if (($transactionType ?? 'BY_TRANSACTION') === 'BY_ROUND') {
                // หา related logs จากหลาย method ที่ยังไม่ถูกปิด (con_4=null)
                $methodsToCheck = ['OPEN', 'WAITING']; // เพิ่มได้ตามที่ใช้งานจริง
                foreach ($methodsToCheck as $mth) {
                    $rows = $pullLogs($mth, [
                        'transaction_type' => 'BY_ROUND',
                        'con_2' => $roundId,
                        'limit' => 10,
                    ]);
                    foreach ($rows as $r) {
                        if (($r['response'] ?? '') === 'in' && ($r['con_4'] ?? 'null') === 'null') {
                            $relatedLogIds[] = $r['log_id'];
                        }
                    }
                }
                $relatedLogIds = array_values(array_unique($relatedLogIds));

                if (empty($relatedLogIds)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, $this->member->balance);
                    break;
                }

                // กันซ้ำ settle รอบนี้ (method=$status + con_2 + con_4=null)
                if (!$ismulti && !$skipBalanceUpdate) {
                    $dupSettleRows = $pullLogs((string)$status, [
                        'transaction_type' => 'BY_ROUND',
                        'con_2' => $roundId,
                        'limit' => 10,
                    ]);
                    $dupSettleRows = array_values(array_filter($dupSettleRows, fn($r) => ($r['response'] ?? '') === 'in' && ($r['con_4'] ?? 'null') === 'null'
                    ));
                    if (!empty($dupSettleRows)) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                        break;
                    }
                }
            } else {
                // BY_TRANSACTION → ต้องมี OPEN ของ txnId
                $openRows = $pullLogs('OPEN', [
                    'transaction_type' => 'BY_TRANSACTION',
                    'con_1' => $txnId,
                    'limit' => 10,
                ]);
                if (empty($openRows)) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, $this->member->balance);
                    break;
                }
                // เอาอันล่าสุดที่ยังไม่ถูกปิด
                $openRows = array_values(array_filter($openRows, fn($r) => ($r['con_4'] ?? 'null') === 'null'));
                if (!empty($openRows)) {
                    $openLogId = $openRows[0]['log_id'];
                }

                if (!$skipBalanceUpdate) {
                    // กันซ้ำ settle เดิม (method=$status + con_1 + con_4=null)
                    $dupSettleRows = $pullLogs((string)$status, [
                        'transaction_type' => 'BY_TRANSACTION',
                        'con_1' => $txnId,
                        'limit' => 10,
                    ]);
                    $dupSettleRows = array_values(array_filter($dupSettleRows, fn($r) => ($r['response'] ?? '') === 'in' && ($r['con_4'] ?? 'null') === 'null'
                    ));
                    if (!empty($dupSettleRows)) {
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, $this->member->balance);
                        break;
                    }
                }
            }

            // ===== 3) เติมเงิน (TX) =====
            $settleResult = [
                'ok' => true,
                'param' => null,
                'logData' => null,
                'member_balance' => (float)$this->member->balance,
            ];

            if (!$skipBalanceUpdate) {
                try {
                    $settleResult = DB::transaction(function () use ($session, $txn, $status, $payout, $roundId, $txnId, $ismulti, $oldBalance, $company, $gameUser) {
                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                        if ($payout > 0) {
                            $member->increment($this->balances, $payout);
                            $member->refresh();
                        }

                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}) + [
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
                            'amount' => $payout,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => $ismulti,
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
                } catch (\Throwable $e) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, $this->member->balance) + [
                            'message' => $e->getMessage(),
                        ];
                    break;
                }
            } else {
                // ไม่อัปเดตยอด แต่ตอบสำเร็จ + เตรียม log
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];

                $settleResult = [
                    'ok' => true,
                    'param' => $param,
                    'logData' => [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $ismulti,
                        'con_4' => null,
                        'before_balance' => (float)$oldBalance,
                        'after_balance' => (float)$this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ],
                    'member_balance' => (float)$this->member->balance,
                ];
            }

            if (!$settleResult['ok']) {
                $param = $settleResult['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10998, $this->member->balance);
                break;
            }

            // ===== 4) เขียน log settle + ผูก con_4 =====
            $settleId = $glog->saveGameLogToRedis($settleResult['logData']);
            $param = $settleResult['param'];

            $settleTag = ($status ?? 'SETTLE') . '_' . $settleId;

            if (($transactionType ?? 'BY_TRANSACTION') === 'BY_ROUND') {
                if (!empty($relatedLogIds)) {
                    $glog->updateLogField($relatedLogIds, 'con_4', $settleTag, $gameUser, $company);
                }
            } else {
                if ($openLogId) {
                    $glog->updateLogField($openLogId, 'con_4', $settleTag, $gameUser, $company);
                }
            }

            // LogSeamless ตามของเดิม
//            LogSeamless::log(
//                $company,
//                $gameUser,
//                $txn,
//                (float)$oldBalance,
//                (float)$settleResult['member_balance']
//            );
        }

        return $finalize($param);
    }

    public function settleBets_(Request $request)
    {
        Log::channel('gamelog')->debug("Start settlebet-----------");
        $session = $request->all();
        $param = [];
        $logOpenMap = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;
        $amount = collect($session['txns'])->sum('payoutAmount');

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'settlemain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $isSingleState = $txn['isSingleState'] ?? false;
            $skipBalanceUpdate = $txn['skipBalanceUpdate'] ?? false;
            $isFeature = $txn['isFeature'] ?? false;
            $isFeatureBuy = $txn['isFeatureBuy'] ?? false;
            $isEndRound = $txn['isEndRound'] ?? true;
            $ismulti = ($isFeature || $isFeatureBuy || !$isEndRound);
            $transactionType = $txn['transactionType'] ?? 'BY_TRANSACTION';

            // 1. Handle isSingleState before settle
            if ($isSingleState) {
                if (!$skipBalanceUpdate) {

                    $existingBet = $this->getLastLogId('settled', $this->member->user_name, $session['productId'], 'OPEN', 'both', $txn['id'], $txn['roundId'], 'OPEN');

                    if ($existingBet) {
                        if ($session['productId'] === 'ASKMEBET') {
                            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                                    'balanceBefore' => (float)$oldBalance,
                                    'balanceAfter' => (float)$this->member->balance,
                                ];
                        } else {
                            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        }

                        break;
                    }


//					$newBalance = $this->member->balance - $txn['betAmount'];
//					if ($newBalance < 0) {
//						$param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
//						break;
//					}
//					$this->member->decrement($this->balances, $txn['betAmount']);

                    if (!$this->safeDecrementBalance($txn['betAmount'])) {
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                        break;
                    }

                }

                $openLog = $this->createGameLog([
                    'input' => $txn,
                    'output' => [],
                    'company' => $session['productId'],
                    'game_user' => $this->member->user_name,
                    'method' => 'OPEN',
                    'response' => 'in',
                    'amount' => $txn['betAmount'],
                    'con_1' => $txn['id'],
                    'con_2' => $txn['roundId'],
                    'con_3' => 'OPEN',
                    'con_4' => null,
                    'before_balance' => $oldBalance,
                    'after_balance' => $this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ], 'Settle Step Create Bet in Single State');

                $logOpenMap[$txn['roundId']][] = $openLog;

                //                Log::info('SETTLE SINGLE START  CREATE OPEN LOG  GET ARRAY ID in $logOpenMap', $logOpenMap);

            }

//            if (!isset($logOpenMap[$txn['roundId']])) {
            Log::channel('gamelog')->debug('Settle step check old bet', [
                'method' => $txn['status'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'logopenmap' => $logOpenMap,
            ]);
            // 2. เช็ค log ว่าเคย placeBets หรือยัง

            //            Log::info('SETTLE CHECK BET BY TRANSACTION');
            if ($transactionType === 'BY_ROUND') {

                Log::channel('gamelog')->debug("Start Settle By ROUND เชค ว่า มี รายการ Bet ไหม");

                $logs = $this->getLogIds('settled', $this->member->user_name, $session['productId'], 'OPEN', 'con_2', $txn['id'], $txn['roundId'], 'OPEN');

                Log::channel('gamelog')->debug('หลังค้นหาหลายรายการ SETTLE เชคว่าเคยมี Placebet หรือยัง', ['logs' => $logs]);

                if (!$logs) {
                    Log::channel('gamelog')->debug("Start Settle By ROUND ไม่เพบ รายการ BET ก่อนหน้านี่");
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }


                if (!$ismulti && !$skipBalanceUpdate) {
                    Log::channel('gamelog')->debug("Settle By ROUND เงื่อนไข ไม่เป็นรายการแบบ หลายรายการ และอัพยอดปกติ");
                    Log::channel('gamelog')->debug('SETTLE เชคว่า เป็น หลายรายการไหม และ con4 null con3 false หา settle ล่าสุด');

                    $dupLog = $this->getLastLogId('settled', $this->member->user_name, $session['productId'], $txn['status'], 'con_2', $txn['id'], $txn['roundId'], "false", null);

                    Log::channel('gamelog')->debug('SETTLE เชคว่า เป็น หลายรายการไหม และ con4 null con3 false หา settle ล่าสุด ได้ผลว่า ', ['logs' => $dupLog]);


                    if ($dupLog) {
                        Log::channel('gamelog')->debug("Start Settle By ROUND พบ รายการ Settle ก่อนนี้");
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        break;
                    }
                }

                Log::channel('gamelog')->debug('SETTLE สิ้นสุด By ROUND');


            } else {

                $log = $this->getLastLogId('settled', $this->member->user_name, $session['productId'], 'OPEN', 'con_1', $txn['id'], $txn['roundId'], "ALL");
                //                Log::info('SETTLE CHECK BET BY TRANSACTION THEN CHECK BET LOG : NO CREATE THEN CHECK BY DB', $log);
                Log::channel('gamelog')->debug('SETTLE เริ่ม By TRAN เชคว่าเคยมี BET ไหมผลคือ ', ['log' => $log]);

                if (!$log) {

                    Log::channel('gamelog')->debug("Start Settle By TRAN ไม่พบ รายการ BET");

                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                if (!$skipBalanceUpdate) {

                    Log::channel('gamelog')->debug("Start Settle By TRAN เชค รายการ Settle ว่าซ้ำไหม");


                    //                    Log::info('SETTLE CHECK BET BY TRANSACTION THEN CHECK BET LOG : NO UPDATE BALANCE');

                    $dupSettle = $this->getLastLogId('settled_dup', $this->member->user_name, $session['productId'], $txn['status'], 'con_1', $txn['id'], $txn['roundId'], 'ALL', null);
                    //                    Log::info('SETTLE CHECK BET BY TRANSACTION THEN CHECK BET LOG : NO UPDATE BALANCE : DUP', $dupSettle);

                    Log::channel('gamelog')->debug('SETTLE  By TRAN เชคว่าเคยมี settle ซ้ำไหม ', ['log' => $dupSettle]);

                    if ($dupSettle) {
                        Log::channel('gamelog')->debug('SETTLE BY TRAN มีรายการ SETTLE แล้ว  ', ['log' => $dupSettle, ' code' => 20002]);
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        break;
                    }
                }

                Log::channel('gamelog')->debug('SETTLE สิ้นสุด by Tran ');

            }

//            }


            Log::channel('gamelog')->debug('settle start refill step', [
                'method' => $txn['status'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId']
            ]);
            // 3. เติมเงิน
            if (!$skipBalanceUpdate) {
                $this->safeIncreaseBalance($txn['payoutAmount']);

            }

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];


            $logData = [
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $txn['payoutAmount'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $ismulti,
                'con_4' => null,
                'status' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ];

            $settleId = $this->createGameLog($logData, 'Settle Create');


            $settleLogMap[$txn['roundId']] = [
                'id' => $settleId,
                'status' => $txn['status'],
            ];

            Log::channel('gamelog')->debug('settleBets after create Settle ', [
                'method' => $txn['status'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'settleId' => $settleId,
                'settleLogMap' => $settleLogMap
            ]);


            if (isset($logOpenMap[$txn['roundId']])) {

                Log::channel('gamelog')->debug('settleBets after create Settle in isset $logOpenMap ', [
                    'method' => $txn['status'],
                    'find' => 'con_2',
                    'con_1' => $txn['id'],
                    'con_2' => $txn['roundId'],
                    'settleId' => $settleId,
                    'logOpenMap' => $logOpenMap
                ]);

                foreach ($logOpenMap as $roundId => $openIds) {
                    if (
                        !isset($settleLogMap[$roundId]['id']) ||
                        !isset($settleLogMap[$roundId]['status']) ||
                        !is_array($openIds) ||
                        empty($openIds)
                    ) {
                        continue;
                    }

//						$openIds = array_map(fn ($id) => new ObjectId($id), $openIds);
                    $con4Value = $settleLogMap[$roundId]['status'] . '_' . $settleLogMap[$roundId]['id'];

                    $this->gameLogRedis->updateLogField($openIds, ['con_4' => $con4Value], $this->member->user_name, $session['productId']);


                }

            } else {

                $con4Value = $txn['status'] . '_' . $settleId;

                if ($transactionType === 'BY_ROUND') {
//						$logObjectIds = array_map(fn ($id) => $id instanceof ObjectId ? $id : new ObjectId($id), $logs);

                    $this->gameLogRedis->updateLogField($logs, ['con_4' => $con4Value], $this->member->user_name, $session['productId']);


                } elseif (isset($log)) {
//						$logObjectId = $log instanceof ObjectId ? $log : new ObjectId($log);

                    $this->gameLogRedis->updateLogField($log, ['con_4' => $con4Value], $this->member->user_name, $session['productId']);

                }
            }

            //            LogSeamless::log(
            //                $session['productId'],
            //                $this->member->user_name,
            //                $txn,
            //                $oldBalance,
            //                $this->member->balance
            //            );
        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        //        Log::info('SETTLE  COMPLETE : END', ['log' => $mainLog]);

        return $param;
    }

    public function unsettleBets(Request $request)
    {
        Log::channel('gamelog')->debug("Start unsettle-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $existing = $this->getLastLogId($this->member->user_name, $session['productId'], 'unsettle', 'both', $session['id'], $session['productId']);

        if ($existing) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
        }

        $totalAmount = 0;
        foreach ($session['txns'] as $txn) {
            $totalAmount += $txn['payoutAmount'];
        }

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'unsettle',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {

            $logDup = $this->getLastLogId($this->member->user_name, $session['productId'], 'unsettlesub', 'both', $txn['id'], $txn['roundId'], $txn['status'], null);

            if ($logDup) {
                return $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
            }

            if ($txn['betAmount'] > 0) {
                $this->member->decrement($this->balances, $txn['betAmount']);
                $method = 'betsub';
                $amount = $txn['betAmount'];
            } else {

                $settledLog = $this->getLastLogId($this->member->user_name, $session['productId'], 'paysub', 'both', $txn['id'], $txn['roundId'], $txn['status'], null);

                if (!$settledLog) {
                    return $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                }

                if ($this->member->balance - $txn['payoutAmount'] < 0) {
                    return $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                }

                $this->member->decrement($this->balances, $txn['payoutAmount']);
                $method = 'unsettlesub';
                $amount = $txn['payoutAmount'];
            }

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $logId = $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $this->game,
                'game_user' => $this->member->user_name,
                'method' => $method,
                'response' => 'in',
                'amount' => $amount,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ])->id;

            if (isset($settledLog)) {

                GameLogProxy::where('_id', $settledLog)
                    ->update(['con_4' => 'unsettle_' . $logId]);

                GameLogProxy::where('con_4', 'settle_' . $settledLog->_id)
                    ->update(['con_4' => null]);
            }

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
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

        // main log เปิดหัว (ลง Redis)
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

        // หา base log ด้วยคู่กุญแจ (method, con_1, con_2) และต้อง con_4 = null
        $findBase = function (string $method, string $con1, string $con2) use ($glog, $gameUser, $company) {
            // ใช้ดัชนี con_1 แล้วกรอง con_2 ให้ตรง (เร็วสุด)
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'mode' => 'list',
                'con_1' => $con1,
                'limit' => 10,
            ]);
            foreach (($res['logs'] ?? []) as $r) {
                if (($r['response'] ?? '') !== 'in') continue;
                if ((string)($r['con_2'] ?? '') !== (string)$con2) continue;
                if ((string)($r['con_3'] ?? '') !== (string)$method) continue; // ตามโครงเดิม
                if (($r['con_4'] ?? 'null') !== 'null') continue;              // ต้องยังไม่ปิด
                return $r; // มี log_id และ amount เดิม
            }
            return null;
        };

        foreach ($txns as $txn) {
            $txnId = (string)($txn['id'] ?? '');
            $roundId = (string)($txn['roundId'] ?? '');
            $status = (string)($txn['status'] ?? '');
            $newBet = (float)($txn['betAmount'] ?? 0.0);

            // ต้องมี con_1 และ con_2 ครบ
            if ($txnId === '' || $roundId === '' || $status === '') {
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20001,
                    (float)$this->member->balance
                );
                break;
            }

            // หา base log จาก Redis: (method, con_1, con_2, con_4=null)
            $orig = $findBase($status, $txnId, $roundId);
            if (!$orig) {
                // ไม่พบรายการต้นทางให้ปรับ
                $param = $this->responseData(
                    $session['id'] ?? null,
                    $session['username'] ?? '',
                    $company,
                    20001,
                    (float)$this->member->balance
                );
                break;
            }

            $origBet = (float)($orig['amount'] ?? 0.0);
            $diff = $newBet - $origBet; // >0 ตัดเพิ่ม, <0 คืนเงิน, =0 ไม่ขยับยอด

            try {
                $txResult = DB::transaction(function () use ($diff, $newBet, $session, $txn, $status, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    // ล็อก member เพื่อป้องกันแข่งกัน
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($diff > 0) {
                        if ((float)$member->{$this->balances} < $diff) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
                                'log' => null,
                            ];
                        }
                        $member->decrement($this->balances, $diff);
                    } elseif ($diff < 0) {
                        $member->increment($this->balances, abs($diff));
                    }
                    $member->refresh();

                    // response หลังปรับยอด
                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // log ราย txn (ลง Redis นอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $newBet, // เก็บเป็น "ยอดใหม่" ตามของเดิม
                        'con_1' => $txnId,
                        'con_2' => $roundId,
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
                        'log' => $logData,
                        'after_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // เขียน log ปรับยอดลง Redis + ผูก base log ด้วย con_4
                $adjustId = $glog->saveGameLogToRedis($txResult['log']);
                $glog->updateLogField($orig['log_id'], 'con_4', 'ADJUSTBET_' . $adjustId, $gameUser, $company);

                // LogSeamless (นอก TX)
//                LogSeamless::log(
//                    $company,
//                    $gameUser,
//                    $txn,
//                    (float) $oldBalance,
//                    (float) $txResult['after_balance']
//                );

                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function adjustBets_(Request $request)
    {
        Log::channel('gamelog')->debug("Start adjust-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'adjustbetmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {

            Log::channel('gamelog')->debug("Starting adjust-----------");
            $log = $this->getLastLogId('adjustbet', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], $txn['status'], null);
            Log::channel('gamelog')->debug("adjustbet Search success", ['log' => $log]);
            if (!$log) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            Log::channel('gamelog')->debug("adjustbet หา Addon ของ ", ['log' => $log]);
            $addon = $this->getAddon($this->member->user_name, $session['productId'], $log);
            Log::channel('gamelog')->debug("adjustbet ได้ข้อมูล addon success", ['log' => $log, 'addon' => $addon]);
            $oldBetAmount = $addon['amount'] ?? 0;
            $newBetAmount = $txn['betAmount'];

            $testBalance = ($this->member->balance + $oldBetAmount) - $newBetAmount;
            if ($testBalance < 0) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                break;
            }

            // Adjust balance atomically
            DB::transaction(function () use ($oldBetAmount, $newBetAmount) {
                $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();
                $member->increment($this->balances, $oldBetAmount);
                $member->decrement($this->balances, $newBetAmount);
                $this->member->refresh();
            });


            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $logId = $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $txn['betAmount'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'adjust create open bet log');


            Log::channel('gamelog')->debug("adjustbet หลัง สร้าง log open bet ใหม่ ก็จะ ปรับ รายการเก่า ว่า ถูกดำเนินการแล้ว addon success", ['log' => $log, 'addon' => $addon, 'logid' => $logId]);
//				$logObjectId = $log instanceof ObjectId ? $log : new ObjectId($log);
            $this->gameLogRedis->updateLogField($log, ['con_4' => 'ADJUSTBET_' . $logId], null, $this->member->user_name, $session['productId']);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
    }


    public function cancelBets(Request $request)
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

        // main log เปิดหัว (ลง Redis)
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

        // ===== helpers (Redis) =====

        // ดึง logs ของคู่ (method, con_1, con_2) ที่ยังไม่ถูกปิด (con_4=null)
        $fetchPairOpenLogs = function (string $method, string $con1, string $con2) use ($glog, $gameUser, $company): array {
            $res = $glog->queryGameLogs($gameUser, $company, $method, [
                'mode' => 'list',
                'con_1' => $con1,
                'limit' => 200,
            ]);
            $rows = $res['logs'] ?? [];
            return array_values(array_filter($rows, function ($r) use ($con2) {
                return ($r['response'] ?? '') === 'in'
                    && (string)($r['con_2'] ?? '') === (string)$con2
                    && (($r['con_4'] ?? 'null') === 'null');
            }));
        };

        // มี cancel เดิมของคู่เดียวกันแล้วหรือยัง (method=$status, con_1, con_2, con_4=null)
        $isDuplicateCancel = function (string $status, string $con1, string $con2) use ($glog, $gameUser, $company): bool {
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'mode' => 'list',
                'con_1' => $con1,
                'limit' => 50,
            ]);
            foreach (($res['logs'] ?? []) as $r) {
                if (($r['response'] ?? '') !== 'in') continue;
                if ((string)($r['con_2'] ?? '') !== (string)$con2) continue;
                if ((string)($r['con_3'] ?? '') !== (string)$status) continue;
                if (($r['con_4'] ?? 'null') === 'null') return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = (string)($txn['id'] ?? '');
            $roundId = (string)($txn['roundId'] ?? '');
            $status = (string)($txn['status'] ?? 'CANCELLED');  // เช่น CANCELLED / REJECT
            $reqAmount = (float)($txn['betAmount'] ?? 0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN'; // ต้นตอเงินเดิมพันของคู่นี้

            // ต้องมี con_1 + con_2 (id, roundId) ครบเสมอ
            if ($txnId === '' || $roundId === '') {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            // กันซ้ำ cancel คู่เดิม
            if ($isDuplicateCancel($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // หา base logs ของคู่เดียวกัน (OPEN หรือ WAITING) ที่ยังไม่ปิด
            $baseLogs = $fetchPairOpenLogs($logMethod, $txnId, $roundId);
            if (empty($baseLogs)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            // รวมยอดที่เคยหักจริงทั้งหมดของคู่นี้ (อาจมีมากกว่า 1 log ก็ได้)
            $baseAmount = 0.0;
            foreach ($baseLogs as $lg) {
                $baseAmount += (float)($lg['amount'] ?? 0);
            }

            try {
                // ทำยอดเงินภายใต้ TX + lockForUpdate (ตรรกะเดิม)
                $txRes = DB::transaction(function () use ($session, $txn, $status, $reqAmount, $baseAmount, $oldBalance, $company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($reqAmount > $baseAmount) {
                        $newBal = (float)$member->{$this->balances} - $baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
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
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $reqAmount,
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

                // เขียน log cancel (Redis) + ผูก base logs ด้วย con_4
                $cancelId = $glog->saveGameLogToRedis($txRes['logData']);
                $cancelTag = ($status ?: 'CANCEL') . '_' . $cancelId;

                $baseIds = array_map(fn($r) => $r['log_id'], $baseLogs);
                if (!empty($baseIds)) {
                    $glog->updateLogField($baseIds, 'con_4', $cancelTag, $gameUser, $company);
                }

                // LogSeamless
//                LogSeamless::log(
//                    $company,
//                    $gameUser,
//                    $txn,
//                    (float)$oldBalance,
//                    (float)$txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }

    public function cancelBets_(Request $request)
    {
        Log::channel('gamelog')->debug("Start CancelBets-----------");
        $session = $request->all();
        $param = [];
        $isArray = false;

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'cancelmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $exists = $this->getLastLogId('cancelbet', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], 'none', null);

            if ($exists) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $logMethod = ($txn['status'] === 'REJECT') ? 'WAITING' : 'OPEN';

            if ($txn['transactionType'] === 'BY_ROUND') {
                $logs = $this->getLogIds('cancelbet', $this->member->user_name, $session['productId'], $logMethod, 'con_2', $txn['id'], $txn['roundId'], 'none', null);

                if (!$logs) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }


                $sumAmount = 0;
                foreach ($logs as $log) {
                    $addon = $this->getAddon($this->member->user_name, $session['productId'], $log);
                    $sumAmount += $addon['amount'];
                }

                $betAmount = $sumAmount;
                $isArray = true;

            } else {
                $log = $this->getLastLogId('cancelbet', $this->member->user_name, $session['productId'], $logMethod, 'con_1', $txn['id'], $txn['roundId'], $logMethod, null);


                if (!$log) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                $addon = $this->getAddon($this->member->user_name, $session['productId'], $log);

                $betAmount = $addon['amount'];
            }

            if ($txn['betAmount'] > $betAmount) {
                $this->safeDecrementBalance($betAmount);
//                $this->member->decrement($this->balances, $betAmount);
            }

            $this->safeIncreaseBalance($txn['betAmount']);

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $logId = $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $txn['betAmount'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => false,
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'cancelbet create ' . $txn['status'] . ' log');

            if ($isArray) {
//					$logObjectIds = array_map(fn ($id) => $id instanceof ObjectId ? $id : new ObjectId($id), $logs);

                $this->gameLogRedis->updateLogField($logs, ['con_4' => $txn['status'] . '_' . $logId], $this->member->user_name, $session['productId']);
            } else {
//					$logObjectId = $log instanceof ObjectId ? $log : new ObjectId($log);

                $this->gameLogRedis->updateLogField($log, ['con_4' => $txn['status'] . '_' . $logId], $this->member->user_name, $session['productId']);

            }

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
    }

    public function rollback(Request $request)
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
        $txns = (array)($session['txns'] ?? []);

        // === main log (ลง Redis) ===
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

        // ===== helpers =====

        // ดึง base log ล่าสุดของคู่ (con1, con2) ในกลุ่ม method ['REFUND','SETTLED'] ที่ con_4=null
        $findLatestBase = function (string $con1, string $con2) use ($glog, $gameUser, $company): ?array {
            $methods = ['REFUND', 'SETTLED'];
            $cands = [];

            foreach ($methods as $m) {
                $res = $glog->queryGameLogs($gameUser, $company, $m, [
                    'mode' => 'list',
                    'con_1' => $con1,     // index เร็วสุด
                    'limit' => 150,
                ]);
                foreach (($res['logs'] ?? []) as $r) {
                    if (($r['response'] ?? '') !== 'in') continue;
                    if ((string)($r['con_2'] ?? '') !== (string)$con2) continue;
                    if (($r['con_4'] ?? 'null') !== 'null') continue; // ต้องยังไม่ถูกปิด
                    $cands[] = $r; // มีทั้ง log_id และ created_at (มาจาก addon)
                }
            }

            if (empty($cands)) return null;

            // เลือกตัว "ล่าสุด" ตาม created_at (ISO) ถ้าไม่มีใช้ท้ายลิสต์
            usort($cands, function ($a, $b) {
                $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                return $tb <=> $ta; // ใหม่ก่อน
            });

            return $cands[0];
        };

        // กันซ้ำ rollback: มี ROLLBACK คู่เดียวกันและยัง con_4=null อยู่ไหม
        $isDuplicateRollback = function (string $status, string $con1, string $con2) use ($glog, $gameUser, $company): bool {
            $res = $glog->queryGameLogs($gameUser, $company, $status, [
                'mode' => 'list',
                'con_1' => $con1,
                'limit' => 50,
            ]);
            foreach (($res['logs'] ?? []) as $r) {
                if (($r['response'] ?? '') !== 'in') continue;
                if ((string)($r['con_2'] ?? '') !== (string)$con2) continue;
                if ((string)($r['con_3'] ?? '') !== (string)$status) continue;
                if (($r['con_4'] ?? 'null') === 'null') return true;
            }
            return false;
        };

        foreach ($txns as $txn) {
            $status = (string)($txn['status'] ?? 'ROLLBACK'); // ใช้ค่า default เดิม
            $txnId = (string)($txn['id'] ?? '');
            $roundId = (string)($txn['roundId'] ?? '');

            // ต้องมี con_1 + con_2 ครบ
            if ($txnId === '' || $roundId === '') {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            // กันซ้ำ ROLLBACK คู่เดียวกัน
            if ($isDuplicateRollback($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // หา base log ล่าสุดของคู่นี้ (REFUND/SETTLED) ที่ยังไม่ปิด
            $base = $findLatestBase($txnId, $roundId);
            if (!$base) {
                // ของเดิม: BY_TRANSACTION ไม่เจอ base -> 20002 / แต่เราจะถือว่า "ไม่มีรายการให้ย้อน" = 20001
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }

            // คำนวณยอด rollback ตามชนิด base
            $rollbackAmount = 0.0;
            if (($base['method'] ?? '') === 'SETTLED') {
                $rollbackAmount = (float)($txn['payoutAmount'] ?? 0);
            } else { // REFUND
                $rollbackAmount = (float)($txn['betAmount'] ?? 0);
            }

            try {
                // 1) ปรับยอดใน TX (ของเดิม: decrement ได้ ไม่เช็กติดลบ)
                $txRes = DB::transaction(function () use ($session, $txn, $status, $rollbackAmount, $oldBalance, $company) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                    }
                    $member->refresh();

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    return [
                        'param' => $param,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                // 2) เขียน rollback log (Redis)
                $rollbackId = $glog->saveGameLogToRedis([
                    'input' => $txn,
                    'output' => $txRes['param'],
                    'company' => $company,
                    'game_user' => $gameUser,
                    'method' => $status, // ROLLBACK
                    'response' => 'in',
                    'amount' => $rollbackAmount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$txRes['member_balance'],
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);

                // 3) ผูก base ด้วย con_4 -> ชี้ไปยัง rollback ตัวนี้
                $glog->updateLogField($base['log_id'], 'con_4', $status . '_' . $rollbackId, $gameUser, $company);

                // (ออปชัน/ของเดิมมี): เคลียร์ WAITING/OPEN ที่เคยชี้ไป base — ใน Redis ยังไม่มี index ย้อน con_4 จึงขอข้าม
                // ถ้าต้องจริง ๆ แนะนำเพิ่ม index con_4 ใน GameLogRedisService เพื่อค้นกลับแล้ว update ทีหลัง

                // 4) seamless log
//                LogSeamless::log(
//                    $company,
//                    $gameUser,
//                    $txn,
//                    (float)$oldBalance,
//                    (float)$txRes['member_balance']
//                );

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function rollback_(Request $request)
    {
        Log::channel('gamelog')->debug("Start rollback-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'rollbackmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {

            // 1. หา log ที่ rollback นี้ซ้ำหรือยัง
            $isDup = $this->getLastLogId(
                'rollback',
                $this->member->user_name,
                $session['productId'],
                $txn['status'],
                'both',
                $txn['id'],
                $txn['roundId'],
                "ROLLBACK",
                null
            );
            if ($isDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            // 2. หา log REFUND, SETTLED ที่เกี่ยวข้อง เพื่อใช้ rollback
            $methods = ['REFUND', 'SETTLED'];
            $logItems = [];
            foreach ($methods as $method) {
                $id = $this->getLastLogId('rollback',
                    $this->member->user_name,
                    $session['productId'],
                    $method,
                    $txn['transactionType'] === 'BY_ROUND' ? 'con_2' : 'con_1',
                    $txn['id'],
                    $txn['roundId'],
                    "none"
                );
                if ($id) {
                    $logItems[] = [
                        'method' => $method,
                        'id' => $id,
                    ];
                }
            }

            if (empty($logItems)) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            // 3. เลือก log ที่เวลาล่าสุด
            $latest = $this->getLatestAddonFromLogItems($logItems, $this->member->user_name, $session['productId']);
            if (!$latest) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            // 4. คำนวณยอด rollback
            $rollbackAmount = $latest['method'] === 'SETTLED' ? $txn['payoutAmount'] : $txn['betAmount'];
            $this->safeDecrementBalanceMinus($rollbackAmount);

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $logId = $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $rollbackAmount,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => false,
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'rollback create log ' . $txn['status']);

            // 5. อัปเดต con_4 ของ log REFUND/SETTLED ที่ถูก rollback
            $this->gameLogRedis->updateLogField(
                $latest['id'],
                ['con_4' => $txn['status'] . '_' . $logId],
                $this->member->user_name,
                $session['productId']
            );

            // 6. หา log OPEN/WAITING ที่ผูก con_4 กับ REFUND/SETTLED นี้ และ clear con_4 = null
            $openLogIds = [];
            foreach (['OPEN', 'WAITING'] as $openMethod) {
                $candidateIds = $this->getLogIds(
                    $this->member->user_name,
                    $session['productId'],
                    $openMethod,
                    'both',
                    $txn['id'],
                    $txn['roundId'],
                    null,
                    $latest['method'] . '_' . $latest['id']
                );
                foreach ($candidateIds as $oid) {
                    $openLogIds[] = $oid;
                }
            }
            foreach ($openLogIds as $openLogId) {
                $this->updateLogAddonField(
                    $this->member->user_name,
                    $session['productId'],
                    $openLogId,
                    'con_4',
                    'null'
                );
            }
        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);
        return $param;
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
        $amount = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // === main log (response=in) ลง Redis ===
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [], // จะอัปเดตตอนจบ
            'company' => $company,
            'game_user' => $gameUser,
            'method' => 'winrewardmain',
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

        // ปิดงาน: อัปเดต output ของ main log
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // helper กันซ้ำแบบเทียบทั้ง con_1 + con_2 (เหมือนใน placeBets)
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null && $con2 === null) return false;

            if ($con1) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_1' => $con1,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['con_2'] ?? '') === (string)$con2 && ($it['response'] ?? '') === 'in') {
                        return true;
                    }
                }
            }

            if ($con2) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_2' => $con2,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['con_1'] ?? '') === (string)$con1 && ($it['response'] ?? '') === 'in') {
                        return true;
                    }
                }
            }

            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = (string)($txn['status'] ?? ''); // เช่น WIN/LOSE/SETTLE ฯลฯ
            $payout = (float)($txn['payoutAmount'] ?? 0);

            // 1) กันซ้ำตาม method + con_1 + con_2
            if ($isDup($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // 2) ปรับยอด (เครดิตเฉพาะ payout > 0) แบบ TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($payout > 0) {
                        $member->increment($this->balances, $payout);
                        $member->refresh();
                    }

                    $param = $this->responseData(
                            $session['id'] ?? null,
                            $session['username'] ?? '',
                            $company,
                            0,
                            (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log ย่อย (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => (float)$payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
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
                        'log' => $logData,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10998, (float)$this->member->balance);
                    break;
                }

                // 3) เขียน log ย่อยลง Redis (นอก TX)
                $glog->saveGameLogToRedis($txRes['log']);

                // (ถ้าต้องมี Seamless ตามของเดิม ค่อยเปิดใช้)
                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function winRewards_(Request $request)
    {
        Log::channel('gamelog')->debug("Start winreward-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'winrewardmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $logDup = $this->getLastLogId('winreward', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], "false", null);

            if ($logDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $payout = $txn['payoutAmount'] ?? 0;

            $this->safeIncreaseBalance($payout);

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $payout,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => false,
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'winreward create log ' . $txn['status']);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
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

        // === main log (response=in) ลง Redis ===
        $mainLogId = $glog->saveGameLogToRedis([
            'input' => $session,
            'output' => [], // จะอัปเดตตอนจบ
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

        // ปิดงาน main log
        $finalize = function (array $out) use ($glog, $mainLogId, $gameUser, $company) {
            $glog->updateLogField($mainLogId, 'output', $out, $gameUser, $company);
            return $out;
        };

        // กันซ้ำแบบเดียวกับเมธอดอื่น ๆ: เทียบ method + con_1 + con_2
        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null && $con2 === null) return false;

            if ($con1) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_1' => $con1,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string)($it['con_2'] ?? '') === (string)$con2) {
                        return true;
                    }
                }
            }

            if ($con2) {
                $items = $glog->findGameLogs($gameUser, $company, $method, [
                    'con_2' => $con2,
                    'limit' => 10,
                ]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string)($it['con_1'] ?? '') === (string)$con1) {
                        return true;
                    }
                }
            }

            return false;
        };

        // หา SETTLED ต้นทางจาก Redis
        $findSettled = function (?string $txnId, ?string $roundId, string $type) use ($glog, $gameUser, $company) {
            $filters = $type === 'BY_ROUND'
                ? ['con_2' => $roundId, 'limit' => 20]
                : ['con_1' => $txnId, 'limit' => 20];

            $items = $glog->findGameLogs($gameUser, $company, 'SETTLED', $filters);

            // เลือกตัวล่าสุด (อาศัย date_create ถ้ามี; ถ้าไม่มีเรียงตามลำดับที่คืนมา)
            if (empty($items)) return null;

            usort($items, function ($a, $b) {
                $da = $a['date_create'] ?? '';
                $db = $b['date_create'] ?? '';
                return strcmp($db, $da); // desc
            });

            return $items[0]; // ล่าสุด
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = (string)($txn['status'] ?? 'VOID_SETTLED'); // ปกติจะเป็น VOID_SETTLED
            $type = (string)($txn['transactionType'] ?? 'BY_TRANSACTION'); // หรือ BY_ROUND

            // 1) กันซ้ำ
            if ($isDup($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // 2) หา SETTLED ต้นทาง
            $settledLog = $findSettled($txnId, $roundId, $type);
            if (!$settledLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float)$this->member->balance);
                break;
            }
            $settledId = $settledLog['_id'] ?? ($settledLog['id'] ?? null);

            // 3) คำนวณยอดสุทธิที่จะปรับกลับ: คืน bet และหัก payout
            $betAmount = (float)($txn['betAmount'] ?? 0);     // จะเพิ่ม
            $payout = (float)($txn['payoutAmount'] ?? 0);  // จะลด
            $netDelta = $betAmount - $payout;                 // + เพิ่ม, - ลด, 0 คงเดิม

            try {
                // 4) ปรับยอดใน TX + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $txn, $status, $netDelta, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    // ป้องกันติดลบหลังปรับ
                    $candidate = (float)$member->{$this->balances} + $netDelta;
                    if ($candidate < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
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
                            (float)$member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log ย่อย (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $company,
                        'game_user' => $gameUser,
                        'method' => $status,   // VOID_SETTLED
                        'response' => 'in',
                        'amount' => (float)$netDelta, // เก็บ net เพื่ออ่านย้อนหลังง่าย
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
                        'log' => $logData,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // 5) เขียน log ย่อยลง Redis และได้ id
                $childId = $glog->saveGameLogToRedis($txRes['log']);

                // 6) ผูก con_4 ของ SETTLED ต้นทาง -> "VOID_SETTLED_<childId>"
                if ($settledId) {
                    $glog->updateLogField($settledId, 'con_4', ($status ?: 'VOID_SETTLED') . '_' . $childId, $gameUser, $company);
                }

                // (ถ้าต้องการ Seamless ตามระบบเดิม ค่อยเปิด)
                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData(
                        $session['id'] ?? null,
                        $session['username'] ?? '',
                        $company,
                        50001,
                        (float)$this->member->balance
                    ) + ['message' => $e->getMessage()];
                break;
            }
        }

        return $finalize($param);
    }


    public function voidSettled_(Request $request)
    {
        Log::channel('gamelog')->debug("Start void settle-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'voidsettledmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $duplicate = $this->getLastLogId('void', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], $txn['status'], null);

            if ($duplicate) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($txn['transactionType'] === 'BY_ROUND') {
                $settledLog = $this->getLastLogId('void', $this->member->user_name, $session['productId'], 'SETTLED', 'con_2', $txn['id'], $txn['roundId'], 'none');

            } else {

                $settledLog = $this->getLastLogId('void', $this->member->user_name, $session['productId'], 'SETTLED', 'con_1', $txn['id'], $txn['roundId'], 'none');

            }

            if (!$settledLog) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            $this->safeIncreaseBalance($txn['betAmount']);


            $payout = $txn['payoutAmount'];

            if (!$this->safeDecrementBalance($payout)) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                break;
            }


            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $logId = $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $txn['betAmount'] - $payout,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'void create log ' . $txn['status']);

//				$logObjectId = $settledLog instanceof ObjectId ? $settledLog : new ObjectId($settledLog);
            $this->gameLogRedis->updateLogField($settledLog, ['con_4' => $txn['status'] . '_' . $logId], $this->member->user_name, $session['productId']);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
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

        // === main log เปิดหัว (ลง Redis)
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
            if ($con1 === null && $con2 === null) return false;

            if ($con1) {
                $items = $glog->findGameLogs($gameUser, $company, $method, ['con_1' => $con1, 'limit' => 10]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string)($it['con_2'] ?? '') === (string)$con2) return true;
                }
            }
            if ($con2) {
                $items = $glog->findGameLogs($gameUser, $company, $method, ['con_2' => $con2, 'limit' => 10]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string)($it['con_1'] ?? '') === (string)$con1) return true;
                }
            }
            return false;
        };

        foreach ($txns as $txn) {
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = (string)($txn['status'] ?? 'TIPS');
            $amount = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // 1) กันซ้ำ
            if ($isDup($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float)$this->member->balance);
                break;
            }

            // 2) ไม่อัปเดตยอด เพียงบันทึก log และตอบ success
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$this->member->balance) + [
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
                    'amount' => (float)$amount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => null,
                    'con_4' => null,
                    'before_balance' => (float)$oldBalance,
                    'after_balance' => (float)$this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
                ]);

                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $this->member->balance);
                continue;
            }

            // 3) อัปเดตยอดแบบ TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($member->{$this->balances} < $amount) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float)$member->{$this->balances}),
                        ];
                    }

                    if ($amount > 0) {
                        $member->decrement($this->balances, $amount);
                    }
                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float)$member->{$this->balances}) + [
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
                        'amount' => (float)$amount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
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
                        'log' => $logData,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                $glog->saveGameLogToRedis($txRes['log']);
                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float)$this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }


    public function placeTips_(Request $request)
    {
        Log::channel('gamelog')->debug("Start tip-----------");
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'placetipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $tipDup = $this->getLastLogId('tip', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], $txn['status'], null);

            if ($tipDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $amount = $txn['betAmount'] ?? 0;
            $skipUpdate = $txn['skipBalanceUpdate'] ?? false;

            if (!$skipUpdate) {

                if (!$this->safeDecrementBalance($amount)) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }

            }

            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $amount,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'tip create log ' . $txn['status']);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
    }

    public function cancelTips(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'canceltipmain',
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

        $isDup = function (string $method, ?string $con1, ?string $con2) use ($glog, $gameUser, $company): bool {
            if ($con1 === null && $con2 === null) return false;

            if ($con1) {
                $items = $glog->findGameLogs($gameUser, $company, $method, ['con_1' => $con1, 'limit' => 10]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string) ($it['con_2'] ?? '') === (string) $con2) return true;
                }
            }
            if ($con2) {
                $items = $glog->findGameLogs($gameUser, $company, $method, ['con_2' => $con2, 'limit' => 10]);
                foreach ($items as $it) {
                    if (($it['response'] ?? '') === 'in' && (string) ($it['con_1'] ?? '') === (string) $con1) return true;
                }
            }
            return false;
        };

        // หา TIPS ต้นทางจาก Redis
        $findTipsOrigin = function (?string $txnId, ?string $roundId) use ($glog, $gameUser, $company) {
            $items = $glog->findGameLogs($gameUser, $company, 'TIPS', [
                'con_1' => $txnId,
                'limit' => 20,
            ]);
            // กรอง con_2 ให้ตรง และ con_4 ยังว่าง
            $items = array_values(array_filter($items, function ($it) use ($roundId) {
                return (string) ($it['con_2'] ?? '') === (string) $roundId && empty($it['con_4']);
            }));

            if (empty($items)) {
                // ลองค้นด้วย con_2 เฉพาะอย่างเดียว
                $items2 = $glog->findGameLogs($GLOBALS['gameUser'] ?? '', $GLOBALS['company'] ?? '', 'TIPS', [
                    'con_2' => $roundId,
                    'limit' => 20,
                ]);
                $items2 = array_values(array_filter($items2, fn ($it) => (string) ($it['con_1'] ?? '') === (string) $txnId && empty($it['con_4'])));
                $items = $items2;
            }

            if (empty($items)) return null;

            usort($items, function ($a, $b) {
                $da = $a['date_create'] ?? '';
                $db = $b['date_create'] ?? '';
                return strcmp($db, $da);
            });

            return $items[0];
        };

        foreach ($txns as $txn) {
            $txnId   = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status  = (string) ($txn['status'] ?? 'CANCEL_TIP');
            $amount  = (float) ($txn['betAmount'] ?? 0);

            // 1) กันซ้ำ
            if ($isDup($status, $txnId, $roundId)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance);
                break;
            }

            // 2) ต้องมี TIPS ต้นทางที่ยังไม่ถูกผูก con_4
            $tipLog = $findTipsOrigin($txnId, $roundId);
            if (! $tipLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20001, (float) $this->member->balance);
                break;
            }
            $tipId = $tipLog['_id'] ?? ($tipLog['id'] ?? null);

            try {
                // 3) คืนยอดใน TX + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($amount > 0) {
                        $member->increment($this->balances, $amount);
                    }
                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 0, (float) $member->{$this->balances}) + [
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
                        'amount'          => (float) $amount,
                        'con_1'           => $txnId,
                        'con_2'           => $roundId,
                        'con_3'           => null,
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $member->{$this->balances},
                        'date_create'     => $this->now->toDateTimeString(),
                        'expireAt'        => $this->expireAt,
                    ];

                    return [
                        'ok'              => true,
                        'param'           => $param,
                        'log'             => $logData,
                        'member_balance'  => (float) $member->{$this->balances},
                    ];
                }, 1);

                if (! $txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                // 4) เขียน log ยกเลิก และผูก con_4 ที่ต้นทาง
                $childId = $glog->saveGameLogToRedis($txRes['log']);
                if ($tipId) {
                    $glog->updateLogField($tipId, 'con_4', ($status ?: 'CANCEL_TIP') . '_' . $childId, $gameUser, $company);
                }

                // LogSeamless::log($company, $gameUser, $txn, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }


    public function cancelTips_(Request $request)
    {
        Log::channel('gamelog')->debug("Start canceltip-----------");
        $session = $request->all();
        $param = [];
        $isArray = false;

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'canceltipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $txn) {
            $exists = $this->getLastLogId('canceltip', $this->member->user_name, $session['productId'], $txn['status'], 'both', $txn['id'], $txn['roundId'], $txn['status'], null);

            if ($exists) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $checkTip = $this->getLastLogId('canceltip', $this->member->user_name, $session['productId'], 'TIPS', 'both', $txn['id'], $txn['roundId'], 'TIPS', null);

            if (!$checkTip) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

//            $newBalance = $this->member->balance - $txn['betAmount'];
//
//            if ($newBalance < 0) {
//                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
//                break;
//            }

            $this->safeIncreaseBalance($txn['betAmount']);


            $param = $this->responseData($session['id'], $session['username'], $session['productId'], 0, $this->member->balance) + [
                    'balanceBefore' => (float)$oldBalance,
                    'balanceAfter' => (float)$this->member->balance,
                ];

            $this->createGameLog([
                'input' => $txn,
                'output' => $param,
                'company' => $session['productId'],
                'game_user' => $this->member->user_name,
                'method' => $txn['status'],
                'response' => 'in',
                'amount' => $txn['betAmount'],
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ], 'canceltip create log ' . $txn['status']);

        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
    }

    public function adjustBalance(Request $request)
    {
        $session = $request->all();
        $param   = [];

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        /** @var GameLogRedisService $glog */
        $glog     = $this->gameLogRedis;
        $company  = (string) ($session['productId'] ?? '');
        $gameUser = (string) $this->member->user_name;

        $txns       = (array) ($session['txns'] ?? []);
        $oldBalance = (float) $this->member->balance;

        $mainLogId = $glog->saveGameLogToRedis([
            'input'           => $session,
            'output'          => [],
            'company'         => $company,
            'game_user'       => $gameUser,
            'method'          => 'adjustbalancemain',
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

        $isDupAdjust = function (?string $refId, string $status) use ($glog, $gameUser, $company): bool {
            if (! $refId) return false;

            $items = $glog->findGameLogs($gameUser, $company, 'ADJUSTBALANCE', [
                'con_1' => $refId,
                'limit' => 10,
            ]);

            foreach ($items as $it) {
                if (($it['response'] ?? '') === 'in'
                    && (string) ($it['con_2'] ?? '') === (string) $refId
                    && (string) ($it['con_3'] ?? '') === (string) $status) {
                    return true;
                }
            }
            return false;
        };

        foreach ($txns as $item) {
            $refId  = $item['refId'] ?? null;
            $status = (string) ($item['status'] ?? 'DEBIT');   // 'DEBIT' | 'CREDIT'
            $amount = (float) ($item['amount'] ?? 0);

            // 1) กันซ้ำ
            if ($isDupAdjust($refId, $status)) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 20002, (float) $this->member->balance);
                break;
            }

            try {
                // 2) ปรับยอดใน TX + lockForUpdate
                $txRes = DB::transaction(function () use ($session, $item, $status, $amount, $refId, $oldBalance, $company, $gameUser) {
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if ($status === 'DEBIT') {
                        if (($member->{$this->balances} - $amount) < 0) {
                            return [
                                'ok'    => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 10002, (float) $member->{$this->balances}),
                            ];
                        }
                        if ($amount > 0) $member->decrement($this->balances, $amount);
                    } else { // CREDIT
                        if ($amount > 0) $member->increment($this->balances, $amount);
                    }
                    $member->refresh();

                    // response รูปแบบเดิม
                    $param = [
                        'id'              => $session['id'] ?? null,
                        'statusCode'      => 0,
                        'currency'        => 'THB',
                        'productId'       => $company,
                        'username'        => $gameUser,
                        'balanceBefore'   => (float) $oldBalance,
                        'balanceAfter'    => (float) $member->{$this->balances},
                        'timestampMillis' => $this->now->getTimestampMs(),
                    ];

                    $baseLog = [
                        'input'           => $item,
                        'output'          => $param,
                        'company'         => $company,
                        'game_user'       => $gameUser,
                        'response'        => 'in',
                        'amount'          => (float) $amount,
                        'con_1'           => $refId,
                        'con_2'           => $refId,
                        'con_3'           => $status,
                        'con_4'           => null,
                        'before_balance'  => (float) $oldBalance,
                        'after_balance'   => (float) $member->{$this->balances},
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

                // 3) เขียน log ทั้งคู่ลง Redis
                foreach ($txRes['logs'] as $lg) {
                    $glog->saveGameLogToRedis($lg);
                }

                // LogSeamless::log($company, $gameUser, $item, (float) $oldBalance, (float) $txRes['member_balance']);

                $param = $txRes['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $company, 50001, (float) $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        return $finalize($param);
    }


    public function adjustBalance_(Request $request)
    {
        Log::channel('gamelog')->debug("Start aj balance-----------");
        $param = [];
        $session = $request->all();

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        // log หลัก
        $log = [
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'],
            'game_user' => $this->member->user_name,
            'method' => 'adjustbalancemain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'],
            'con_2' => $session['productId'],
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ];

        $mainLog = $this->createGameLogMain($log);

        foreach ($session['txns'] as $item) {
            $checkDup = $this->getLastLogId('adjustbalance',
                $this->member->user_name,
                $session['productId'],
                'ADJUSTBALANCE',
                'both',
                $item['refId'],
                $item['refId'],
                $item['status'],
                null
            );

            if ($checkDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($item['status'] === 'DEBIT') {
                if (!$this->safeDecrementBalance($item['amount'])) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }
            } else {
                $this->safeIncreaseBalance($item['amount']);
            }

            $param = [
                'id' => $session['id'],
                'statusCode' => 0,
                'currency' => 'THB',
                'productId' => $session['productId'],
                'username' => $this->member->user_name,
                'balanceBefore' => (float)$oldBalance,
                'balanceAfter' => (float)$this->member->balance,
                'timestampMillis' => $this->now->getTimestampMs(),
            ];

            // เก็บ log ทั้ง 2 method ลง Redis
            foreach (['ADJUSTBALANCE', 'OPEN'] as $method) {
                $session_in = [];
                $session_in['input'] = $item;
                $session_in['output'] = $param;
                $session_in['company'] = $session['productId'];
                $session_in['game_user'] = $this->member->user_name;
                $session_in['response'] = 'in';
                $session_in['method'] = $method;
                $session_in['amount'] = $item['amount'];
                $session_in['con_1'] = $item['refId'];
                $session_in['con_2'] = $item['refId'];
                $session_in['con_3'] = $item['status'];
                $session_in['before_balance'] = $oldBalance;
                $session_in['after_balance'] = $this->member->balance;
                $session_in['date_create'] = $this->now->toDateTimeString();
                $session_in['expireAt'] = $this->expireAt;
                $this->createGameLog($session_in, 'adjustbalance create log ' . $method);
            }
        }

        $this->gameLogRedis->updateLogField($mainLog, 'output', $param);

        return $param;
    }


}