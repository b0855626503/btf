<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Manager as MongoManager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Int64;

class RedisToMongoGameDataFlusher
{
    /** @var \Illuminate\Redis\Connections\Connection */
    protected $redis;

    protected string $mongoDsn;
    protected string $mongoNamespace; // เช่น 'gamelogs.gamedatas'

    // รากคีย์ของ gamedata เท่านั้น
    protected string $keyRoot = 'gamedata:log';
    protected string $idxRoot = 'gdataidx';

    /**
     * หลักเวลา:
     * - ใช้ timestampMillis (top-level) → output.timestampMillis → input.timestampMillis → txns.0.timestampMillis → created_at/date_create
     * - created_at จะอิง ms ข้างบนเสมอ และเก็บ ts_ms (Int64) ไว้สำหรับ sort/index
     * Multi-state:
     * - isSingleState=false → ทุก event เป็นเอกสารใหม่ (where = productId+username+rid)
     */
    public function __construct()
    {
        $this->redis = Redis::connection(env('REDIS_GAMELOG_CONNECTION', 'gamelog'));

        $this->mongoDsn = config('database.connections.mongodb.dsn')
            ?? env('MONGODB_DSN', 'mongodb://127.0.0.1:27017');

        $this->mongoNamespace = config('database.connections.mongodb.collection.gamedatas')
            ?? env('MONGODB_NS_GAME_DATA', 'gamelogs.gamedatas');
    }

    public function flushUser(string $user, int $batch = 500): array
    {
        $buf = [];
        $processed = 0;
        $inserted = 0;
        $matched = 0;
        $modified = 0;
        $skipped = 0;

        // snapshot เวลา (ไม่เอาไป filter id แล้ว — แค่ล็อกดีบัก)
        $cutoffMs = (int) round(microtime(true) * 1000);

        $usedIndex = false;
        foreach ($this->scanIndexIdsByUser($user, 1000) as $logId) {
            $usedIndex = true;

            $row = $this->hydrateMergedById($logId);
            if (!$row) continue;

            $built = $this->buildRow($row);
            $processed++;
            if (!$built) { $skipped++; continue; }

            $buf[] = $built;
            if (count($buf) >= $batch) {
                $stat = $this->bulkUpsert($buf);
                $inserted += (int)$stat['inserted'];
                $matched  += (int)$stat['matched'];
                $modified += (int)$stat['modified'];
                $skipped  += (int)$stat['skipped'];
                $buf = [];
            }
        }

        // Fallback: ถ้าไม่มี index หรือได้มาน้อย → SCAN main ตาม user
        if (!$usedIndex) {
            $scanned = 0; $picked = 0;
            foreach ($this->scanMainByUser($user, 1000) as [$logId, $main]) {
                $scanned++; $picked++; $processed++;

                $built  = $this->buildRow($main + ['redis_id' => $logId, 'log_id' => $logId]);
                if (!$built) { $skipped++; continue; }

                $buf[] = $built;
                if (count($buf) >= $batch) {
                    $stat = $this->bulkUpsert($buf);
                    $inserted += (int)$stat['inserted'];
                    $matched  += (int)$stat['matched'];
                    $modified += (int)$stat['modified'];
                    $skipped  += (int)$stat['skipped'];
                    $buf = [];
                }
            }
            Log::debug("[GAMEDATA] scanMainByUser scanned={$scanned} picked={$picked} user={$user} cutoffMs={$cutoffMs}");
        }

        if (!empty($buf)) {
            $stat = $this->bulkUpsert($buf);
            $inserted += (int)$stat['inserted'];
            $matched  += (int)$stat['matched'];
            $modified += (int)$stat['modified'];
            $skipped  += (int)$stat['skipped'];
        }

        Log::channel('gamelog')->info("[GAMEDATA] flushUser user={$user} processed={$processed} inserted={$inserted} matched={$matched} modified={$modified} skipped={$skipped}");
        return compact('processed', 'inserted', 'matched', 'modified', 'skipped');
    }

