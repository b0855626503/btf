<?php

namespace Gametech\API\Traits;

use App\Services\GameLogRedisService;
use Gametech\API\Models\GameData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

trait LogSeamless
{
    /**
     * @param mixed $timestamp เวลาที่ต้องการใช้เรียงลำดับ (รับได้ทั้ง ms, sec, ISO string, "Y-m-d H:i:s")
     */
    public static function log($product, $username, $item, $beforebalance, $afterbalance, $timestamp = null, $update = true): void
    {
        try {
            $ttlDays = (int)env('REDIS_MONGO_TTL_DAYS', 3);
            $async   = filter_var(env('LOGSEAMLESS_ASYNC', true), FILTER_VALIDATE_BOOLEAN);
            $legacy  = filter_var(env('LOGSEAMLESS_LEGACY_MONGO', false), FILTER_VALIDATE_BOOLEAN);

            $stake          = (float) Arr::get($item, 'betAmount', 0);
            $payout         = (float) Arr::get($item, 'payoutAmount', 0);
            $isSingleState  = (bool)  Arr::get($item, 'isSingleState', false);
            $skipBalanceUpd = (bool)  Arr::get($item, 'skipBalanceUpdate', false);
            $transactionType = strtoupper((string) Arr::get($item, 'transactionType', 'BY_TRANSACTION'));
            $productId      = (string) $product;

            // normalize ชื่อเก่า
            if ($transactionType === 'BY_BET') {
                $transactionType = 'BY_TRANSACTION';
            }

            $betId     = (string) (Arr::get($item, 'id', '') ?: Arr::get($item, 'refId', ''));
            $roundId   = (string) Arr::get($item, 'roundId', Arr::get($item, 'txns.0.roundId', ''));
            $gameName  = (string) Arr::get($item, 'playInfo', '');
            $betStatus = (string) Arr::get($item, 'status', 'UNKNOWN');

            // map amount แบบ single-field + status
            if (isset($item['amount'], $item['status'])) {
                if (strtoupper((string) $item['status']) === 'DEBIT') {
                    $stake = (float) $item['amount'];
                } else {
                    $payout = (float) $item['amount'];
                }
                $betId     = (string) (Arr::get($item, 'refId', '') ?: $betId);
                $roundId   = $roundId ?: $betId;
                $betStatus = $item['status'];
            }

            // ===== เวลาเรียงลำดับ: ใช้ $timestamp เป็นหลัก → fallback pickMillis(item) → now
            $tsMs = self::normalizeMillis($timestamp);
            if ($tsMs === null) {
                $tsMs = self::pickMillis($item);
            }
            if ($tsMs === null) {
                $tsMs = (int) round(microtime(true) * 1000);
            }

            // validate ตาม transactionType
            $missingBoth = ($betId === '' && $roundId === '');
            $invalid =
                ($transactionType === 'BY_TRANSACTION' && $betId === '') ||
                ($transactionType === 'BY_ROUND' && $roundId === '') ||
                ($transactionType === 'UNKNOWN' && $missingBoth);

            if ($invalid) {
                Log::warning('Invalid seamless log (by txnType)', compact('transactionType', 'productId', 'username', 'betId', 'roundId', 'item'));
                return;
            }

            // (A) เขียน Redis (namespace เฉพาะ gamedata)
            try {
                /** @var GameLogRedisService $glog */
                $glog = app(GameLogRedisService::class)->withNamespace('gamedata:log', 'gdataidx', 'gdata:once');

                $amount = strcasecmp($betStatus, 'SETTLED') === 0 ? $payout : $stake;

                if($betStatus === 'ROLLBACK'){
                    $payout = -$payout;
                }else if($betStatus === 'REFUND'){
                    $stake = -$stake;
                }else if($betStatus === 'VOID'){
                    $stake = -$stake;
                    $payout = -$payout;
                }

                $payload = [
                    'doc_type' => 'gamedata',
                    'input' => $item,
                    'output' => [
                        'productId'        => $productId,
                        'username'         => $username,
                        'balanceBefore'    => (float) $beforebalance,
                        'balanceAfter'     => (float) $afterbalance,
                        // ฝังเวลาไว้ที่ output ด้วย เพื่อให้ flusher pick ได้เร็ว
                        'timestampMillis'  => $tsMs,
                    ],
                    // duplicate ไว้ top-level ด้วย (flusher รองรับหลายแหล่ง)
                    'timestampMillis' => $tsMs,

                    'betAmount'    => $stake,
                    'payoutAmount' => $payout,
                    'company'      => $productId,
                    'game_user'    => (string) $username,
                    'method'       => strtoupper($betStatus ?: 'UNKNOWN'), // ใช้เป็น betStatus
                    'response'     => 'in',
                    'amount'       => (float) $amount,

                    'con_1' => $betId,
                    'con_2' => $roundId,
                    'con_3' => $isSingleState ? 1 : 0,
                    'con_4' => $transactionType,

                    'before_balance' => (float) $beforebalance,
                    'after_balance'  => (float) $afterbalance,

                    'date_create' => now()->toDateTimeString(),

                    // เก็บเป็น ISO string; flusher มี toUtc() รองรับ
                    'expireAt' => (new UTCDateTime(now()->addDays($ttlDays)))
                        ->toDateTime()
                        ->format('c'),

                    'game_name'           => $gameName,
                    'skip_balance_update' => $skipBalanceUpd ? 1 : 0,

                    // ไว้เป็น fallback ชั้นท้าย (ms เช่นกัน)
                    'created_at' => $tsMs,
                ];

                $glog->saveGameLogToRedis($payload);
            } catch (\Throwable $e) {
                Log::channel('gamelog')->warning('[LogSeamless] Redis stage failed', ['err' => $e->getMessage()]);
            }

            // (B) Legacy เขียนตรง Mongo (ออปชัน)
            if (!$async && $legacy) {
                $date_create = now()->toDateTimeString();
                $expireAt    = new UTCDateTime(now()->addDays($ttlDays));

                $common = [
                    'username'          => $username,
                    'gameName'          => $gameName,
                    'date_create'       => $date_create,
                    'expireAt'          => $expireAt,
                    'betStatus'         => $betStatus,
                    'isSingleState'     => $isSingleState,
                    'skipBalanceUpdate' => $skipBalanceUpd,
                    'betId'             => $betId,
                    'roundId'           => $roundId,
                    'before_balance'    => $beforebalance,
                    'after_balance'     => $afterbalance,
                    // เก็บเวลาไว้ใน output style เดิมไม่ได้ แต่ใส่ created_at เพื่อความสอดคล้อง
                    'created_at'        => new UTCDateTime((int) ($tsMs)),
                ];

                if ($isSingleState) {
                    if ($transactionType === 'BY_ROUND') {
                        $where   = ['productId' => $productId, 'username' => $username, 'roundId' => $roundId];
                        $common += ['betId' => $betId, 'stake' => $stake, 'payout' => $payout];
                    } elseif ($transactionType === 'UNKNOWN') {
                        $where   = ['productId' => $productId, 'username' => $username, 'betId' => $betId, 'roundId' => $roundId];
                        $common += ['stake' => $stake, 'payout' => $payout];
                    } else { // BY_TRANSACTION
                        $where   = ['productId' => $productId, 'username' => $username, 'betId' => $betId];
                        $common += ['roundId' => $roundId, 'stake' => $stake, 'payout' => $payout];
                    }
                } else {
                    if ($transactionType === 'BY_ROUND') {
                        $where   = ['productId' => $productId, 'username' => $username, 'roundId' => $roundId];
                        $common += ['betId' => $betId];
                    } elseif ($transactionType === 'UNKNOWN') {
                        $where = ['productId' => $productId, 'username' => $username, 'betId' => $betId, 'roundId' => $roundId];
                    } else { // BY_TRANSACTION
                        $where   = ['productId' => $productId, 'username' => $username, 'betId' => $betId];
                        $common += ['roundId' => $roundId];
                    }

                    if (strcasecmp($betStatus, 'OPEN') === 0) {
                        $common += ['stake' => $stake, 'payout' => $payout];
                    } elseif (strcasecmp($betStatus, 'SETTLED') === 0) {
                        if ($stake > 0) $common += ['stake' => $stake];
                        $common += ['payout' => $payout];
                    } else {
                        $common += ['stake' => $stake, 'payout' => $payout];
                    }
                }

                if ($update) {
                    GameData::updateOrCreate($where, $common);
                } else {
                    GameData::create($common);
                }
            }
        } catch (\Throwable $e) {
            Log::error('LogSeamless failed', [
                'error' => $e->getMessage(),
                'product' => $product,
                'username' => $username,
                'item' => $item,
                'timestamp' => $timestamp,
            ]);
        }
    }

