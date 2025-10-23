<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use MongoDB\Driver\Manager as MongoManager;
use MongoDB\Driver\WriteConcern;

class RedisToMongoFlusher
{
    /** @var \Illuminate\Redis\Connections\Connection */
    protected $redis;

    /** Mongo */
    protected string $mongoDsn;
    protected string $mongoNamespace; // ex: 'gamelogs.gamelog'

    /** Locks / checkpoints */
    protected string $lockPrefix   = 'gamelog:flush:lock:';
    protected string $hwmKeySuffix = 'gamelog:flush:hwm';

    /** key patterns (หลายรุ่น) */
    protected array $addonPatterns = [
        'game:log:addon:*',
        'glog:addon:*',
    ];

    public function __construct()
    {
        $connName = env('REDIS_GAMELOG_CONNECTION', 'gamelog');
        $this->redis = Redis::connection($connName);

        $this->mongoDsn = config('database.connections.mongodb.dsn')
            ?? config('database.mongodb.dsn')
            ?? env('MONGODB_DSN', 'mongodb://127.0.0.1:27017');

        $this->mongoNamespace = config('database.connections.mongodb.collection.game_logs')
            ?? config('database.mongodb.collection.game_logs')
            ?? env('MONGODB_NS_GAME_LOGS', 'gamelogs.gamelog');
    }

    /* ===================== Public ===================== */