    /** Flush ทั้งระบบ (ไม่ระบุ user) */
    public function flushAll(int $batch = 500): array
    {
        $buf = [];
        $processed = $inserted = $matched = $modified = $skipped = 0;
        $seen = [];

        // 1) ใช้ดัชนีทั้งระบบก่อน (gdataidx:*:*:*:all)
        foreach ($this->scanIndexIdsAll(1000) as $logId) {
            if (isset($seen[$logId])) continue;
            $seen[$logId] = 1;

            $row = $this->hydrateMergedById($logId);
            if (!$row) continue;

            $built = $this->buildRow($row);
            $processed++;
            if (!$built) { $skipped++; continue; }

            $buf[] = $built;
            if (count($buf) >= $batch) {
                $stat = $this->bulkUpsert($buf);
                $inserted += (int)$stat['inserted'];
                $matched  += (int)$stat['matched'];
                $modified += (int)$stat['modified'];
                $skipped  += (int)$stat['skipped'];
                $buf = [];
            }
        }

        // 2) Fallback: สแกน main ทั้งระบบ (ไม่ยุ่ง addon)
        foreach ($this->scanMainIdsAll(1000) as $logId) {
            if (isset($seen[$logId])) continue;
            $seen[$logId] = 1;

            $row = $this->hydrateMergedById($logId);
            if (!$row) continue;

            $built = $this->buildRow($row);
            $processed++;
            if (!$built) { $skipped++; continue; }

            $buf[] = $built;
            if (count($buf) >= $batch) {
                $stat = $this->bulkUpsert($buf);
                $inserted += (int)$stat['inserted'];
                $matched  += (int)$stat['matched'];
                $modified += (int)$stat['modified'];
                $skipped  += (int)$stat['skipped'];
                $buf = [];
            }
        }

        if ($buf) {
            $stat = $this->bulkUpsert($buf);
            $inserted += (int)$stat['inserted'];
            $matched  += (int)$stat['matched'];
            $modified += (int)$stat['modified'];
            $skipped  += (int)$stat['skipped'];
        }

        Log::channel('gamelog')->info("[GAMEDATA] flushAll processed={$processed} inserted={$inserted} matched={$matched} modified={$modified} skipped={$skipped}");
        return compact('processed', 'inserted', 'matched', 'modified', 'skipped');
    }

    /* ============ Hydrate & Build ============ */

    /** gamedata ไม่มี addon → อ่านเฉพาะ main */
    protected function hydrateMergedById(string $logId): ?array
    {
        $main = $this->redis->hgetall("{$this->keyRoot}:{$logId}") ?: [];
        if (!$main) return null;

        foreach (['input', 'output'] as $k) {
            if (array_key_exists($k, $main)) $main[$k] = $this->normalizeDocValue($main[$k]);
        }

        $main['redis_id'] = $logId;
        $main['log_id']   = $logId;
        return $main;
    }

