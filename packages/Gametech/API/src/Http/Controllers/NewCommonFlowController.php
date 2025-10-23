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

class NewCommonFlowController extends AppBaseController
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

    public function placeBets(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = (float) config('api.time_budget.placebets', 3.5);

        // เริ่มเวลา (รวม middleware)
        $startedAt = (float) ($request->server('REQUEST_TIME_FLOAT') ?? microtime(true));
        $elapsed = static fn () => microtime(true) - $startedAt;
        $guard   = static fn () => $elapsed() <= $TIME_LIMIT;
        $guardHeadroom = static fn (float $need) => ($TIME_LIMIT - $elapsed()) >= $need;

        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, 0) + [
//                    'elapsed' => round($elapsed(), 3),
//                    'message' => 'Processing time exceeded limit',
//                    'balanceBefore' => (float) $this->member->balance,
//                    'balanceAfter'  => (float) $this->member->balance,
                ];
        };

        $session  = $request->all();
        $param    = [];
        $timedOut = false;

        $txns = (array) ($session['txns'] ?? []);

        if (! $this->member) {
            // กรณีไม่มี member: ตอบกลับทันทีตามเดิม
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $oldBalance = $this->member->balance;
        $amount     = collect($txns)->sum(fn ($t) => (float) ($t['betAmount'] ?? 0));

        // main log ตั้งแต่ต้น
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

        // คลอเชอร์ finalize: การันตีอัปเดต mainLog->output ทุกทางออก
        $finalize = function (array $out) use ($mainLog) {
            $mainLog->output = $out;
            $mainLog->save();
            return $out;
        };

        // ถ้าเวลาหมดหลังสร้าง mainLog → ตอบด้วยการ finalize
        if (! $guard()) {
            return $finalize($timeoutResponse($session));
        }

        foreach ($txns as $txn) {
            if (! $guard()) {
                $param    = $timeoutResponse($session);
                $timedOut = true;
                break;
            }

            $txnId      = $txn['id']       ?? null;
            $roundId    = $txn['roundId']  ?? null;
            $status     = $txn['status']   ?? null;
            $betAmount  = (float) ($txn['betAmount'] ?? 0);
            $skipUpdate = (bool) ($txn['skipBalanceUpdate'] ?? false);

            // กันซ้ำ
            $txnDup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->exists();

            if ($txnDup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // OPEN + เคย WAITING มาก่อน => สำเร็จโดยไม่หักซ้ำ
            if ($status === 'OPEN') {
                if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

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
                    if (! $timedOut) {
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
                            'con_3'          => $status,
                            'con_4'          => null,
                            'before_balance' => $oldBalance,
                            'after_balance'  => $this->member->balance,
                            'date_create'    => $this->now->toDateTimeString(),
                            'expireAt'       => $this->expireAt,
                        ]);
                    }
                    break;
                }
            }

            // แค่สถานะ ไม่ต้องอัปเดตยอด
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float) $oldBalance,
                        'balanceAfter'  => (float) $this->member->balance,
                    ];
                if (! $timedOut) {
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
                        'con_3'          => $status,
                        'con_4'          => null,
                        'before_balance' => $oldBalance,
                        'after_balance'  => $this->member->balance,
                        'date_create'    => $this->now->toDateTimeString(),
                        'expireAt'       => $this->expireAt,
                    ]);
                }
                break;
            }

            // ต้องเหลือ headroom ก่อนเข้า TX
            if (! $guardHeadroom(0.20)) {
                $param    = $timeoutResponse($session);
                $timedOut = true;
                break;
            }

            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $guard) {
                    if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                    $newBalance = $member->{$this->balances} - $betAmount;
                    if ($newBalance < 0) {
                        return [
                            'ok'    => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            'log'   => null,
                            'member_balance' => (float) $member->{$this->balances},
                        ];
                    }

                    // (ทดสอบความช้าแบบ production ได้ที่นี่ถ้าต้องการ)
                    // if ($request->boolean('_db_slow')) { \DB::select('SELECT SLEEP(4)'); }

                    $member->decrement($this->balances, $betAmount);
                    $member->refresh();

                    if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

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
                        'con_3'          => $status,
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

                if (! $guard()) {
                    $param    = $timeoutResponse($session);
                    $timedOut = true;
                    break;
                }

                if (! $timedOut) {
                    $this->createGameLog($txResult['log']);
                    LogSeamless::log(
                        $session['productId'] ?? '',
                        $this->member->user_name,
                        $txn,
                        $oldBalance,
                        $txResult['member_balance']   // ใช้ค่าหลัง TX ที่เชื่อถือได้
                    );
                }

                $param = $txResult['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param    = $timeoutResponse($session);
                    $timedOut = true;
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log เสมอ
        return $finalize($param);
    }


    public function placeBets_b(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = (float) config('api.time_budget.placebets', 3.5);

        // ใช้เวลาเริ่มจาก PHP (รวม middleware แล้ว)
        $startedAt = (float) ($request->server('REQUEST_TIME_FLOAT') ?? microtime(true));

        $elapsed = static function () use ($startedAt): float {
            return microtime(true) - $startedAt;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        // ต้องเหลือ headroom อย่างน้อย 0.2s ก่อนเริ่มงานเสี่ยง (เข้า TX)
        $guardHeadroom = static function (float $need) use ($elapsed, $TIME_LIMIT): bool {
            return ($TIME_LIMIT - $elapsed()) >= $need;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20003, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param   = [];
        $timedOut = false; // กันเขียน log ราย txn หลัง timeout
        $txns = (array)($session['txns'] ?? []);
        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $oldBalance = $this->member->balance;
        $amount = collect($txns)->sum(fn($t) => (float)($t['betAmount'] ?? 0));


        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'betmain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);


        if (! $guard()) {
            return $timeoutResponse($session);
        }




        if (! $guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param; $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (! $guard()) {
                $param = $timeoutResponse($session);
                $timedOut = true;
                break;
            }

            $txnId      = $txn['id']       ?? null;
            $roundId    = $txn['roundId']  ?? null;
            $status     = $txn['status']   ?? null;
            $betAmount  = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // duplicate check
            $txnDup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->exists();

            if ($txnDup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // OPEN + เจอ WAITING เดิม: ตอบสำเร็จโดยไม่หัก
            if ($status === 'OPEN') {
                if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

                $waitingExists = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'WAITING')
                    ->where('con_1', $txnId)
                    ->where('con_2', $roundId)
                    ->exists();

                if ($waitingExists) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter'  => (float)$this->member->balance,
                        ];
                    if (! $timedOut) {
                        $this->createGameLog([
                            'input' => $txn,
                            'output' => $param,
                            'company' => $session['productId'] ?? '',
                            'game_user' => $this->member->user_name,
                            'method' => $status,
                            'response' => 'in',
                            'amount' => $betAmount,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => $status,
                            'con_4' => null,
                            'before_balance' => $oldBalance,
                            'after_balance' => $this->member->balance,
                            'date_create' => $this->now->toDateTimeString(),
                            'expireAt' => $this->expireAt,
                        ]);
                    }
                    break;
                }
            }

            // ไม่ต้องอัปเดตยอด → ตอบสำเร็จและ log ได้ (ถ้าไม่ timeout)
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter'  => (float)$this->member->balance,
                    ];
                if (! $timedOut) {
                    $this->createGameLog([
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $betAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);
                }
                break;
            }

            // ก่อนเข้า TX ต้องเหลือเวลาอย่างน้อย 0.2s
            if (! $guardHeadroom(0.20)) {
                $param = $timeoutResponse($session);
                $timedOut = true;
                break;
            }

            try {
                $txResult = DB::transaction(function () use ($session, $txn, $status, $txnId, $roundId, $betAmount, $oldBalance, $guard) {
                    if (! $guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (! $guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $newBalance = $member->{$this->balances} - $betAmount;
                    if ($newBalance < 0) {
                        return [
                            'ok'   => false,
                            'param'=> $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            'log'  => null,
                        ];
                    }

                    // (ถ้าจะทดสอบช้าแบบ production จริง ให้หน่วงตรงนี้)
                    // if ($request->boolean('_db_slow')) { \DB::select('SELECT SLEEP(4)'); }

                    $member->decrement($this->balances, $betAmount);
                    $member->refresh();

                    // เช็กอีกครั้ง "ก่อนออกจาก TX" → ถ้าเกินจะ rollback
                    if (! $guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter'  => (float)$member->{$this->balances},
                        ];

                    $log = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $betAmount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    return ['ok' => true, 'param' => $param, 'log' => $log];
                }, 1);

                if (! $txResult['ok']) {
                    $param = $txResult['param'];
                    break;
                }

                // ออกจาก TX แล้ว — ถ้าเวลาหมดตอนนี้ ให้ถือว่า timeout (ไม่เขียน log ราย txn)
                if (! $guard()) {
                    $param = $timeoutResponse($session);
                    $timedOut = true;
                    break;
                }

                if (! $timedOut) {
                    $this->createGameLog($txResult['log']);
                    LogSeamless::log(
                        $session['productId'] ?? '',
                        $this->member->user_name,
                        $txn,
                        $oldBalance,
                        $this->member->balance
                    );
                }

                $param = $txResult['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    $timedOut = true;
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function placeBets_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $txnDup = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->exists();

            if ($txnDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($txn['status'] === 'OPEN') {
                $waitingExists = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'WAITING')
                    ->where('con_1', $txn['id'])
                    ->where('con_2', $txn['roundId'])
                    ->exists();

                if ($waitingExists) {
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
                    ]);
                    break;
                }
            }

            $betAmount = $txn['betAmount'];
            $skipUpdate = $txn['skipBalanceUpdate'] ?? false;

            if (!$skipUpdate) {
                $newBalance = $this->member->balance - $betAmount;

                if ($newBalance < 0) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }

                $this->member->decrement($this->balances, $betAmount);
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
                'amount' => $betAmount,
                'con_1' => $txn['id'],
                'con_2' => $txn['roundId'],
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ]);

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
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
                            'con_3'           => 'OPEN',
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
                        'con_3'           => 'OPEN',
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
                            'status'          => null,
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
                        'status'          => null,
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
            LogSeamless::log(
                $session['productId'] ?? '',
                $this->member->user_name,
                $txn,
                $oldBalance,
                $settleResult['member_balance']
            );
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function settleBets_guard(Request $request)
    {
        // === Time budget (configurable) ===
        $TIME_LIMIT = (float) config('api.time_budget.settlebets', 3.5);

        // ใช้เวลาเริ่มจาก PHP (รวม middleware delay แล้ว)
        $startedAt = (float) ($request->server('REQUEST_TIME_FLOAT') ?? microtime(true));
        $elapsed = static function () use ($startedAt): float { return microtime(true) - $startedAt; };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool { return $elapsed() <= $TIME_LIMIT; };
        // ต้องเหลือ headroom อย่างน้อย X วินาที ก่อนเริ่มงานเสี่ยง (เข้า TX)
        $guardHeadroom = static function (float $need) use ($elapsed, $TIME_LIMIT): bool {
            return ($TIME_LIMIT - $elapsed()) >= $need;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param   = [];
        $timedOut = false;

//        Log::channel('gamelog')->debug('Start settlebet-----------', ['session' => $session]);

        if (! $guard()) {
            return $timeoutResponse($session);
        }

        if (! $this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns       = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;
        $amount     = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // main log
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

        if (! $guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param; $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (! $guard()) {
                $param = $timeoutResponse($session);
                $timedOut = true;
                break;
            }

            // flags/vars
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

            // 1) single state → สร้าง OPEN (หัก bet ก่อน) ถ้าไม่ skip
            if ($isSingleState) {
                if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

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

                    // ต้องเหลือเวลาอย่างน้อย 0.2s ก่อนเริ่ม TX
                    if (! $guardHeadroom(0.20)) {
                        $param = $timeoutResponse($session);
                        $timedOut = true;
                        break;
                    }

                    try {
                        $res = DB::transaction(function () use ($betAmt, $session, $txn, $txnId, $roundId, $oldBalance, $guard) {
                            if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                            if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                            $newBalance = $member->{$this->balances} - $betAmt;
                            if ($newBalance < 0) {
                                return [
                                    'ok'   => false,
                                    'param'=> $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                                ];
                            }

                            $member->decrement($this->balances, $betAmt);
                            $member->refresh();

                            // เช็กก่อนออก TX → ถ้าเกินจะ rollback
                            if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                            return [
                                'ok'  => true,
                                'bal' => (float)$member->{$this->balances},
                            ];
                        }, 1);

                        if (! $res['ok']) {
                            $param = $res['param'];
                            break;
                        }

                        if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

                        if (! $timedOut) {
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
                                'con_3'           => 'OPEN',
                                'con_4'           => null,
                                'before_balance'  => $oldBalance,
                                'after_balance'   => $this->member->balance,
                                'date_create'     => $this->now->toDateTimeString(),
                                'expireAt'        => $this->expireAt,
                            ]);
                        }
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                            $param = $timeoutResponse($session);
                            $timedOut = true;
                            break;
                        }
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                                'message' => $e->getMessage(),
                            ];
                        break;
                    }
                } else {
                    // ไม่หักยอด แต่ต้องการรอย OPEN
                    if (! $timedOut) {
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
                            'con_3'           => 'OPEN',
                            'con_4'           => null,
                            'before_balance'  => $oldBalance,
                            'after_balance'   => $this->member->balance,
                            'date_create'     => $this->now->toDateTimeString(),
                            'expireAt'        => $this->expireAt,
                        ]);
                    }
                }
            }

            // 2) ตรวจว่าเคย placeBets หรือยัง (ตามของเดิม)
            $relatedLogs = collect();
            $openLog     = null;

            if ($transactionType === 'BY_ROUND') {
                if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

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

            // 3) เติมเงิน (ถ้าต้องทำ) — ทำใน TX + lockForUpdate
            $settleResult = [
                'ok' => true,
                'param' => null,
                'logData' => null,
                'member_balance' => $this->member->balance,
            ];

            if (! $skipBalanceUpdate) {
                // ต้องเหลือเวลา 0.2s ก่อนเริ่ม TX
                if (! $guardHeadroom(0.20)) {
                    $param = $timeoutResponse($session);
                    $timedOut = true;
                    break;
                }

                try {
                    $settleResult = DB::transaction(function () use ($session, $txn, $status, $payout, $roundId, $txnId, $ismulti, $oldBalance, $guard) {
                        if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                        if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

                        $member->increment($this->balances, $payout);
                        $member->refresh();

                        // เช็กก่อนออก TX → ถ้าเกินจะ rollback
                        if (! $guard()) throw new \RuntimeException('TIMEOUT_ABORTED');

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
                            'status'          => null,
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
                } catch (\RuntimeException $e) {
                    if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                        $param = $timeoutResponse($session);
                        $timedOut = true;
                        break;
                    }
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
                        'status'          => null,
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

            if (! $guard()) { $param = $timeoutResponse($session); $timedOut = true; break; }

            // 4) เขียน log settle + ผูก con_4 (เฉพาะถ้ายังไม่ timeout)
            $settleId = null;
            if (! $timedOut) {
                $settleId = $this->createGameLog($settleResult['logData'])->id;
            }

            if (! $timedOut) {
                if ($transactionType === 'BY_ROUND') {
                    foreach ($relatedLogs as $rl) {
                        $rl->con_4 = ($status ?? 'SETTLE') . '_' . $settleId;
                        $rl->save();
                    }
                } elseif ($openLog) {
                    $openLog->con_4 = ($status ?? 'SETTLE') . '_' . $settleId;
                    $openLog->save();
                }
            }

            // LogSeamless (เฉพาะถ้าไม่ timeout)
            if (! $timedOut) {
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $settleResult['member_balance']
                );
            }

            $param = $settleResult['param'];
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function settleBets_s(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5; // ปรับได้
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        Log::channel('gamelog')->debug("Start settlebet-----------", ['session' => $session]);

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;
        $amount = collect($txns)->sum(fn($t) => (float)($t['payoutAmount'] ?? 0));

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'settlemain',
            'response' => 'in',
            'amount' => $amount,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            // flags/ตัวแปรจาก payload
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

            // 1) ถ้าเป็น single state: ตัดเงิน OPEN ก่อน settle
            if ($isSingleState) {
                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                if (!$skipBalanceUpdate) {
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
                        $res = DB::transaction(function () use ($betAmt, $session, $txn, $txnId, $roundId, $oldBalance, $guard) {
                            if (!$guard()) {
                                throw new \RuntimeException('TIMEOUT_ABORTED');
                            }

                            $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                            if (!$guard()) {
                                throw new \RuntimeException('TIMEOUT_ABORTED');
                            }

                            $newBalance = $member->{$this->balances} - $betAmt;
                            if ($newBalance < 0) {
                                return [
                                    'ok' => false,
                                    'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                                ];
                            }

                            $member->decrement($this->balances, $betAmt);
                            $member->refresh();

                            return [
                                'ok' => true,
                                'bal' => (float)$member->{$this->balances},
                            ];
                        }, 1);

                        if (!$res['ok']) {
                            $param = $res['param'];
                            break;
                        }

                        // เขียน log OPEN หลัง TX (ลดเวลาถือ lock)
                        if (!$guard()) {
                            $param = $timeoutResponse($session);
                            break;
                        }

                        $this->createGameLog([
                            'input' => $txn,
                            'output' => [],
                            'company' => $session['productId'] ?? '',
                            'game_user' => $this->member->user_name,
                            'method' => 'OPEN',
                            'response' => 'in',
                            'amount' => $betAmt,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => 'OPEN',
                            'con_4' => null,
                            'before_balance' => $oldBalance,
                            'after_balance' => $this->member->balance, // $this->member ควร refresh ภายนอกถ้าต้องการ
                            'date_create' => $this->now->toDateTimeString(),
                            'expireAt' => $this->expireAt,
                        ]);
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                            $param = $timeoutResponse($session);
                            break;
                        }
                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                                'message' => $e->getMessage(),
                            ];
                        break;
                    }
                } else {
                    // ไม่มีหักเงิน แต่อยากมีรอย OPEN ใน log ไว้
                    $this->createGameLog([
                        'input' => $txn,
                        'output' => [],
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => 'OPEN',
                        'response' => 'in',
                        'amount' => $betAmt,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => 'OPEN',
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ]);
                }
            }

            // 2) ตรวจว่าเคย placeBets หรือยัง ตาม transactionType
            $relatedLogs = collect(); // สำหรับ BY_ROUND
            $openLog = null;      // สำหรับ BY_TRANSACTION

            if ($transactionType === 'BY_ROUND') {
                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

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

                if (!$ismulti && !$skipBalanceUpdate) {
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
            } else {
                $openLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'OPEN')
                    ->where('con_1', $txnId)
                    ->latest('created_at')
                    ->first();

                if (!$openLog) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                if (!$skipBalanceUpdate) {
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

            // 3) เติมเงิน (ถ้ามี) — ทำใน TX + lockForUpdate
            $settleResult = [
                'ok' => true,
                'param' => null,
                'logData' => null,
                'member_balance' => $this->member->balance,
            ];

            if (!$skipBalanceUpdate) {
                try {
                    $settleResult = DB::transaction(function () use ($session, $txn, $status, $payout, $roundId, $txnId, $ismulti, $oldBalance, $guard) {
                        if (!$guard()) {
                            throw new \RuntimeException('TIMEOUT_ABORTED');
                        }

                        $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                        if (!$guard()) {
                            throw new \RuntimeException('TIMEOUT_ABORTED');
                        }

                        $member->increment($this->balances, $payout);
                        $member->refresh();

                        $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                                'balanceBefore' => (float)$oldBalance,
                                'balanceAfter' => (float)$member->{$this->balances},
                            ];

                        $logData = [
                            'input' => $txn,
                            'output' => $param,
                            'company' => $session['productId'] ?? '',
                            'game_user' => $this->member->user_name,
                            'method' => $status,
                            'response' => 'in',
                            'amount' => $payout,
                            'con_1' => $txnId,
                            'con_2' => $roundId,
                            'con_3' => $ismulti,
                            'con_4' => null,
                            'status' => null,
                            'before_balance' => $oldBalance,
                            'after_balance' => $member->{$this->balances},
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
                } catch (\RuntimeException $e) {
                    if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                        $param = $timeoutResponse($session);
                        break;
                    }
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                            'message' => $e->getMessage(),
                        ];
                    break;
                }
            } else {
                // ไม่อัปเดตยอด แต่ยังตอบสำเร็จและเขียน log
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];

                $settleResult = [
                    'ok' => true,
                    'param' => $param,
                    'logData' => [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $ismulti,
                        'con_4' => null,
                        'status' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $this->member->balance,
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ],
                    'member_balance' => $this->member->balance,
                ];
            }

            if (!$settleResult['ok']) {
                $param = $settleResult['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                break;
            }

            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            // เขียน log settle
            $settleId = $this->createGameLog($settleResult['logData'])->id;
            $param = $settleResult['param'];

            // 4) อัปเดต con_4 ของ log ที่เกี่ยวข้อง
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
            LogSeamless::log(
                $session['productId'] ?? '',
                $this->member->user_name,
                $txn,
                $oldBalance,
                $settleResult['member_balance']
            );
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function settleBets_(Request $request)
    {

        $session = $request->all();
        $param = [];

        Log::channel('gamelog')->debug("Start settlebet-----------", ['session' => $session]);

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

        $mainLog = $this->createGameLog($log);

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
                    $existingBet = GameLogProxy::where('company', $session['productId'])
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', 'OPEN')
                        ->where('con_1', $txn['id'])
                        ->where('con_2', $txn['roundId'])
                        ->first();

                    if ($existingBet) {
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        break;
                    }

                    $newBalance = $this->member->balance - $txn['betAmount'];
                    if ($newBalance < 0) {
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                        break;
                    }
                    $this->member->decrement($this->balances, $txn['betAmount']);
                }

                $this->createGameLog([
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
                ]);
            }

            // 2. เช็ค log ว่าเคย placeBets หรือยัง
            if ($transactionType === 'BY_ROUND') {
                $logs = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('con_2', $txn['roundId'])
                    ->whereNull('con_4')
                    ->get();


                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                if (!$ismulti && !$skipBalanceUpdate) {
                    $dupLog = GameLogProxy::where('company', $session['productId'])
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', $txn['status'])
                        ->where('con_2', $txn['roundId'])
                        ->whereNull('con_4')
                        ->latest('created_at')
                        ->first();

                    if ($dupLog && $dupLog['con_3'] === false) {
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        break;
                    }
                }
            } else {
                $log = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'OPEN')
                    ->where('con_1', $txn['id'])
                    ->latest('created_at')
                    ->first();

                if (!$log) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                if (!$skipBalanceUpdate) {
                    $dupSettle = GameLogProxy::where('company', $session['productId'])
                        ->where('response', 'in')
                        ->where('game_user', $this->member->user_name)
                        ->where('method', $txn['status'])
                        ->where('con_1', $txn['id'])
                        ->whereNull('con_4')
                        ->exists();

                    if ($dupSettle) {
                        $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                        break;
                    }
                }
            }

            // 3. เติมเงิน
            if (!$skipBalanceUpdate) {
                $this->member->increment($this->balances, $txn['payoutAmount']);
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

            $settleId = $this->createGameLog($logData)->id;

            // 4. อัปเดต con_4 ของ log ที่เกี่ยวข้อง
            if ($transactionType === 'BY_ROUND') {
                foreach ($logs as $log) {
                    $log->con_4 = $txn['status'] . '_' . $settleId;
                    $log->save();
                }
            } elseif (isset($log)) {
                $log->con_4 = $txn['status'] . '_' . $settleId;
                $log->save();
            }

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function unsettleBets(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        $existing = GameLogProxy::where('company', $this->game)
            ->where('response', 'in')
            ->where('game_user', $this->member->user_name)
            ->where('method', 'unsettle')
            ->where('con_1', $session['id'])
            ->where('con_2', $session['productId'])
            ->whereNull('con_3')
            ->whereNull('con_4')
            ->latest('created_at')->first();

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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $logDup = GameLogProxy::where('company', $this->game)
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'unsettlesub')
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->whereNull('con_4')
                ->latest('created_at')->first();

            if ($logDup) {
                return $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
            }

            if ($txn['betAmount'] > 0) {
                $this->member->decrement($this->balances, $txn['betAmount']);
                $method = 'betsub';
                $amount = $txn['betAmount'];
            } else {
                $settledLog = GameLogProxy::where('company', $this->game)
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'paysub')
                    ->where('con_1', $txn['id'])
                    ->where('con_2', $txn['roundId'])
                    ->where('con_3', $txn['status'])
                    ->whereNull('con_4')
                    ->latest('created_at')->first();

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
                $settledLog->con_4 = 'unsettle_' . $logId;
                $settledLog->save();

                GameLogProxy::where('con_4', 'settle_' . $settledLog->_id)
                    ->update(['con_4' => null]);
            }

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txResult['after_balance']
                );

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

    public function adjustBets_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'adjustbetmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $newBet = (float)($txn['betAmount'] ?? 0);

            // หา log เดิมของรายการนี้ (ตามโค้ดเดิม)
            $origLog = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->latest('created_at')
                ->first();

            if (!$origLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                break;
            }

            $origBet = (float)$origLog->amount;

            // คำนวณผลต่างที่ต้องปรับ
            // ถ้า newBet > origBet -> ต้อง "ตัดเพิ่ม" diff
            // ถ้า newBet < origBet -> ต้อง "คืนเงิน" diff
            $diff = $newBet - $origBet;

            try {
                $txResult = DB::transaction(function () use ($diff, $session, $txn, $status, $txnId, $roundId, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // ล็อกแถว member
                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // ปรับยอดตาม diff
                    if ($diff > 0) {
                        // ต้องตัดเพิ่ม diff: กันติดลบ
                        $newBal = $member->{$this->balances} - $diff;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                                'log' => null,
                            ];
                        }
                        $member->decrement($this->balances, $diff);
                    } elseif ($diff < 0) {
                        // คืนเงิน (-diff)
                        $member->increment($this->balances, abs($diff));
                    }
                    $member->refresh();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // response ณ หลังปรับยอด
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log adjust ใหม่ (อย่าเขียนใน TX เพื่อให้ล็อกสั้น)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => (float)($txn['betAmount'] ?? 0), // ยอดใหม่
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log adjust + เชื่อม con_4 กับ log เดิม
                $adjustId = $this->createGameLog($txResult['log'])->id;

                $origLog->con_4 = 'ADJUSTBET_' . $adjustId;
                $origLog->save();

                // LogSeamless (นอก TX)
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txResult['after_balance']
                );

                $param = $txResult['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function adjustBets_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $log = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->latest('created_at')
                ->first();

            if (!$log) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            $testBalance = ($this->member->balance + $log->amount) - $txn['betAmount'];
            if ($testBalance < 0) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                break;
            }

            $this->member->increment($this->balances, $log->amount);
            $this->member->decrement($this->balances, $txn['betAmount']);

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
            ])->id;

            $log->con_4 = 'ADJUSTBET_' . $logId;
            $log->save();

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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


    public function cancelBets_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];
        $isArray = false;

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'cancelmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null; // เช่น CANCELLED / REJECT
            $txnType = $txn['transactionType'] ?? 'BY_TRANSACTION';
            $reqAmount = (float)($txn['betAmount'] ?? 0);
            $logMethod = ($status === 'REJECT') ? 'WAITING' : 'OPEN';

            // กันซ้ำ: เคย cancel รายการนี้แล้วหรือยัง (ตามโค้ดเดิม)
            $exists = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // ดึง logs ที่เป็นต้นทางของเงินเดิมพันจะถูกยกเลิก
            if ($txnType === 'BY_ROUND') {
                $logs = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $logMethod)   // WAITING หรือ OPEN
                    ->where('con_2', $roundId)
                    ->get();

                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }

                $baseAmount = (float)$logs->sum('amount'); // ยอดที่เคยหักรวม
                $isArray = true;
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

                $baseAmount = (float)$logs[0]->amount; // ยอดที่เคยหักของรายการนี้
                $isArray = false;
            }

            // ทำยอดเงินภายใต้ TX + lockForUpdate เฉพาะ member เดียว
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $reqAmount, $baseAmount, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // ตรรกะตามโค้ดเดิม:
                    // - ถ้า reqAmount > baseAmount : decrement(baseAmount) แล้ว increment(reqAmount)
                    // - ถ้า reqAmount <= baseAmount : increment(reqAmount)
                    if ($reqAmount > $baseAmount) {
                        // ต้องลดก่อน baseAmount → กันเครดิตติดลบ
                        $newBal = $member->{$this->balances} - $baseAmount;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
                                'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                            ];
                        }
                        $member->decrement($this->balances, $baseAmount);
                        $member->increment($this->balances, $reqAmount);
                    } else {
                        // คืนเท่าที่ขอ (หรือน้อยกว่าหรือเท่ากับที่เคยหัก)
                        $member->increment($this->balances, $reqAmount);
                    }

                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log ของการ cancel (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $reqAmount,
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log cancel
                $logId = $this->createGameLog($txRes['logData'])->id;

                // อัปเดต con_4 ของ log ต้นทาง
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function cancelBets_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $exists = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $logMethod = ($txn['status'] === 'REJECT') ? 'WAITING' : 'OPEN';

            if ($txn['transactionType'] === 'BY_ROUND') {
                $logs = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $logMethod)
                    ->where('con_2', $txn['roundId'])
                    ->get();

                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                $betAmount = $logs->sum('amount');
                $isArray = true;

            } else {
                $logs = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $logMethod)
                    ->where('con_1', $txn['id'])
                    ->latest('created_at')->limit(1)->get();

                if ($logs->isEmpty()) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }

                $betAmount = $logs[0]->amount;
            }

            if ($txn['betAmount'] > $betAmount) {
                $this->member->decrement($this->balances, $betAmount);
            }

            $this->member->increment($this->balances, $txn['betAmount']);

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
            ])->id;

            if ($isArray) {
                foreach ($logs as $log) {
                    $log->con_4 = $txn['status'] . '_' . $logId;
                    $log->save();
                }
            } else {
                $logs[0]->con_4 = $txn['status'] . '_' . $logId;
                $logs[0]->save();
            }

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                    ->where('con_3', $status)
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
                    'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function rollback_guard(Request $request)
    {
        $session = $request->all();
        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

        // main log (เหมือนเดิม)
        $mainLog = $this->createGameLog([
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
        ]);

        foreach ((array)($session['txns'] ?? []) as $txn) {
            $status = $txn['status'] ?? 'ROLLBACK';
            $txnType = $txn['transactionType'] ?? 'BY_TRANSACTION';
            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;

            // === เงื่อนไขตาม "ของเดิม": เฉพาะ BY_ROUND เท่านั้นที่เช็กซ้ำ ROLLBACK ก่อน ===
            if ($txnType === 'BY_ROUND') {
                $isDup = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $status)              // ROLLBACK
                    ->where('con_1', $txnId)
                    ->where('con_2', $roundId)
                    ->where('con_3', $status)
                    ->whereNull('con_4')
                    ->exists();

                if ($isDup) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                    break;
                }
            }

            // === หา baseLog ตามเดิม ===
            if ($txnType === 'BY_ROUND') {
                $baseLog = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (!$baseLog) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }
            } else { // BY_TRANSACTION
                $baseLog = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_1', $txnId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (!$baseLog) {
                    // ของเดิม: BY_TRANSACTION ไม่พบ baseLog => 20002
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                    break;
                }
            }

            // === ใช้จำนวนเงินจาก payload ตามของเดิม ===
            $rollbackAmount = ($baseLog->method === 'SETTLED')
                ? (float)($txn['payoutAmount'] ?? 0)
                : (float)($txn['betAmount'] ?? 0);

            try {
                // รวมทุกอย่างสำคัญไว้ใน TX เดียว (ตัดยอด + สร้าง log + ผูก con_4 + เคลียร์ WAITING/OPEN)
                $txResult = DB::transaction(function () use ($session, $txn, $status, $rollbackAmount, $baseLog, $oldBalance) {
                    // ล็อกแถวสมาชิก
                    $member = MemberProxy::where('code', $this->member->code)
                        ->lockForUpdate()
                        ->first();

                    // ตัดคืน (ตามของเดิม: ไม่เช็กติดลบ)
                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                        $member->refresh();
                    }

                    // response แบบเดิม
                    $param = $this->responseData(
                            $session['id'],
                            $session['username'],
                            $session['productId'],
                            0,
                            $member->{$this->balances}
                        ) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // สร้าง rollback log (ใน TX เพื่อรู้ $logId)
                    $logId = $this->createGameLog([
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'],
                        'game_user' => $this->member->user_name,
                        'method' => $status,                // ROLLBACK
                        'response' => 'in',
                        'amount' => $rollbackAmount,
                        'con_1' => $txn['id'],
                        'con_2' => $txn['roundId'],
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ])->id;

                    // ผูก baseLog -> con_4 ชี้ไปยัง rollback log
                    GameLogProxy::where('_id', new ObjectId($baseLog->_id))
                        ->update([
                            'con_4' => $status . '_' . $logId,
                        ]);

                    // เคลียร์ WAITING/OPEN ที่ชี้ไป baseLog เดิม (เหมือนของเดิม)
                    GameLogProxy::where('con_4', $baseLog->method . '_' . $baseLog->id)
                        ->whereIn('method', ['WAITING', 'OPEN'])
                        ->where('company', $session['productId'])
                        ->where('game_user', $this->member->user_name)
                        ->update(['con_4' => null]);

                    return [
                        'param' => $param,
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                // เขียน seamless log (นอก TX)
                LogSeamless::log(
                    $session['productId'],
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txResult['member_balance']
                );

                $param = $txResult['param'];
            } catch (\Throwable $e) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function rollback__(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'rollbackmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null; // e.g. ROLLBACK
            $txnType = $txn['transactionType'] ?? 'BY_TRANSACTION';

            // กันซ้ำตาม logic เดิม: ถ้ามี log ของ status นี้อยู่แล้ว (ยังไม่ถูกปิด con_4) ให้ไม่ทำซ้ำ
            $dupExists = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($dupExists) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // หา log ที่จะถูก rollback: ถ้า BY_ROUND ดูรอบ, ถ้า BY_TRANSACTION ดูตาม id
            if ($txnType === 'BY_ROUND') {
                $baseLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_2', $roundId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();
                if (!$baseLog) {
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                    break;
                }
            } else {
                $baseLog = GameLogProxy::where('company', $session['productId'] ?? '')
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_1', $txnId)
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();
                if (!$baseLog) {
                    // เดิมคุณคืน 20002 ตรงนี้ ผมคงไว้ตามเดิม
                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                    break;
                }
            }

            // ยอดที่จะตัดคืนออกจากผู้ใช้:
            // - ถ้า baseLog เป็น SETTLED → เคยเพิ่ม payout มาก่อน → rollback = payoutAmount
            // - ถ้า baseLog เป็น REFUND  → เคยคืน bet มาก่อน → rollback = betAmount
            $rollbackAmount = ($baseLog->method === 'SETTLED')
                ? (float)($txn['payoutAmount'] ?? 0)
                : (float)($txn['betAmount'] ?? 0);

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $rollbackAmount, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // กันติดลบ
                    $newBal = $member->{$this->balances} - $rollbackAmount;
                    if ($newBal < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                        ];
                    }

                    // ตัดคืน
                    if ($rollbackAmount > 0) {
                        $member->decrement($this->balances, $rollbackAmount);
                    }
                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log ใหม่ (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $rollbackAmount,
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log rollback
                $logId = $this->createGameLog($txRes['logData'])->id;

                // ผูก con_4 ให้ baseLog ที่ถูก rollback
                $baseLog->con_4 = ($status ?? 'ROLLBACK') . '_' . $logId;
                $baseLog->save();

                // เคลียร์ WAITING/OPEN ที่ผูกกับ baseLog เดิม (ตามโค้ดเดิม)
                GameLogProxy::where('con_4', $baseLog->method . '_' . $baseLog->id)
                    ->whereIn('method', ['WAITING', 'OPEN'])
                    ->where('company', $session['productId'] ?? '')
                    ->where('game_user', $this->member->user_name)
                    ->update(['con_4' => null]);

                // LogSeamless (นอก TX)
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function rollback_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            if ($txn['transactionType'] === 'BY_ROUND') {
                $isDup = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', $txn['status'])
                    ->where('con_1', $txn['id'])
                    ->where('con_2', $txn['roundId'])
                    ->where('con_3', $txn['status'])
                    ->whereNull('con_4')
                    ->exists();

                if ($isDup) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                    break;
                }

                $log = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_2', $txn['roundId'])
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (!$log) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                    break;
                }
            } else {
                $log = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->whereIn('method', ['REFUND', 'SETTLED'])
                    ->where('con_1', $txn['id'])
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

                if (!$log) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                    break;
                }
            }

            $rollbackAmount = $log->method === 'SETTLED' ? $txn['payoutAmount'] : $txn['betAmount'];

            $this->member->decrement($this->balances, $rollbackAmount);

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
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ])->id;

            $log->con_4 = $txn['status'] . '_' . $logId;
            $log->save();

            GameLogProxy::where('con_4', $log->method . '_' . $log->id)
                ->whereIn('method', ['WAITING', 'OPEN'])
                ->where('company', $session['productId'])
                ->where('game_user', $this->member->user_name)
                ->update(['con_4' => null]);

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function winRewards(Request $request)
    {
        $session = $request->all();
        \Log::channel('gamelog')->debug("Start Winreward-----------", ['session' => $session]);

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function winRewards_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        \Log::channel('gamelog')->debug("Start Winreward-----------", ['session' => $session]);

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        $param = [];

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'winrewardmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null;
            $payout = (float)($txn['payoutAmount'] ?? 0);

            // กันซ้ำ (ตามตรรกะเดิม)
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // เติมยอดใน TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $payout, $txnId, $roundId, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    if ($payout > 0) {
                        $member->increment($this->balances, $payout);
                        $member->refresh();
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $payout,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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
                    // ปกติไม่น่าเข้ามา เพราะไม่มี error code อื่นในสาขานี้
                    $param = $txRes['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                    break;
                }

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log นอก TX
                $this->createGameLog($txRes['logData']);

                // LogSeamless นอก TX
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function winRewards_(Request $request)
    {

        $session = $request->all();

        Log::channel('gamelog')->debug("Start Winreward-----------", ['session' => $session]);
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $logDup = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->whereNull('con_4')
                ->exists();

            if ($logDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $payout = $txn['payoutAmount'] ?? 0;

            $this->member->increment($this->balances, $payout);

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
                'con_3' => $txn['status'],
                'con_4' => null,
                'before_balance' => $oldBalance,
                'after_balance' => $this->member->balance,
                'date_create' => $this->now->toDateTimeString(),
                'expireAt' => $this->expireAt,
            ]);

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function voidSettled_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'voidsettledmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null; // e.g. VOID_SETTLED
            $type = $txn['transactionType'] ?? 'BY_TRANSACTION';

            // กันซ้ำ
            $duplicate = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
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

            if (!$settledLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                break;
            }

            $betAmount = (float)($txn['betAmount'] ?? 0); // จะคืน (เพิ่ม)
            $payout = (float)($txn['payoutAmount'] ?? 0); // จะตัดออก (ลด)
            $netDelta = $betAmount - $payout;               // + เพิ่ม, - ลด

            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $betAmount, $payout, $netDelta, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // ตรวจล่วงหน้าแบบ net: ห้ามติดลบหลังปรับ
                    $candidate = (float)$member->{$this->balances} + $netDelta;
                    if ($candidate < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                        ];
                    }

                    // ปรับแบบ net
                    if ($netDelta > 0) {
                        $member->increment($this->balances, $netDelta);
                    } elseif ($netDelta < 0) {
                        $member->decrement($this->balances, abs($netDelta));
                    }
                    $member->refresh();

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียม log (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $netDelta, // บันทึกเป็น net เพื่ออ่านย้อนหลังง่าย
                        'con_1' => $txn['id'] ?? null,
                        'con_2' => $txn['roundId'] ?? null,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log + ปิดปลายทาง SETTLED
                $logId = $this->createGameLog($txRes['logData'])->id;
                $settledLog->con_4 = ($status ?? 'VOID_SETTLED') . '_' . $logId;
                $settledLog->save();

                // LogSeamless (นอก TX)
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function voidSettled_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $duplicate = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->whereNull('con_4')
                ->exists();

            if ($duplicate) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($txn['transactionType'] === 'BY_ROUND') {
                $settledLog = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'SETTLED')
                    ->where('con_2', $txn['roundId'])
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();
            } else {

                $settledLog = GameLogProxy::where('company', $session['productId'])
                    ->where('response', 'in')
                    ->where('game_user', $this->member->user_name)
                    ->where('method', 'SETTLED')
                    ->where('con_1', $txn['id'])
                    ->whereNull('con_4')
                    ->latest('created_at')
                    ->first();

            }

            if (!$settledLog) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            $this->member->increment($this->balances, $txn['betAmount']);

            $payout = $txn['payoutAmount'];

            if ($this->member->balance < $payout) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                break;
            }

            $this->member->decrement($this->balances, $payout);

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
            ])->id;

            $settledLog->con_4 = $txn['status'] . '_' . $logId;
            $settledLog->save();

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                    'con_3'           => $status,
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function placeTips_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'placetipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null; // ควรเป็น 'TIPS' ตามระบบเดิม
            $amount = (float)($txn['betAmount'] ?? 0);
            $skipUpdate = (bool)($txn['skipBalanceUpdate'] ?? false);

            // กันซ้ำตามเดิม
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // ถ้าไม่อัปเดตยอด ให้ตอบสำเร็จและล็อกไว้เฉย ๆ
            if ($skipUpdate) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $this->member->balance) + [
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$this->member->balance,
                    ];

                $this->createGameLog([
                    'input' => $txn,
                    'output' => $param,
                    'company' => $session['productId'] ?? '',
                    'game_user' => $this->member->user_name,
                    'method' => $status,
                    'response' => 'in',
                    'amount' => $amount,
                    'con_1' => $txnId,
                    'con_2' => $roundId,
                    'con_3' => $status,
                    'con_4' => null,
                    'before_balance' => $oldBalance,
                    'after_balance' => $this->member->balance,
                    'date_create' => $this->now->toDateTimeString(),
                    'expireAt' => $this->expireAt,
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

            // อัปเดตยอดแบบปลอดภัยใน TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    // กันติดลบ
                    $newBal = $member->{$this->balances} - $amount;
                    if ($newBal < 0) {
                        return [
                            'ok' => false,
                            'param' => $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10002, $member->{$this->balances}),
                        ];
                    }

                    // หักทิป
                    if ($amount > 0) {
                        $member->decrement($this->balances, $amount);
                        $member->refresh();
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    // เตรียมล็อก (เขียนนอก TX)
                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log นอก TX
                $this->createGameLog($txRes['logData']);

                // LogSeamless นอก TX
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function placeTips_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $tipDup = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->exists();

            if ($tipDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $amount = $txn['betAmount'] ?? 0;
            $skipUpdate = $txn['skipBalanceUpdate'] ?? false;

            if (!$skipUpdate) {
                $newBalance = $this->member->balance - $amount;

                if ($newBalance < 0) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }

                $this->member->decrement($this->balances, $amount);
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
            ]);

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function cancelTips_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'canceltipmain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $txn) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $txnId = $txn['id'] ?? null;
            $roundId = $txn['roundId'] ?? null;
            $status = $txn['status'] ?? null; // สถานะยกเลิกทิป
            $amount = (float)($txn['betAmount'] ?? 0);

            // กันซ้ำ: เคย cancel สำหรับ txn นี้แล้วหรือยัง
            $exists = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $status)
                ->where('con_1', $txnId)
                ->where('con_2', $roundId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
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

            if (!$tipLog) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20001, $this->member->balance);
                break;
            }

            // คืนยอดทิปใน TX + lockForUpdate (ไม่ต้องเช็กติดลบเพราะเป็น "เพิ่ม" เงิน)
            try {
                $txRes = DB::transaction(function () use ($session, $txn, $status, $amount, $txnId, $roundId, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    if ($amount > 0) {
                        $member->increment($this->balances, $amount);
                        $member->refresh();
                    }

                    $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 0, $member->{$this->balances}) + [
                            'balanceBefore' => (float)$oldBalance,
                            'balanceAfter' => (float)$member->{$this->balances},
                        ];

                    $logData = [
                        'input' => $txn,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'method' => $status,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $txnId,
                        'con_2' => $roundId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
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
                    $param = $txRes['param'] ?? $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10998, $this->member->balance);
                    break;
                }

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log ยกเลิกทิป
                $logId = $this->createGameLog($txRes['logData'])->id;

                // ปิดปลายทาง log ต้นทาง TIPS
                $tipLog->con_4 = ($status ?? 'CANCEL_TIP') . '_' . $logId;
                $tipLog->save();

                // LogSeamless (นอก TX)
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $txn,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }

    public function cancelTips_(Request $request)
    {
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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $txn) {
            $exists = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', $txn['status'])
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->where('con_3', $txn['status'])
                ->whereNull('con_4')
                ->exists();

            if ($exists) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            $dup = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'TIPS')
                ->where('con_1', $txn['id'])
                ->where('con_2', $txn['roundId'])
                ->whereNull('con_4')
                ->doesntExist();

            if ($dup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20001, $this->member->balance);
                break;
            }

            $newBalance = $this->member->balance - $txn['betAmount'];

            if ($newBalance < 0) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                break;
            }

            $this->member->increment($this->balances, $txn['betAmount']);

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
            ]);

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $txn,
                $oldBalance,
                $this->member->balance
            );
        }

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
                ->where('con_3', $status)
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
                        'con_3'           => $status,
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
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $item,
                    $oldBalance,
                    $txRes['member_balance']
                );

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

    public function adjustBalance_guard(Request $request)
    {
        // === Time budget (3.5s) ===
        $TIME_LIMIT = 3.5;
        $tStartNs = hrtime(true);
        $elapsed = static function () use ($tStartNs): float {
            return (hrtime(true) - $tStartNs) / 1_000_000_000;
        };
        $guard = static function () use ($elapsed, $TIME_LIMIT): bool {
            return $elapsed() <= $TIME_LIMIT;
        };
        $timeoutResponse = function (array $session) use ($elapsed) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member?->balance ?? 0) + [
                    'elapsed' => round($elapsed(), 3),
                    'message' => 'Processing time exceeded limit',
                ];
        };

        $session = $request->all();
        $param = [];

        if (!$guard()) {
            return $timeoutResponse($session);
        }

        if (!$this->member) {
            return $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 10001);
        }

        $txns = (array)($session['txns'] ?? []);
        $oldBalance = $this->member->balance;

        // main log เริ่มต้น
        $mainLog = $this->createGameLog([
            'input' => $session,
            'output' => $param,
            'company' => $session['productId'] ?? '',
            'game_user' => $this->member->user_name,
            'method' => 'adjustbalancemain',
            'response' => 'in',
            'amount' => 0,
            'con_1' => $session['id'] ?? null,
            'con_2' => $session['productId'] ?? null,
            'con_3' => null,
            'con_4' => null,
            'before_balance' => $oldBalance,
            'after_balance' => $this->member->balance,
            'date_create' => $this->now->toDateTimeString(),
            'expireAt' => $this->expireAt,
        ]);

        if (!$guard()) {
            $param = $timeoutResponse($session);
            $mainLog->output = $param;
            $mainLog->save();
            return $param;
        }

        foreach ($txns as $item) {
            if (!$guard()) {
                $param = $timeoutResponse($session);
                break;
            }

            $refId = $item['refId'] ?? null;
            $status = $item['status'] ?? null;   // 'DEBIT' | 'CREDIT'
            $amount = (float)($item['amount'] ?? 0);

            // กันซ้ำ: อิงตามโค้ดเดิม (ADJUSTBALANCE + con_1/con_2 = refId, con_3 = status)
            $dup = GameLogProxy::where('company', $session['productId'] ?? '')
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'ADJUSTBALANCE')
                ->where('con_1', $refId)
                ->where('con_2', $refId)
                ->where('con_3', $status)
                ->whereNull('con_4')
                ->exists();

            if ($dup) {
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 20002, $this->member->balance);
                break;
            }

            // ปรับยอดใน TX + lockForUpdate
            try {
                $txRes = DB::transaction(function () use ($session, $item, $status, $amount, $refId, $oldBalance, $guard) {
                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    $member = MemberProxy::where('code', $this->member->code)->lockForUpdate()->first();

                    if (!$guard()) {
                        throw new \RuntimeException('TIMEOUT_ABORTED');
                    }

                    if ($status === 'DEBIT') {
                        // กันเครดิตติดลบ
                        $newBal = $member->{$this->balances} - $amount;
                        if ($newBal < 0) {
                            return [
                                'ok' => false,
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

                    $param = [
                        'id' => $session['id'] ?? null,
                        'statusCode' => 0,
                        'currency' => 'THB',
                        'productId' => $session['productId'] ?? '',
                        'username' => $this->member->user_name,
                        'balanceBefore' => (float)$oldBalance,
                        'balanceAfter' => (float)$member->{$this->balances},
                        'timestampMillis' => $this->now->getTimestampMs(),
                    ];

                    // เตรียม log สองรายการเหมือนโค้ดเดิม: ADJUSTBALANCE และ OPEN
                    $baseLog = [
                        'input' => $item,
                        'output' => $param,
                        'company' => $session['productId'] ?? '',
                        'game_user' => $this->member->user_name,
                        'response' => 'in',
                        'amount' => $amount,
                        'con_1' => $refId,
                        'con_2' => $refId,
                        'con_3' => $status,
                        'con_4' => null,
                        'before_balance' => $oldBalance,
                        'after_balance' => $member->{$this->balances},
                        'date_create' => $this->now->toDateTimeString(),
                        'expireAt' => $this->expireAt,
                    ];

                    $logsToCreate = [
                        array_merge($baseLog, ['method' => 'ADJUSTBALANCE']),
                        array_merge($baseLog, ['method' => 'OPEN']),
                    ];

                    return [
                        'ok' => true,
                        'param' => $param,
                        'logs' => $logsToCreate, // เขียนนอก TX
                        'member_balance' => (float)$member->{$this->balances},
                    ];
                }, 1);

                if (!$txRes['ok']) {
                    $param = $txRes['param'];
                    break;
                }

                if (!$guard()) {
                    $param = $timeoutResponse($session);
                    break;
                }

                // เขียน log นอก TX ให้ไว
                foreach ($txRes['logs'] as $lg) {
                    GameLogProxy::create($lg);
                }

                // LogSeamless นอก TX
                LogSeamless::log(
                    $session['productId'] ?? '',
                    $this->member->user_name,
                    $item,
                    $oldBalance,
                    $txRes['member_balance']
                );

                $param = $txRes['param'];
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'TIMEOUT_ABORTED') {
                    $param = $timeoutResponse($session);
                    break;
                }
                $param = $this->responseData($session['id'] ?? null, $session['username'] ?? '', $session['productId'] ?? '', 50001, $this->member->balance) + [
                        'message' => $e->getMessage(),
                    ];
                break;
            }
        }

        // ปิด main log
        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }


    public function adjustBalance_(Request $request)
    {

        $param = [];
        $amount = 0;
        $session = $request->all();

        if (!$this->member) {
            return $this->responseData($session['id'], $session['username'], $session['productId'], 10001);
        }

        $oldBalance = $this->member->balance;

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

        $mainLog = $this->createGameLog($log);

        foreach ($session['txns'] as $item) {
            $checkDup = GameLogProxy::where('company', $session['productId'])
                ->where('response', 'in')
                ->where('game_user', $this->member->user_name)
                ->where('method', 'ADJUSTBALANCE')
                ->where('con_1', $item['refId'])
                ->where('con_2', $item['refId'])
                ->where('con_3', $item['status'])
                ->whereNull('con_4')
                ->exists();

            if ($checkDup) {
                $param = $this->responseData($session['id'], $session['username'], $session['productId'], 20002, $this->member->balance);
                break;
            }

            if ($item['status'] === 'DEBIT') {
                $balance = $this->member->balance - $item['amount'];
                if ($balance < 0) {
                    $param = $this->responseData($session['id'], $session['username'], $session['productId'], 10002, $this->member->balance);
                    break;
                }
                $this->member->decrement($this->balances, $item['amount']);
            } else {
                $this->member->increment($this->balances, $item['amount']);
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

            foreach (['ADJUSTBALANCE', 'OPEN'] as $method) {
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
                GameLogProxy::create($session_in);
            }

            LogSeamless::log(
                $session['productId'],
                $this->member->user_name,
                $item,
                $oldBalance,
                $this->member->balance
            );
        }

        $mainLog->output = $param;
        $mainLog->save();

        return $param;
    }
}
