<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class GameLogRedisService
{
    /** @var \Illuminate\Redis\Connections\Connection */
    protected $redis;

    /** TTL ของคีย์ใน Redis (วินาที) */
    protected int $ttlSeconds;

    /** รากคีย์/ดัชนี (ทำให้เปลี่ยนเนมสเปซได้) */
    protected string $keyRoot = 'game:log';
    protected string $idxRoot = 'idx';
    protected string $onceRoot = 'glog:once';

    public function __construct(?string $connection = null, ?int $ttlSeconds = null)
    {
        $conn = $connection
            ?? env('REDIS_GAMELOG_CONNECTION')
            ?? 'gamelog';

        try {
            $this->redis = Redis::connection($conn);
        } catch (\Throwable $e) {
            $this->redis = Redis::connection(); // default
        }

        $this->ttlSeconds = $ttlSeconds ?? (int)env('REDIS_GAMELOG_TTL', 86400); // 1 วัน
    }

    /** คลอนด้วยเนมสเปซคีย์ใหม่ (กันชนกับระบบอื่น) */
    public function withNamespace(string $keyRoot, string $idxRoot = 'idx', ?string $onceRoot = null): self
    {
        $c = clone $this;
        $c->keyRoot = rtrim($keyRoot, ':');
        $c->idxRoot = rtrim($idxRoot, ':');
        if ($onceRoot !== null) $c->onceRoot = rtrim($onceRoot, ':');
        return $c;
    }

    /* ======================= Core ======================= */

    /**
     * บันทึกเกมล็อกลง Redis แล้วคืน log_id (ยูนีคจริง)
     * ฟิลด์ที่รับได้: input, output, company, game_user, method, response, amount,
     * con_1..con_4, before_balance, after_balance, date_create, expireAt, created_at
     */
    public function saveGameLogToRedis(array $data): string
    {
        // 1) serialize input/output
        foreach (['input', 'output'] as $k) {
            if (isset($data[$k]) && (is_array($data[$k]) || is_object($data[$k]))) {
                $data[$k] = json_encode($data[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // 2) สร้าง log_id แบบ ms + sequence (จาก Redis TIME + INCR)
        $logId  = $this->nextLogId();
        $logKey = $this->keyMain($logId);

        // 3) เตรียมค่า index
        $user    = (string)($data['game_user'] ?? '_');
        $company = (string)($data['company'] ?? '_');
        $method  = strtoupper((string)($data['method'] ?? '_'));
        $con1    = $data['con_1'] ?? null;
        $con2    = $data['con_2'] ?? null;

        // ใช้ log_id เป็น score เพื่อเรียงตรงกับลำดับเขียนจริง 100%
        $score = (int)$logId;

        $base = $this->baseIndex($user, $company, $method);

        // 4) เตรียมค่า main/addon
        $mainFields = $this->flattenForRedis($data);
        $addonKey   = $this->keyAddon($logId);
        $createdAtIso = $this->formatCreatedAtIso($data['created_at'] ?? ($data['date_create'] ?? null));

        $addonData = [
            'amount'      => isset($data['amount']) ? (string)$data['amount'] : '0',
            'method'      => $method,
            'con_1'       => $con1 !== null ? (string)$con1 : '',
            'con_2'       => $con2 !== null ? (string)$con2 : '',
            'con_3'       => array_key_exists('con_3', $data) && !is_null($data['con_3'])
                ? (is_bool($data['con_3']) ? ($data['con_3'] ? 'true' : 'false') : (string)$data['con_3'])
                : 'null',
            'con_4'       => array_key_exists('con_4', $data) && !is_null($data['con_4']) ? (string)$data['con_4'] : 'null',
            'created_at'  => $createdAtIso,
            'doc_type'    => (string)($data['doc_type'] ?? ''), // เผื่อ logic แยก doc type
        ];

        // 5) เขียนแบบอะตอมมิก: main + addon + index (MULTI/EXEC)
        $ok = false;
        try {
            if ($this->redis instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                $cli = $this->redis->client();
                $cli->multi(); // BEGIN

                // main
                $cli->hMSet($logKey, $mainFields);
                $cli->expire($logKey, $this->ttlSeconds);

                // addon
                $cli->hMSet($addonKey, $addonData);
                $cli->expire($addonKey, $this->ttlSeconds);

                // index: all / con1 / con2
                $cli->zAdd("{$base}:all", $score, $logId);
                $cli->expire("{$base}:all", $this->ttlSeconds);

                if ($con1 !== null && $con1 !== '') {
                    $cli->zAdd("{$base}:con1", $score, $logId);
                    $cli->expire("{$base}:con1", $this->ttlSeconds);
                }
                if ($con2 !== null && $con2 !== '') {
                    $cli->zAdd("{$base}:con2", $score, $logId);
                    $cli->expire("{$base}:con2", $this->ttlSeconds);
                }

                $cli->exec(); // COMMIT
                $ok = true;
            } else {
                // พยายาม MULTI/EXEC ผ่าน raw (Predis)
                $raw = $this->redis->client();
                $raw->executeRaw(['MULTI']);

                $raw->executeRaw($this->hmsetRawArgs($logKey, $mainFields));
                $raw->executeRaw(['EXPIRE', $logKey, (string)$this->ttlSeconds]);

                $raw->executeRaw($this->hmsetRawArgs($addonKey, $addonData));
                $raw->executeRaw(['EXPIRE', $addonKey, (string)$this->ttlSeconds]);

                $raw->executeRaw(['ZADD', "{$base}:all", (string)$score, (string)$logId]);
                $raw->executeRaw(['EXPIRE', "{$base}:all", (string)$this->ttlSeconds]);

                if ($con1 !== null && $con1 !== '') {
                    $raw->executeRaw(['ZADD', "{$base}:con1", (string)$score, (string)$logId]);
                    $raw->executeRaw(['EXPIRE', "{$base}:con1", (string)$this->ttlSeconds]);
                }
                if ($con2 !== null && $con2 !== '') {
                    $raw->executeRaw(['ZADD', "{$base}:con2", (string)$score, (string)$logId]);
                    $raw->executeRaw(['EXPIRE', "{$base}:con2", (string)$this->ttlSeconds]);
                }

                $raw->executeRaw(['EXEC']);
                $ok = true;
            }
        } catch (\Throwable $e) {
            // fallback → pipeline (ไม่อะตอมแต่ให้ผ่าน)
            try {
                $this->redis->pipeline(function ($pipe) use ($logKey, $mainFields, $addonKey, $addonData, $base, $score, $logId, $con1, $con2) {
                    $pipe->hMSet($logKey, $mainFields);
                    $pipe->expire($logKey, $this->ttlSeconds);

                    $pipe->hMSet($addonKey, $addonData);
                    $pipe->expire($addonKey, $this->ttlSeconds);

                    $pipe->zadd("{$base}:all", $score, $logId);
                    $pipe->expire("{$base}:all", $this->ttlSeconds);

                    if ($con1 !== null && $con1 !== '') {
                        $pipe->zadd("{$base}:con1", $score, $logId);
                        $pipe->expire("{$base}:con1", $this->ttlSeconds);
                    }
                    if ($con2 !== null && $con2 !== '') {
                        $pipe->zadd("{$base}:con2", $score, $logId);
                        $pipe->expire("{$base}:con2", $this->ttlSeconds);
                    }
                });
                $ok = true;
            } catch (\Throwable $e2) {
                $ok = false;
                Log::warning('[gamelog] pipeline failed', ['err' => $e2->getMessage()]);
            }
        }

        // 6) ดีบักให้ครบทั้ง main และ index
        if ($ok) {
            Log::channel('gamelog')->debug("[gamelog] wrote main key={$logKey} method={$method} user={$user}");
            Log::channel('gamelog')->debug("[gamelog] indexed log_id={$logId} base={$base} score={$score}");
        } else {
            Log::channel('gamelog')->error("[gamelog] write failed log_id={$logId} base={$base}");
        }

        return $logId;
    }

    /** อัปเดตฟิลด์เดียวของ log */
    public function updateLogField(string $logId, string $field, $value, string $user = '', string $company = ''): void
    {
        if ($field === 'con_3' || $field === 'con_4') {
            $this->redis->hset($this->keyAddon($logId), $field, $value === null ? 'null' : (string)$value);
            $this->redis->expire($this->keyAddon($logId), $this->ttlSeconds);
            return;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $this->redis->hset($this->keyMain($logId), $field, (string)($value ?? ''));
        $this->redis->expire($this->keyMain($logId), $this->ttlSeconds);
    }

    /** ดึง log เต็ม (main + addon) */
    public function getLog(string $logId): array
    {
        $main  = $this->redis->hgetall($this->keyMain($logId))  ?: [];
        $addon = $this->redis->hgetall($this->keyAddon($logId)) ?: [];
        if (!$main && !$addon) return [];
        return array_merge($main, $addon, ['log_id' => $logId]);
    }

    /** ค้น log เต็ม (main+addon) จากดัชนี */
    public function findGameLogs(string $user, string $company, string $method, array $opt = []): array
    {
        $base  = $this->baseIndex($user, $company, $method);
        $limit = (int)($opt['limit'] ?? 50);

        $idsAll = $this->redis->zrevrange("{$base}:all", 0, $limit - 1) ?: [];
        if (isset($opt['con_1'])) {
            $idsC1 = $this->redis->zrevrange("{$base}:con1", 0, $limit - 1) ?: [];
            $idsAll = array_values(array_intersect($idsAll, $idsC1));
        }
        if (isset($opt['con_2'])) {
            $idsC2 = $this->redis->zrevrange("{$base}:con2", 0, $limit - 1) ?: [];
            $idsAll = array_values(array_intersect($idsAll, $idsC2));
        }

        if (empty($idsAll)) return [];

        $resMain = $this->redis->pipeline(function ($p) use ($idsAll) {
            foreach ($idsAll as $id) $p->hgetall($this->keyMain($id));
        });
        $resAddon = $this->redis->pipeline(function ($p) use ($idsAll) {
            foreach ($idsAll as $id) $p->hgetall($this->keyAddon($id));
        });

        $out = [];
        foreach ($idsAll as $i => $id) {
            $m = $resMain[$i] ?? [];
            $a = $resAddon[$i] ?? [];
            if (!$m && !$a) continue;

            foreach (['con_3', 'con_4'] as $k) {
                if (isset($a[$k])) $a[$k] = $this->normalizeBoolNull($a[$k]);
            }
            $out[] = array_merge($m, $a, ['log_id' => $id]);
        }

        return $out;
    }

    public function queryGameLogs(string $user, string $company, string $method, array $opt = []): array
    {
        $limit  = max(1, (int)($opt['limit'] ?? 50));
        $offset = max(0, (int)($opt['offset'] ?? 0));
        $order  = in_array(strtolower((string)($opt['order'] ?? 'desc')), ['asc', 'desc'], true)
            ? strtolower((string)($opt['order'] ?? 'desc'))
            : 'desc';

        $base  = $this->baseIndex($user, $company, $method);
        $keyAll = "{$base}:all";
        $start  = $offset;
        $end    = $offset + $limit - 1;

        $ids = $order === 'desc'
            ? ($this->redis->zrevrange($keyAll, $start, $end) ?: [])
            : ($this->redis->zrange($keyAll, $start, $end) ?: []);

        $wantC1 = array_key_exists('con_1', $opt) ? (string)$opt['con_1'] : null;
        $wantC2 = array_key_exists('con_2', $opt) ? (string)$opt['con_2'] : null;

        if (!empty($ids) && ($wantC1 !== null || $wantC2 !== null)) {
            $addonRows = $this->redis->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) $p->hgetall($this->keyAddon($id));
            });

            $filtered = [];
            foreach ($ids as $i => $id) {
                $a = $addonRows[$i] ?? [];
                $c1 = (string)($a['con_1'] ?? '');
                $c2 = (string)($a['con_2'] ?? '');
                $ok = true;
                if ($wantC1 !== null && $c1 !== $wantC1) $ok = false;
                if ($wantC2 !== null && $c2 !== $wantC2) $ok = false;
                if ($ok) $filtered[] = $id;
            }
            $ids = $filtered;
        }

        if (empty($ids)) {
            return ['items' => [], 'next_offset' => null];
        }

        $resMain = $this->redis->pipeline(function ($p) use ($ids) {
            foreach ($ids as $id) $p->hgetall($this->keyMain($id));
        });
        $resAddon = $this->redis->pipeline(function ($p) use ($ids) {
            foreach ($ids as $id) $p->hgetall($this->keyAddon($id));
        });

        $items = [];
        foreach ($ids as $i => $id) {
            $m = $resMain[$i] ?? [];
            $a = $resAddon[$i] ?? [];
            if (!$m && !$a) continue;

            foreach (['con_3', 'con_4'] as $k) {
                if (isset($a[$k])) {
                    $t = strtolower((string)$a[$k]);
                    if ($t === '' || $t === 'null') $a[$k] = null;
                    elseif ($t === 'true') $a[$k] = true;
                    elseif ($t === 'false') $a[$k] = false;
                }
            }

            $items[] = array_merge($m, $a, ['log_id' => $id]);
        }

        $next = (count($ids) === $limit) ? ($offset + $limit) : null;
        return ['items' => $items, 'next_offset' => $next];
    }

    /** กันซ้ำแบบ AND ด้วย con_1 + con_2 */
    public function hasDuplicatePair(string $user, string $company, string $method, string $con1, string $con2, int $window = 100): bool
    {
        $res = $this->queryGameLogs($user, $company, $method, [
            'con_1' => $con1,
            'con_2' => $con2,
            'limit'  => max(1, $window),
            'offset' => 0,
            'order'  => 'desc',
        ]);

        foreach ($res['items'] as $it) {
            if (($it['response'] ?? '') === 'in') return true;
        }
        return false;
    }

    public function queryGameLogIds(string $user, string $company, string $method, array $opt = []): array
    {
        $limit  = max(1, (int)($opt['limit'] ?? 50));
        $offset = max(0, (int)($opt['offset'] ?? 0));
        $order  = in_array(strtolower((string)($opt['order'] ?? 'desc')), ['asc', 'desc'], true)
            ? strtolower((string)($opt['order'] ?? 'desc'))
            : 'desc';

        $base  = $this->baseIndex($user, $company, $method);
        $keyAll = "{$base}:all";
        $start  = $offset;
        $end    = $offset + $limit - 1;

        $ids = $order === 'desc'
            ? ($this->redis->zrevrange($keyAll, $start, $end) ?: [])
            : ($this->redis->zrange($keyAll, $start, $end) ?: []);

        $wantC1 = array_key_exists('con_1', $opt) ? (string)$opt['con_1'] : null;
        $wantC2 = array_key_exists('con_2', $opt) ? (string)$opt['con_2'] : null;

        if (!empty($ids) && ($wantC1 !== null || $wantC2 !== null)) {
            $addonRows = $this->redis->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) $p->hgetall($this->keyAddon($id));
            });

            $ids = array_values(array_filter($ids, function ($id, $i) use ($addonRows, $wantC1, $wantC2) {
                $a = $addonRows[$i] ?? [];
                $c1 = (string)($a['con_1'] ?? '');
                $c2 = (string)($a['con_2'] ?? '');
                if ($wantC1 !== null && $c1 !== $wantC1) return false;
                if ($wantC2 !== null && $c2 !== $wantC2) return false;
                return true;
            }, ARRAY_FILTER_USE_BOTH));
        }

        $next = (count($ids) === $limit) ? ($offset + $limit) : null;
        return ['ids' => $ids, 'next_offset' => $next];
    }

    public function hasDuplicate(string $user, string $company, string $method, ?string $con1, ?string $con2, string $response = 'in'): bool
    {
        if ($con1 === null && $con2 === null) return false;

        $check = function (array $filter) use ($user, $company, $method, $response): bool {
            $items = $this->findGameLogs($user, $company, $method, $filter + ['limit' => 20]);
            foreach ($items as $it) {
                $okR = !isset($it['response']) || $response === '' || (string)($it['response']) === $response;
                $ok1 = !isset($filter['con_1']) || (string)($it['con_1'] ?? '') === (string)$filter['con_1'];
                $ok2 = !isset($filter['con_2']) || (string)($it['con_2'] ?? '') === (string)$filter['con_2'];
                if ($okR && $ok1 && $ok2) return true;
            }
            return false;
        };

        if ($con1 !== null && $check(['con_1' => $con1])) return true;
        if ($con2 !== null && $check(['con_2' => $con2])) return true;

        return false;
    }

    public function latestBy(string $user, string $company, string $method, array $opt = []): ?array
    {
        $items = $this->findGameLogs($user, $company, $method, $opt + ['limit' => 1]);
        return $items[0] ?? null;
    }

    /* ======================= Helpers ======================= */

    protected function keyMain(string $id): string
    {
        return "{$this->keyRoot}:{$id}";
    }

    protected function keyAddon(string $id): string
    {
        return "{$this->keyRoot}:addon:{$id}";
    }

    protected function baseIndex(string $user, string $company, string $method): string
    {
        $user    = $user !== '' ? $user : '_';
        $company = $company !== '' ? $company : '_';
        $method  = strtoupper($method !== '' ? $method : '_');
        return "{$this->idxRoot}:{$user}:{$company}:{$method}";
    }

    protected function flattenForRedis(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $k => $v) {
            if (is_null($v)) $out[$k] = '';
            elseif (is_bool($v)) $out[$k] = $v ? 'true' : 'false';
            elseif (is_scalar($v)) $out[$k] = (string)$v;
            else $out[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $out;
    }

    protected function normalizeBoolNull($v)
    {
        if ($v === null || is_bool($v)) return $v;
        if (is_string($v)) {
            $t = strtolower(trim($v));
            if ($t === '' || $t === 'null') return null;
            if ($t === 'true') return true;
            if ($t === 'false') return false;
        }
        return $v;
    }

    // สร้าง gate กันซ้ำแบบครั้งเดียว (true=ได้สิทธิ์, false=ซ้ำ)
    public function reserveOnce(
        string  $method,
        string  $company,
        string  $user,
        ?string $con1,
        ?string $con2,
        int     $ttlSeconds
    ): bool
    {
        $c1 = $con1 ?? '-';
        $c2 = $con2 ?? '-';
        $key = "{$this->onceRoot}:{$method}:{$company}:{$user}:{$c1}:{$c2}";
        $ttl = (int)$ttlSeconds;

        $conn = $this->redis;

        try {
            if (method_exists($conn, 'command')) {
                $res = $conn->command('set', [$key, '1', 'NX', 'EX', $ttl]);
                return (bool)$res;
            }

            if ($conn instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                $client = $conn->client();
                $ok = $client->set($key, '1', ['nx', 'ex' => $ttl]);
                return (bool)$ok;
            }

            if ($conn instanceof \Illuminate\Redis\Connections\PredisConnection) {
                $res = $conn->client()->executeRaw(['SET', $key, '1', 'NX', 'EX', (string)$ttl]);
                return $res === 'OK';
            }
        } catch (\Throwable $e) {
        }

        try {
            $script = "return redis.call('SET', KEYS[1], '1', 'NX', 'EX', ARGV[1]) and 1 or 0";
            if ($conn instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                $ok = (int)$conn->client()->eval($script, [$key], [(string)$ttl]) === 1;
                return $ok;
            } else {
                $ok = (int)$conn->eval($script, 1, $key, (string)$ttl) === 1;
                return $ok;
            }
        } catch (\Throwable $e) {
            try {
                if ($conn instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                    $client = $conn->client();
                    if ($client->setNx($key, '1')) {
                        $client->expire($key, $ttl);
                        return true;
                    }
                    return false;
                } else {
                    $set = $conn->client()->executeRaw(['SETNX', $key, '1']);
                    if ((int)$set === 1) {
                        $conn->client()->executeRaw(['EXPIRE', $key, (string)$ttl]);
                        return true;
                    }
                    return false;
                }
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    /** บันทึกเป็นชุดผ่าน pipeline (เผื่อใช้งานอื่น) */
    public function saveLogPipeline(array $logs, ?int $ttlSec = null): array
    {
        $ids = [];
        $keys = [];
        $now = time();

        $this->redis->pipeline(function ($pipe) use (&$ids, &$keys, $logs, $ttlSec, $now) {
            foreach ($logs as $log) {
                $id  = $log['log_id'] ?? $this->nextLogId();
                $key = $this->keyMain($id);

                $fields = (function (array $log) {
                    foreach ($log as $k => $v) {
                        if (is_array($v) || is_object($v)) $log[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    return $log;
                })($log);

                $pipe->hMSet($key, $fields);

                $ttl = $ttlSec;
                if ($ttl === null) {
                    if (!empty($log['expireAt']) && is_numeric($log['expireAt'])) {
                        $ttl = max(1, (int)$log['expireAt'] - $now);
                    }
                }
                if (!empty($ttl)) {
                    $pipe->expire($key, (int)$ttl);
                }

                $ids[] = $id;
                $keys[] = $key;
            }
        });

        return ['id_list' => $ids, 'key_list' => $keys, 'count' => count($ids)];
    }

    /* ======================= Low-level helpers ======================= */

    /** log_id แบบ ms*4096 + seq (seq ต่อ 1ms) จาก Redis TIME + INCR + PEXPIRE */
    protected function nextLogId(): string
    {
        try {
            if ($this->redis instanceof \Illuminate\Redis\Connections\PhpRedisConnection) {
                $r = $this->redis->client();
                $time = $r->time(); // [sec, micro]
                $ms = ((int)$time[0]) * 1000 + (int)floor(((int)$time[1]) / 1000);
                $seqKey = $this->keyRoot . ':seq:' . $ms;
                $seq = (int)$r->incr($seqKey);
                $r->pexpire($seqKey, 2000);
                $seq = $seq % 4096; // 0..4095 ต่อ 1ms
                return (string)($ms * 4096 + $seq);
            } else {
                // Predis/raw
                $cli = $this->redis->client();
                $t   = $cli->executeRaw(['TIME']); // ["sec","usec"]
                $ms  = ((int)$t[0]) * 1000 + (int)floor(((int)$t[1]) / 1000);
                $seqKey = $this->keyRoot . ':seq:' . $ms;
                $seq = (int)$cli->executeRaw(['INCR', $seqKey]);
                $cli->executeRaw(['PEXPIRE', $seqKey, '2000']);
                $seq = $seq % 4096;
                return (string)($ms * 4096 + $seq);
            }
        } catch (\Throwable $e) {
            // fallback (นาน ๆ ที) → microtime + random ป้องกันชน
            $ms = (int)round(microtime(true) * 1000);
            $seq = random_int(0, 4095);
            return (string)($ms * 4096 + $seq);
        }
    }

    protected function hmsetRawArgs(string $key, array $fields): array
    {
        $args = ['HMSET', $key];
        foreach ($fields as $k => $v) {
            $args[] = (string)$k;
            $args[] = (string)$v;
        }
        return $args;
    }

    protected function formatCreatedAtIso($v): string
    {
        // คงเดิมถ้าเป็นสตริง ISO แล้ว
        if (is_string($v) && $v !== '') return $v;
        if (is_numeric($v)) {
            $n = (int)$v;
            if ($n > 1_500_000_000_000) $n = intdiv($n, 1000);
            return gmdate('c', $n);
        }
        return gmdate('c');
    }
}