    /** Flush logs ของ user เดียว (deterministic: cutoff snapshot) */
    public function flushUser(string $user, int $batch = 2000): array
    {
        $buffer = [];
        $processed = 0;

        $inserted = 0;
        $matched = 0;
        $modified = 0;
        $skipped = 0;

        // snapshot: เอา rid สูงสุด ณ ตอนเริ่ม → กันผลไม่นิ่ง
        $cutoff = $this->maxRidAtStart();

        $lock = $this->acquireLock("user:{$user}", 60_000);
        if (!$lock) {
            Log::info("RedisToMongoFlusher skip (locked) user={$user}");
            return ['processed' => 0, 'inserted' => 0, 'matched' => 0, 'modified' => 0, 'skipped' => 0];
        }

        try {
            $seen = [];
            $iter = $this->iterateLogIdsUser($user, $cutoff);

            foreach ($iter as $logId) {
                if (isset($seen[$logId])) continue;
                $seen[$logId] = 1;

                $addon = $this->redis->hgetall("game:log:addon:{$logId}") ?: [];
                $merged = $this->hydrate($logId, $addon);
                if (!$merged) { $skipped++; continue; }

                $buffer[] = $this->toMongoDoc($merged);
                $processed++;

                if (\count($buffer) >= $batch) {
                    usort($buffer, fn($a, $b) => $this->cmpDocs($a, $b));
                    $stat = $this->bulkUpsert($buffer);
                    $inserted += (int)($stat['inserted'] ?? 0);
                    $matched  += (int)($stat['matched']  ?? 0);
                    $modified += (int)($stat['modified'] ?? 0);
                    $skipped  += (int)($stat['skipped']  ?? 0);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                usort($buffer, fn($a, $b) => $this->cmpDocs($a, $b));
                $stat = $this->bulkUpsert($buffer);
                $inserted += (int)($stat['inserted'] ?? 0);
                $matched  += (int)($stat['matched']  ?? 0);
                $modified += (int)($stat['modified'] ?? 0);
                $skipped  += (int)($stat['skipped']  ?? 0);
            }

            Log::channel('gamelog')->info(
                "[RedisToMongoFlusher] flushUser user={$user} processed={$processed} "
                . "inserted={$inserted} matched={$matched} modified={$modified} skipped={$skipped} cutoff={$cutoff}"
            );

            return compact('processed','inserted','matched','modified','skipped');
        } catch (\Throwable $e) {
            Log::error('RedisToMongoFlusher fatal', ['msg' => $e->getMessage()]);
            return ['processed' => $processed, 'inserted' => 0, 'matched'=>0, 'modified'=>0, 'skipped'=>0, 'error' => $e->getMessage()];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /** Flush ทั้งระบบ (ของใครของมัน ตาม prefix/DSN) + HWM */
    public function flushAll(int $batch = 2000): array
    {
        $lock = $this->acquireLock('all', 300_000); // 5 นาที
        if (!$lock) {
            Log::info('[RedisToMongoFlusher] flushAll skip (locked)');
            return ['processed'=>0,'inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>0];
        }

        $buffer = [];
        $processed = $inserted = $matched = $modified = $skipped = 0;
        $seen = [];

        try {
            $hwm    = $this->loadHwm();
            $cutoff = $this->maxRidAtStart();
            $maxRidSeen = $hwm;

            $consume = function (iterable $ids) use (&$seen, &$buffer, $batch, &$processed, &$inserted, &$matched, &$modified, &$skipped, $hwm, $cutoff, &$maxRidSeen) {
                foreach ($ids as $logId) {
                    if (isset($seen[$logId])) continue;
                    $rid = $this->redisIdNum($logId);
                    if ($rid === 0 || $rid <= $hwm || $rid > $cutoff) continue;

                    $addon = $this->redis->hgetall("game:log:addon:{$logId}") ?: [];
                    $merged = $this->hydrate($logId, $addon);
                    if (!$merged) { $skipped++; $seen[$logId] = 1; continue; }

                    $buffer[] = $this->toMongoDoc($merged);
                    $processed++;
                    $seen[$logId] = 1;
                    if ($rid > $maxRidSeen) $maxRidSeen = $rid;

                    if (\count($buffer) >= $batch) {
                        usort($buffer, fn($a,$b) => $this->cmpDocs($a,$b));
                        $stat = $this->bulkUpsert($buffer);
                        $inserted += (int)($stat['inserted'] ?? 0);
                        $matched  += (int)($stat['matched']  ?? 0);
                        $modified += (int)($stat['modified'] ?? 0);
                        $skipped  += (int)($stat['skipped']  ?? 0);
                        $buffer = [];

                        // commit checkpoint เป็นระยะ
                        $this->saveHwm($maxRidSeen);
                    }
                }
            };

            // main ก่อน → addon เก็บตก
            $consume($this->scanMainKeysAll(1000));
            $consume($this->scanAddonIdsAll(1000));

            if (!empty($buffer)) {
                usort($buffer, fn($a,$b) => $this->cmpDocs($a,$b));
                $stat = $this->bulkUpsert($buffer);
                $inserted += (int)($stat['inserted'] ?? 0);
                $matched  += (int)($stat['matched']  ?? 0);
                $modified += (int)($stat['modified'] ?? 0);
                $skipped  += (int)($stat['skipped']  ?? 0);
                $buffer = [];
            }

            $this->saveHwm($maxRidSeen);

            Log::channel('gamelog')->info(
                "[RedisToMongoFlusher] flushAll processed={$processed} inserted={$inserted} matched={$matched} modified={$modified} skipped={$skipped} hwm={$hwm} cutoff={$cutoff} newHwm={$maxRidSeen}"
            );

            return compact('processed','inserted','matched','modified','skipped');
        } catch (\Throwable $e) {
            Log::error('RedisToMongoFlusher flushAll fatal', ['msg'=>$e->getMessage()]);
            return ['processed'=>$processed,'inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>0,'error'=>$e->getMessage()];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /* ===================== Hydrate & Convert ===================== */

    /** รวม main + addon, ตั้งเวลาโดยยึด redis_id เป็น fallback หลัก */
    protected function hydrate(string $logId, array $addonFromScan): ?array
    {
        $main  = $this->redis->hgetall("game:log:{$logId}") ?: [];
        $addon = $addonFromScan ?: ($this->redis->hgetall("game:log:addon:{$logId}") ?: []);
        if (!$main && !$addon) return null;

        foreach (['con_3', 'con_4'] as $k) {
            if (array_key_exists($k, $addon)) {
                $addon[$k] = $this->normalizeBoolNull($addon[$k]);
                if ($addon[$k] === '') $addon[$k] = null;
            }
        }

        $merged = array_merge($main, $addon, [
            'log_id'   => $logId,
            'redis_id' => (string)$logId,
        ]);

        $merged['company']   = $merged['company']   ?? null;
        $merged['game_user'] = $merged['game_user'] ?? null;
        $merged['method']    = strtoupper((string)($merged['method'] ?? ''));

        if (!isset($merged['con_1']) && isset($addon['con_1'])) $merged['con_1'] = $addon['con_1'];
        if (!isset($merged['con_2']) && isset($addon['con_2'])) $merged['con_2'] = $addon['con_2'];

        foreach (['amount', 'before_balance', 'after_balance'] as $n) {
            if (isset($merged[$n])) $merged[$n] = (float)$merged[$n];
        }

        $merged['input']  = $this->normalizeDocValue($merged['input']  ?? null);
        $merged['output'] = $this->normalizeDocValue($merged['output'] ?? null);

        // เลือกเวลา: output.ts → input.ts → (ไม่มี) ใช้ redis_id → สุดท้าย created_at/date_create
        $readMs = function ($val) {
            if (is_array($val) && isset($val['$numberLong'])) return (int)$val['$numberLong'];
            return is_numeric($val) ? (int)$val : null;
        };

        $tsMs = null;
        if (is_array($merged['output'])) {
            $tsMs = $readMs($merged['output']['timestampMillis'] ?? null);
        } elseif ($merged['output'] instanceof \stdClass) {
            $tsMs = $readMs($merged['output']->timestampMillis ?? null);
        }
        if ($tsMs === null) {
            if (is_array($merged['input'])) {
                $tsMs = $readMs($merged['input']['timestampMillis'] ?? null);
            } elseif ($merged['input'] instanceof \stdClass) {
                $tsMs = $readMs($merged['input']->timestampMillis ?? null);
            }
        }

        $ridNum = $this->redisIdNum($logId);
        $ridMs  = $ridNum > 0 ? ($ridNum > 1_500_000_000_000 ? $ridNum : $ridNum * 1000) : null;

        $fallbackStrMs = $this->strTimeToMs($merged['created_at'] ?? ($merged['date_create'] ?? null));

        $merged['_created_at'] = $tsMs
            ?? $ridMs
            ?? $fallbackStrMs
            ?? (int)round(microtime(true) * 1000);

        // กันเขียนทับ
        $merged['_expireAt'] = $merged['expireAt'] ?? null;

        return $merged;
    }

    protected function toMongoDoc(array $doc): array
    {
        $createdUtc = $this->toUtcDateTimeOrNull($doc['_created_at'] ?? null) ?? new UTCDateTime();
        $expireUtc  = $this->toUtcDateTimeOrNull($doc['_expireAt'] ?? null);
        if (!$expireUtc) {
            $ttlDays = $this->defaultTtlDays($doc['company'] ?? null);
            $expireMs = $createdUtc->toDateTime()->getTimestamp() * 1000 + ($ttlDays * 86400 * 1000);
            $expireUtc = new UTCDateTime($expireMs);
        }

        $company = $doc['company'] ?? null;
        $method  = strtoupper((string)($doc['method'] ?? ''));
        $amount  = (float)($doc['amount'] ?? 0);

        $before = isset($doc['before_balance']) ? (float)$doc['before_balance'] : null;
        $after  = isset($doc['after_balance'])  ? (float)$doc['after_balance']  : null;

        $input  = $this->normalizeDocValue($doc['input']  ?? null);
        $output = $this->normalizeDocValue($doc['output'] ?? null);
        $dateCreate = $doc['date_create'] ?? null;

        $fp = sha1(implode('|', [
            $company ?? '',
            $doc['game_user'] ?? '',
            $method,
            $doc['con_1'] ?? '',
            $doc['con_2'] ?? '',
            (string)$amount,
            (string)($doc['response'] ?? ''),
            $createdUtc->toDateTime()->format('Uu'),
        ]));

        return [
            'input'   => $input,
            'output'  => $output,

            'company'   => $company,
            'game_user' => $doc['game_user'] ?? null,
            'method'    => $method,
            'response'  => $doc['response'] ?? null,
            'amount'    => $amount,

            'con_1' => $doc['con_1'] ?? null,
            'con_2' => $doc['con_2'] ?? null,
            'con_3' => $doc['con_3'] ?? null,
            'con_4' => $doc['con_4'] ?? null,

            'before_balance' => $before,
            'after_balance'  => $after,

            'date_create' => $dateCreate,
            'expireAt'    => $expireUtc,
            'updated_at'  => new UTCDateTime(),
            'created_at'  => $createdUtc,

            'redis_id'    => (string)($doc['redis_id'] ?? ''),
            'status'      => $doc['status'] ?? null,
            'by_id'       => $doc['by_id'] ?? null,
            'fingerprint' => $fp,
        ];
    }

    /* ===================== Sort & Upsert ===================== */

    /** เรียงตาม redis_id (เลข) เป็นหลัก → ผูกด้วย created_at → method → redis_id(string) */
    protected function cmpDocs(array $a, array $b): int
    {
        $ar = $this->redisIdNum($a['redis_id'] ?? null);
        $br = $this->redisIdNum($b['redis_id'] ?? null);
        if ($ar !== $br) return $ar <=> $br;

        $am = $this->toMs($a['created_at'] ?? null);
        $bm = $this->toMs($b['created_at'] ?? null);
        if ($am !== $bm) return $am <=> $bm;

        $aw = strcmp((string)($a['method'] ?? ''), (string)($b['method'] ?? ''));
        if ($aw !== 0) return $aw;

        $arStr = (string)($a['redis_id'] ?? '');
        $brStr = (string)($b['redis_id'] ?? '');
        return $arStr <=> $brStr;
    }

    // ใน RedisToMongoFlusher

    /**
     * Upsert แบบรักษาลำดับฟิลด์:
     * - เอกสารใหม่: ใช้ insert (preserve field order exactly as toMongoDoc)
     * - เอกสารเก่า: ใช้ $set (เฉพาะฟิลด์ที่ปรับได้) เพื่อไม่รื้อ order เดิม
     * - ต้องมี unique index ที่ redis_id
     */
    protected function bulkUpsert(array $docs): array
    {
        if (empty($docs)) return ['inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>0];

        $manager = new \MongoDB\Driver\Manager($this->mongoDsn, $this->mongoUriOptions());

        // กรองตัวที่ไม่มี redis_id ทิ้ง และอัปเดต updated_at ให้เป็นตอนเขียน
        $ridMap = [];
        $skipped = 0;
        foreach ($docs as $d) {
            $rid = (string)($d['redis_id'] ?? '');
            if ($rid === '') { $skipped++; continue; }
            $d['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            $ridMap[$rid] = $d;
        }
        if (!$ridMap) return ['inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>$skipped];

        // เรียงตาม redis_id (เลข) เป็นหลัก เพื่อ deterministic
        $rids = array_keys($ridMap);
        usort($rids, function($a,$b){
            $na = is_numeric($a)?(int)$a:0;
            $nb = is_numeric($b)?(int)$b:0;
            if ($na !== $nb) return $na <=> $nb;
            return strcmp((string)$a,(string)$b);
        });

        // เช็คว่าอะไรมีอยู่แล้ว
        $existing = [];
        try {
            $filter = ['redis_id' => ['$in' => $rids]];
            $query  = new \MongoDB\Driver\Query($filter, ['projection'=>['redis_id'=>1,'_id'=>1]]);
            $cursor = $manager->executeQuery($this->mongoNamespace, $query);
            foreach ($cursor as $row) $existing[(string)$row->redis_id] = true;
        } catch (\Throwable $e) {
            // ถ้าเช็คล้มเหลว เดี๋ยวไปชน unique key ค่อยนับ skipped อีกที
        }

        $bulk = new \MongoDB\Driver\BulkWrite(['ordered'=>false]);
        $insertCandidates = 0;
        $updateCandidates = 0;

        foreach ($rids as $rid) {
            $doc = $ridMap[$rid];

            if (!isset($existing[$rid])) {
                // INSERT — ใส่ _id ให้เอง และ preserve order เท่าที่ toMongoDoc สร้าง
                if (!isset($doc['_id'])) $doc['_id'] = new \MongoDB\BSON\ObjectId();
                if (!isset($doc['created_at'])) $doc['created_at'] = new \MongoDB\BSON\UTCDateTime();
                $bulk->insert($doc);
                $insertCandidates++;
            } else {
                // UPDATE — ใช้ $set แบบ “เฉพาะฟิลด์ที่ควรอัปเดต” เพื่อไม่ทำลาย order เดิม
                $set = $this->mutableSet($doc);
                if (!empty($set)) {
                    $bulk->update(['redis_id'=>$rid], ['$set'=>$set], ['upsert'=>false]);
                    $updateCandidates++;
                }
            }
        }

        if ($insertCandidates + $updateCandidates === 0) {
            return ['inserted'=>0,'matched'=>0,'modified'=>0,'skipped'=>$skipped];
        }

        $wc = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 5000);
        try {
            $res = $manager->executeBulkWrite($this->mongoNamespace, $bulk, $wc);
        } catch (\TypeError|\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            // driver บางเวอร์ชันต้องส่ง options array
            try {
                $res = $manager->executeBulkWrite($this->mongoNamespace, $bulk, ['writeConcern'=>$wc]);
            } catch (\MongoDB\Driver\Exception\BulkWriteException $be) {
                // duplicate key → นับเป็น skipped
                $skipped += $this->countDuplicateErrors($be);
                $inserted = max(0, $insertCandidates - $skipped);
                $matched  = 0; // เราไม่ได้ใช้ matched ของ update ตรง ๆ
                $modified = 0;
                return compact('inserted','matched','modified','skipped');
            }
        } catch (\MongoDB\Driver\Exception\BulkWriteException $be) {
            $skipped += $this->countDuplicateErrors($be);
            $inserted = max(0, $insertCandidates - $skipped);
            $matched  = 0;
            $modified = 0;
            return compact('inserted','matched','modified','skipped');
        }

        $inserted = method_exists($res,'getInsertedCount') ? (int)$res->getInsertedCount() : 0;
        $matched  = method_exists($res,'getMatchedCount')  ? (int)$res->getMatchedCount()  : 0;
        $modified = method_exists($res,'getModifiedCount') ? (int)$res->getModifiedCount() : 0;

        return compact('inserted','matched','modified','skipped');
    }

    /**
     * ฟิลด์ที่ “อัปเดตได้” โดยไม่รื้อ order เดิมของเอกสาร:
     * - หลีกเลี่ยง created_at (ปล่อยไว้)
     * - ไม่แตะ input/output ถ้าอยากคง order เดิมของ raw (แล้วแต่ดีไซน์คุณ)
     * - ปรับเวลาหมดอายุ / สถานะ / ยอด / updated_at ฯลฯ
     */
    protected function mutableSet(array $d): array
    {
        $set = [];

        // ปรับเฉพาะ metadata ที่เปลี่ยนตามเวลา
        if (isset($d['expireAt']))        $set['expireAt']        = $d['expireAt'];
        if (isset($d['updated_at']))      $set['updated_at']      = $d['updated_at'];
        if (array_key_exists('status',$d))$set['status']          = $d['status'];
        if (array_key_exists('response',$d)) $set['response']     = $d['response'];

        // ยอด/บาลานซ์ที่อาจแก้ไข
        foreach (['amount','before_balance','after_balance'] as $k) {
            if (array_key_exists($k,$d)) $set[$k] = $d[$k];
        }

        // ถ้าต้องการให้ปรับ input/output ด้วย ก็เติมสองบรรทัดนี้ (แต่มีโอกาสทำให้ขนาดเอกสารโต)
        if (array_key_exists('input',$d))  $set['input']  = $d['input'];
        if (array_key_exists('output',$d)) $set['output'] = $d['output'];

        // ฟิลด์บ่งชี้อื่น ๆ ที่ไม่น่าทำลาย order เดิม
        foreach (['by_id','con_1','con_2','con_3','con_4','fingerprint','method','game_user','company','date_create'] as $k) {
            if (array_key_exists($k,$d)) $set[$k] = $d[$k];
        }

        // หลีกเลี่ยงการแตะ created_at เพื่อคงค่าตั้งต้น
        unset($set['created_at'], $set['_id'], $set['redis_id']);

        return $set;
    }

    /** นับ duplicate key errors (E11000) ให้กลายเป็น skipped */
    protected function countDuplicateErrors(\MongoDB\Driver\Exception\BulkWriteException $be): int
    {
        $n = 0;
        $result = $be->getWriteResult();
        if ($result) {
            foreach ($result->getWriteErrors() as $we) {
                $msg = $we->getMessage();
                if (stripos($msg, 'E11000') !== false) $n++;
            }
        }
        return $n;
    }


    /* ===================== Scan & Iterate ===================== */

    /** สแกน main keys ทั้งระบบ (prefix-aware) → yield {id} */
    protected function scanMainKeysAll(int $count = 1000): \Generator
    {
        $client = $this->redis->client();
        $prefix = (string)$client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix !== '' ? $prefix : '') . 'game:log:*';

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, $count);
            if ($keys && $keys !== false) {
                foreach ($keys as $full) {
                    if (strpos($full, ':addon:') !== false) continue;
                    $k = $this->stripPrefixFromKey($full);
                    $pos = strrpos($k, ':');
                    if ($pos === false) continue;
                    $id = substr($k, $pos + 1);
                    if ($id !== '') yield $id;
                }
            }
        } while ($it != 0);
    }

    /** สแกน addon ids ทั้งระบบ (เพื่อเก็บตก) */
    protected function scanAddonIdsAll(int $count = 1000): \Generator
    {
        $client = $this->redis->client();
        $prefix = (string)$client->getOption(\Redis::OPT_PREFIX);
        $pattern = ($prefix !== '' ? $prefix : '') . 'game:log:addon:*';

        $it = null;
        do {
            $keys = $client->scan($it, $pattern, $count);
            if ($keys && $keys !== false) {
                foreach ($keys as $full) {
                    $k = $this->stripPrefixFromKey($full); // game:log:addon:{id}
                    $id = substr($k, strlen('game:log:addon:'));
                    if ($id !== '') yield $id;
                }
            }
        } while ($it != 0);
    }

    /** iterate ids ของ user เดียว: main ก่อน → addon เก็บตก (deterministic ด้วย cutoff) */
    protected function iterateLogIdsUser(string $user, int $cutoff): \Generator
    {
        $seen = [];
        $wantDigits = $this->normalizeUserForCompare($user);

        // main ก่อน
        foreach ($this->scanMainKeysAll(1000) as $id) {
            $rid = $this->redisIdNum($id);
            if ($rid === 0 || $rid > $cutoff) continue;
            if (isset($seen[$id])) continue;

            $main  = $this->redis->hgetall("game:log:{$id}") ?: [];
            $addon = $this->redis->hgetall("game:log:addon:{$id}") ?: [];

            $u = (string)($main['game_user'] ?? ($addon['game_user'] ?? ''));
            if ($u !== '' && $this->userEquals($u, $wantDigits)) {
                $seen[$id] = 1;
                yield $id;
                continue;
            }

            $guess = $this->guessUserFromInput($main['input'] ?? ($addon['input'] ?? ''));
            if ($guess !== '' && $this->userEquals($guess, $wantDigits)) {
                $seen[$id] = 1;
                yield $id;
            }
        }

        // addon เก็บตก
        foreach ($this->scanAddonIdsAll(1000) as $id) {
            $rid = $this->redisIdNum($id);
            if ($rid === 0 || $rid > $cutoff) continue;
            if (isset($seen[$id])) continue;

            $addon = $this->redis->hgetall("game:log:addon:{$id}") ?: [];
            $main  = $this->redis->hgetall("game:log:{$id}") ?: [];

            $u = (string)($main['game_user'] ?? ($addon['game_user'] ?? ''));
            if ($u !== '' && $this->userEquals($u, $wantDigits)) {
                $seen[$id] = 1; yield $id; continue;
            }
            $guess = $this->guessUserFromInput($main['input'] ?? ($addon['input'] ?? ''));
            if ($guess !== '' && $this->userEquals($guess, $wantDigits)) {
                $seen[$id] = 1; yield $id;
            }
        }
    }

    /* ===================== Utils ===================== */

    protected function redisPrefix(): string
    {
        try {
            if ($this->redis instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                return (string)$this->redis->client()->getOption(\Redis::OPT_PREFIX) ?: '';
            }
        } catch (\Throwable $e) {}
        return '';
    }

    protected function stripPrefixFromKey(string $fullKey): string
    {
        $prefix = $this->redisPrefix();
        if ($prefix !== '' && str_starts_with($fullKey, $prefix)) {
            return substr($fullKey, strlen($prefix));
        }
        return $fullKey;
    }

    protected function normalizeUserForCompare(string $u): string
    {
        $u = trim($u);
        $u = ltrim($u, "uU");
        $u = preg_replace('/\D+/', '', $u);
        return $u ?: '';
    }

    protected function userEquals(string $fromLog, string $wantDigits): bool
    {
        $a = $this->normalizeUserForCompare($fromLog);
        $b = $wantDigits;
        if ($a === '' || $b === '') return false;
        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    protected function guessUserFromInput($input): string
    {
        if (is_string($input)) {
            $t = trim($input);
            if ($t === '' || strtolower($t) === 'array') return '';
            if ($t[0] !== '{' && $t[0] !== '[') return '';
            $j = json_decode($t, true);
            $input = (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [];
        } elseif ($input instanceof \stdClass) {
            $input = (array)$input;
        } elseif (!is_array($input)) {
            $input = [];
        }

        $keys = [
            'username','user_name','user','account','acct','phone','mobile','tel',
            'member','memberCode','member_code','code','userId','user_id','uid',
            'player','player_account','login','login_name',
        ];

        foreach ($keys as $k) {
            if (isset($input[$k]) && is_scalar($input[$k])) {
                $v = trim((string)$input[$k]);
                if ($v !== '') return $v;
            }
        }
        foreach ($input as $v) {
            if (is_array($v)) {
                foreach ($keys as $k2) {
                    if (isset($v[$k2]) && is_scalar($v[$k2])) {
                        $vv = trim((string)$v[$k2]);
                        if ($vv !== '') return $vv;
                    }
                }
            }
        }
        return '';
    }

    protected function toUtcDateTimeOrNull($v): ?UTCDateTime
    {
        if ($v instanceof UTCDateTime) return $v;
        if (is_numeric($v)) {
            $n = (int)$v;
            if ($n > 1_500_000_000_000) $n = intdiv($n, 1000);
            return new UTCDateTime($n * 1000);
        }
        if (is_string($v)) {
            $t = trim($v);
            if (preg_match('/(Z|[+\-]\d{2}:?\d{2})$/i', $t)) {
                $ts = strtotime($t);
                return $ts !== false ? new UTCDateTime($ts * 1000) : null;
            }
            try {
                $dt = \Carbon\Carbon::parse($t, 'Asia/Bangkok')->utc();
                return new UTCDateTime($dt->getTimestamp() * 1000);
            } catch (\Throwable $e) {
                $ts = strtotime($t);
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

    protected function normalizeBoolNull($v)
    {
        if (is_bool($v) || $v === null) return $v;
        if (is_string($v)) {
            $t = strtolower(trim($v));
            if ($t === '' || $t === 'null') return null;
            if ($t === 'true') return true;
            if ($t === 'false') return false;
        }
        return $v;
    }

    protected function strTimeToMs($v): ?int
    {
        $utc = $this->toUtcDateTimeOrNull($v);
        if (!$utc) return null;
        $dt = $utc->toDateTime();
        return ((int)$dt->format('U')) * 1000 + (int)$dt->format('v');
    }

    protected function toMs($v): int
    {
        if ($v instanceof UTCDateTime) {
            $dt = $v->toDateTime();
            return ((int)$dt->format('U')) * 1000 + (int)$dt->format('v');
        }
        if (is_numeric($v)) return (int)$v;
        return 0;
    }

    protected function defaultTtlDays(?string $company): int
    {
        $seven = [
            'UMBET', 'LALIKA', 'AFB1188', 'VIRTUAL_SPORT',
            'COC', 'AMBSPORTBOOK', 'SABASPORTS', 'SBO',
            'AOG', 'FB_SPORT', 'DB SPORTS',
        ];
        if ($company && in_array($company, $seven, true)) return 7;
        return (int)env('REDIS_MONGO_TTL_DAYS', 3);
    }

    /* ===== rid helpers / lock / HWM ===== */

    protected function redisIdNum($v): int
    {
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int)$v;
        if (is_string($v)) {
            $digits = preg_replace('/\D+/', '', $v);
            return $digits !== '' ? (int)$digits : 0;
        }
        return 0;
    }

    protected function acquireLock(string $name, int $ttlMs = 30000): ?string
    {
        $token = bin2hex(random_bytes(16));
        $key = $this->lockPrefix . $name;
        $ok = $this->redis->set($key, $token, 'PX', $ttlMs, 'NX');
        return $ok ? "{$key}|{$token}" : null;
    }

    protected function releaseLock(?string $lock): void
    {
        if (!$lock) return;
        [$key, $token] = explode('|', $lock, 2);
        $lua = <<<LUA
if redis.call('GET', KEYS[1]) == ARGV[1] then
  return redis.call('DEL', KEYS[1])
end
return 0
LUA;
        $this->redis->eval($lua, 1, $key, $token);
    }

    protected function hwmKey(): string
    {
        $prefix = $this->redisPrefix();
        return ($prefix !== '' ? $prefix : '') . $this->hwmKeySuffix;
    }

    protected function loadHwm(): int
    {
        $v = $this->redis->get($this->hwmKey());
        return is_numeric($v) ? (int)$v : 0;
    }

    protected function saveHwm(int $rid): void
    {
        $this->redis->set($this->hwmKey(), (string)$rid);
    }

    /** rid สูงสุด ณ ตอนเริ่มรอบ (สแกนแค่ main+addon) */
    protected function maxRidAtStart(): int
    {
        $max = 0;
        foreach ($this->scanMainKeysAll(2000) as $id) {
            $n = $this->redisIdNum($id);
            if ($n > $max) $max = $n;
        }
        foreach ($this->scanAddonIdsAll(2000) as $id) {
            $n = $this->redisIdNum($id);
            if ($n > $max) $max = $n;
        }
        return $max;
    }

    /* ===== Mongo options ===== */

    protected function mongoUriOptions(): array
    {
        $opt = [
            'tls' => true,
            'serverSelectionTryOnce' => false,
            'serverSelectionTimeoutMS' => (int)env('MONGODB_SELECT_TIMEOUT_MS', 8000),
            'appName' => env('MONGODB_APPNAME', 'RedisToMongoFlusher'),
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
}