    /**
     * คืนค่า milliseconds จากรูปแบบเวลาหลากหลาย
     * - int/float/string ตัวเลข: auto-detect sec vs ms
     * - ISO 8601 / มี timezone: ใช้ strtotime()
     * - สตริงไม่ระบุโซน: parse เป็น Asia/Bangkok แล้วแปลง UTC
     */
    protected static function normalizeMillis($v): ?int
    {
        if ($v === null) return null;

        // ตัวเลขหรือสตริงตัวเลข
        if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v))) {
            $n = (int) $v;
            // ถ้าใหญ่กว่า ~1e12 ถือเป็น ms; ถ้า ~1e9 เป็น sec
            if ($n >= 1_500_000_000_000) { // ~2017-07-14 in ms
                return $n; // already ms
            }
            if ($n >= 1_500_000_000) {     // ~2017-07-14 in sec
                return $n * 1000;
            }
            // เลขเล็ก異常: ถือเป็น sec
            return $n * 1000;
        }

        // สตริงวันที่
        if (is_string($v)) {
            // มี timezone หรือ Z ท้ายสตริง → ให้ strtotime จัดการ
            if (preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $v)) {
                $ts = strtotime($v);
                return $ts !== false ? $ts * 1000 : null;
            }
            // ไม่มี timezone → parse เป็น Asia/Bangkok แล้วแปลงเป็น UTC
            try {
                $dt = \Carbon\Carbon::parse($v, 'Asia/Bangkok')->utc();
                return $dt->getTimestamp() * 1000;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected static function pickMillis($item): ?int
    {
        $pick = function ($v) {
            if (is_array($v) && isset($v['$numberLong'])) return (int) $v['$numberLong'];
            return is_numeric($v) ? (int) $v : null;
        };

        if (is_array($item)) {
            // 1) output.timestampMillis
            if (($ms = $pick(Arr::get($item, 'output.timestampMillis'))) !== null) return $ms;
            // 2) top-level timestampMillis
            if (($ms = $pick(Arr::get($item, 'timestampMillis'))) !== null) return $ms;
            // 3) input.timestampMillis
            if (($ms = $pick(Arr::get($item, 'input.timestampMillis'))) !== null) return $ms;
            // 4) txns.0.timestampMillis (เผื่อบางค่าย)
            if (($ms = $pick(Arr::get($item, 'txns.0.timestampMillis'))) !== null) return $ms;
        }
        return null;
    }
}