    /** สแกน main ids ทั้งระบบ (ไม่แตะ addon) */
    protected function scanMainIdsAll(int $count = 1000): \Generator
    {
        $client = $this->redis->client();
        $prefix = (string)$client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix ?: '') . "{$this->keyRoot}:*";

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, $count);
            if ($keys && $keys !== false) {
                foreach ($keys as $kfull) {
                    if (strpos($kfull, ':addon:') !== false) continue; // ตัด addon ทิ้ง
                    $k  = $this->stripPrefix($kfull, $prefix);         // gamedata:log:{id}
                    $id = substr($k, strlen("{$this->keyRoot}:"));
                    if ($id !== '') yield (string)$id;
                }
            }
        } while ($it != 0);
    }

    /** สแกนดัชนีทั้งระบบ gdataidx:*:*:*:all แล้วดึง id */
    protected function scanIndexIdsAll(int $takePerKey = 1000): \Generator
    {
        $client = $this->redis->client();
        $prefix = (string)$client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix ?: '') . "{$this->idxRoot}:*";

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, 500);
            if ($keys && $keys !== false) {
                foreach ($keys as $kfull) {
                    $k = $this->stripPrefix($kfull, $prefix); // gdataidx:{user}:{company}:{METHOD}:{bucket}
                    if (!str_ends_with($k, ':all')) continue;

                    $ids = $this->redis->zrevrange($k, 0, $takePerKey - 1) ?: [];
                    foreach ($ids as $id) {
                        if ($this->redis->exists("{$this->keyRoot}:{$id}")) {
                            yield (string)$id;
                        }
                    }
                }
            }
        } while ($it != 0);
    }

    /** ใช้ index ของ user เดียว */
    protected function scanIndexIdsByUser(string $user, int $takePerKey = 1000): \Generator
    {
        $client  = $this->redis->client();
        $prefix  = (string) $client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix ?: '') . "{$this->idxRoot}:{$user}:*";

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, 500);
            if ($keys && $keys !== false) {
                foreach ($keys as $kfull) {
                    $k = $this->stripPrefix($kfull, $prefix); // gdataidx:{user}:{company}:{METHOD}:{bucket}
                    if (!str_ends_with($k, ':all')) continue;

                    $ids = $this->redis->zrevrange($k, 0, $takePerKey - 1) ?: [];
                    foreach ($ids as $id) {
                        if ($this->redis->exists("{$this->keyRoot}:{$id}")) {
                            yield (string)$id;
                        }
                    }
                }
            }
        } while ($it != 0);
    }

    /** fallback ต่อ user: SCAN main แล้วกรอง game_user */
    protected function scanMainByUser(string $user, int $count = 1000): \Generator
    {
        $client  = $this->redis->client();
        $prefix  = (string) $client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix ?: '') . "{$this->keyRoot}:*";

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, $count);
            if ($keys && $keys !== false) {
                foreach ($keys as $kfull) {
                    $k  = $this->stripPrefix($kfull, $prefix);
                    if (str_contains($k, ':addon:')) continue;
                    $id = substr($k, strlen("{$this->keyRoot}:"));
                    if ($id === '' ) continue;

                    $main = $this->redis->hgetall($k) ?: [];
                    if (($main['game_user'] ?? '') !== $user) continue;

                    $main['input']  = $this->normalizeDocValue($main['input']  ?? null);
                    $main['output'] = $this->normalizeDocValue($main['output'] ?? null);
                    yield [$id, $main];
                }
            }
        } while ($it != 0);
    }

    protected function buildRow(array $x): ?array
    {
        // คีย์หลัก
        $productId = (string) ($x['company'] ?? $x['productId'] ?? $this->getIn($x, 'input.productId') ?? '');
        $username  = (string) ($x['game_user'] ?? $x['username'] ?? $this->getIn($x, 'input.username') ?? '');
        $betId     = (string) ($x['con_1'] ?? $this->getIn($x, 'input.id') ?? $this->getIn($x, 'input.refId') ?? '');
        $roundId   = (string) ($x['con_2'] ?? $this->getIn($x, 'input.roundId') ?? $this->getIn($x, 'input.txns.0.roundId') ?? '');

        // single-state?
        $isSingle = $this->toBoolStrict($x['con_3'] ?? null, null);
        if ($isSingle === null) $isSingle = $this->toBoolStrict($this->getIn($x, 'input.isSingleState'), false);

        // betStatus & txnType
        $betStatus = strtoupper((string) (
            $x['method'] ??
            $this->getIn($x, 'input.status') ??
            $this->getIn($x, 'input.txns.0.status') ??
            'UNKNOWN'
        ));
        $betStatus = str_starts_with($betStatus, 'SLM_') ? substr($betStatus, 4) : $betStatus;

        $txnType = strtoupper((string) (
            $x['con_4'] ??
            $this->getIn($x, 'input.txns.0.transactionType') ??
            $this->getIn($x, 'input.transactionType') ??
            'UNKNOWN'
        ));
        if ($txnType === 'BY_BET') $txnType = 'BY_TRANSACTION';
        if ($txnType === 'UNKNOWN') $txnType = ($roundId !== '') ? 'BY_ROUND' : 'BY_TRANSACTION';

        // validation ขั้นพื้นฐาน
        if ($productId === '' || $username === '') {
            Log::debug('[GAMEDATA] skipped: missing productId/username', compact('productId','username','betId','roundId','txnType','betStatus','isSingle'));
            return null;
        }
        if ($txnType === 'BY_TRANSACTION' && $betId === '') {
            Log::debug('[GAMEDATA] skipped: BY_TRANSACTION but missing betId', compact('productId','username','betId'));
            return null;
        }
        if ($txnType === 'BY_ROUND' && $roundId === '') {
            Log::debug('[GAMEDATA] skipped: BY_ROUND but missing roundId', compact('productId','username','roundId'));
            return null;
        }

        // amounts (รองรับจาก txns[0] ด้วย)
        $stake = $this->num([$this->getIn($x, 'betAmount')]);
        $payout = $this->num([$this->getIn($x, 'payoutAmount')]);

        $before = isset($x['before_balance']) ? (float)$x['before_balance'] : $this->num([$this->getIn($x, 'output.balanceBefore')]);
        $after  = isset($x['after_balance'])  ? (float)$x['after_balance']  : $this->num([$this->getIn($x, 'output.balanceAfter'), $this->getIn($x, 'output.balance')]);

        // เวลา
        $tsMsForSort = $this->pickTsMs($x);
        if ($tsMsForSort === null) $tsMsForSort = (int) round(microtime(true) * 1000);
        $createdAt   = new UTCDateTime($tsMsForSort);

        // expireAt
        $expireAt = $this->toUtc($x['expireAt'] ?? null);
        if (!$expireAt) {
            $days  = (int) env('REDIS_MONGO_TTL_DAYS', 3);
            $baseMs = $createdAt->toDateTime()->getTimestamp() * 1000;
            $expireAt = new UTCDateTime($baseMs + $days * 86400 * 1000);
        }

        // meta
        $game = (string) ($this->getIn($x, 'input.playInfo') ?? $this->getIn($x, 'input.txns.0.playInfo') ?? '');
        $redisId = (string) ($x['redis_id'] ?? $x['log_id'] ?? '');
        $rid     = $redisId !== '' ? new Int64($redisId) : new Int64((string) $createdAt->toDateTime()->format('Uv'));
        $skipUpd = (bool) ($x['skip_balance_update'] ?? false);

        // === where/set ===
        if ($isSingle && $betStatus === 'SETTLED') {
            if ($txnType === 'BY_ROUND') {
                $where = ['productId' => $productId, 'username' => $username, 'rid' => $rid];
                $set = ['betId' => $betId, 'roundId' => $roundId, 'stake' => $stake, 'payout' => $payout, 'before_balance' => $before, 'after_balance' => $after];
            } elseif ($txnType === 'UNKNOWN') {
                $where = ['productId' => $productId, 'username' => $username, 'rid' => $rid];
                if ($betId !== '') $where['betId'] = $betId;
                if ($roundId !== '') $where['roundId'] = $roundId;
                $set = ['stake' => $stake, 'payout' => $payout, 'before_balance' => $before, 'after_balance' => $after];
            } else { // BY_TRANSACTION
                $where = ['productId' => $productId, 'username' => $username, 'rid' => $rid];
                $set = [];
                if ($roundId !== '') $set['roundId'] = $roundId;
                $set += ['betId' => $betId, 'stake' => $stake, 'payout' => $payout, 'before_balance' => $before, 'after_balance' => $after];
            }
        } else {
            // Multi-state: ทุกสถานะเป็นเอกสารใหม่ (idempotent ที่ rid)
            $where = ['productId' => $productId, 'username' => $username, 'rid' => $rid];
            $set = [];
            if ($stake > 0) $set += ['stake' => $stake, 'before_balance' => $before];
            $set += ['payout' => $payout, 'after_balance' => $after];
        }

        // set รวม
        $set += [
            // reference
            'productId' => $productId,
            'username'  => $username,
            'betId'     => $betId,
            'roundId'   => $roundId,

            // meta + time
            'redis_id'   => $redisId,
            'rid'        => $rid,
            'ts_ms'      => new Int64((string) $tsMsForSort),
            'created_at' => $createdAt,
            'updated_at' => new UTCDateTime(),
            'expireAt'   => $expireAt,
            'date_create'=> is_string($x['date_create'] ?? null) ? $x['date_create'] : null,

            // state
            'betStatus'        => $betStatus,
            'isSingleState'    => $isSingle,
            'skipBalanceUpdate'=> $skipUpd,
            'transactionType'  => $txnType,

            // game/amounts
            'gameName'        => $game,
            'stake'           => $stake,
            'payout'          => $payout,
            'before_balance'  => $before,
            'after_balance'   => $after,

            // raw
            'input'  => $this->normalizeDocValue($x['input'] ?? null),
            'output' => $this->normalizeDocValue($x['output'] ?? null),
        ];

        return ['where' => $where, 'set' => $set];
    }

    // ใส่ใน RedisToMongoGameDataFlusher
    protected function toBoolStrict($v, ?bool $default = null): ?bool
    {
        if (is_bool($v)) return $v;
        if (is_int($v) || is_float($v)) return ((int)$v) !== 0;
        if (is_string($v)) {
            $t = strtolower(trim($v));
            if (in_array($t, ['1','true','yes','on'], true)) return true;
            if (in_array($t, ['0','false','no','off','null',''], true)) return false;
            return $default;
        }
        return $default;
    }

    /* ============ Bulk Upsert ============ */

    protected function bulkUpsert(array $rows): array
    {
        if (empty($rows)) return ['inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>0];

        $manager = new MongoManager($this->mongoDsn, $this->mongoUriOptions());
        $bulk    = new BulkWrite(['ordered' => false]);
        $skipped = 0;

        foreach ($rows as $r) {
            if (!isset($r['where'], $r['set'])) { $skipped++; continue; }
            $bulk->update($r['where'], ['$set' => $r['set']], ['upsert' => true]);
        }

        $wc = new WriteConcern(WriteConcern::MAJORITY, 5000);
        try {
            $res = $manager->executeBulkWrite($this->mongoNamespace, $bulk, $wc);
        } catch (\TypeError|\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $res = $manager->executeBulkWrite($this->mongoNamespace, $bulk, ['writeConcern' => $wc]);
        }

        return [
            'inserted' => method_exists($res,'getUpsertedCount') ? (int) $res->getUpsertedCount() : 0,
            'matched'  => method_exists($res,'getMatchedCount')  ? (int) $res->getMatchedCount()  : 0,
            'modified' => method_exists($res,'getModifiedCount') ? (int) $res->getModifiedCount() : 0,
            'skipped'  => $skipped,
        ];
    }

    /* ============ Utils ============ */

    /**
     * เลือก "มิลลิวินาทีดิบ" ตามลำดับ:
     * 0) top-level timestampMillis
     * 1) output.timestampMillis
     * 2) input.timestampMillis
     * 3) txns.0.timestampMillis
     * 4) created_at/date_create (แปลงเป็น ms ผ่าน toUtc)
     */
    protected function pickTsMs(array $x): ?int
    {
        $getMs = function ($v): ?int {
            if (is_array($v) && isset($v['$numberLong'])) return (int) $v['$numberLong'];
            return is_numeric($v) ? (int) $v : null;
        };

        if (($n = $getMs($x['timestampMillis'] ?? null)) !== null) return $n;
        if (($n = $getMs($this->getIn($x, 'output.timestampMillis'))) !== null) return $n;
        if (($n = $getMs($this->getIn($x, 'input.timestampMillis')))  !== null) return $n;
        if (($n = $getMs($this->getIn($x, 'input.txns.0.timestampMillis'))) !== null) return $n;

        $fallback = $x['created_at'] ?? ($x['date_create'] ?? null);
        $utc = $this->toUtc($fallback);
        return $utc ? (int) $utc->toDateTime()->format('Uv') : null;
    }

    /** ให้เป็น UTCDateTime (รองรับวินาที/มิลลิวินาที/สตริงไทยโซน) */
    protected function toUtc($v): ?UTCDateTime
    {
        if ($v instanceof UTCDateTime) return $v;
        if (is_numeric($v)) {
            $n = (int) $v;
            if ($n > 1_500_000_000_000) $n = intdiv($n, 1000);
            return new UTCDateTime($n * 1000);
        }
        if (is_string($v)) {
            if (preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $v)) {
                $ts = strtotime($v);
                return $ts !== false ? new UTCDateTime($ts * 1000) : null;
            }
            try {
                $dt = \Carbon\Carbon::parse($v, 'Asia/Bangkok')->utc();
                return new UTCDateTime($dt->getTimestamp() * 1000);
            } catch (\Throwable $e) {
                $ts = strtotime($v);
                return $ts !== false ? new UTCDateTime($ts * 1000) : null;
            }
        }
        return null;
    }

    protected function normalizeDocValue($v)
    {
        if (is_array($v)) return $v;
        if ($v instanceof \stdClass) return $v;
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '' || strtolower($t) === 'array') return new \stdClass();
            if (($t[0] ?? '') === '{' || ($t[0] ?? '') === '[') {
                $json = json_decode($t, true);
                if (json_last_error() === JSON_ERROR_NONE) return $json ?: new \stdClass();
            }
            return new \stdClass();
        }
        return new \stdClass();
    }

    /** ดึงค่าโดย path "a.b.c" รองรับ array/stdClass และ index ตัวเลข เช่น txns.0.id */
    protected function getIn($arr, string $path)
    {
        $seg = explode('.', $path);
        $x = $arr;
        foreach ($seg as $s) {
            if (is_array($x) && array_key_exists($s, $x)) {
                $x = $x[$s];
            } elseif ($x instanceof \stdClass && property_exists($x, $s)) {
                $x = $x->$s;
            } elseif (is_array($x) && ctype_digit($s) && array_key_exists((int) $s, $x)) {
                $x = $x[(int) $s];
            } else {
                return null;
            }
        }
        return $x;
    }

    /** เลือกตัวเลขอันแรกที่แปลงได้จริงจากชุด candidates */
    protected function num(array $candidates): float
    {
        foreach ($candidates as $v) {
            if ($v === null) continue;
            if (is_numeric($v)) return (float) $v;
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '' && is_numeric($t)) return (float) $t;
            }
        }
        return 0.0;
    }

    protected function mongoUriOptions(): array
    {
        $opt = [
            'tls' => true,
            'serverSelectionTryOnce' => false,
            'serverSelectionTimeoutMS' => (int) env('MONGODB_SELECT_TIMEOUT_MS', 8000),
            'appName' => env('MONGODB_APPNAME', 'RedisToMongoGameDataFlusher'),
        ];

        $ca = env('MONGODB_TLS_CA', '/etc/ssl/certs/ca-certificates.crt');
        if (is_string($ca) && $ca !== '' && @is_file($ca)) {
            $opt['tlsCAFile'] = $ca;
        }

        if (env('MONGODB_TLS_INSECURE', false)) {
            $opt['tlsInsecure'] = true;
            $opt['tlsAllowInvalidCertificates'] = true;
            $opt['tlsAllowInvalidHostnames'] = true;
        }

        return $opt;
    }

    protected function stripPrefix(string $full, string $prefix): string
    {
        return ($prefix !== '' && str_starts_with($full, $prefix)) ? substr($full, strlen($prefix)) : $full;
    }
}
