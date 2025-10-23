<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Redis as Rediss;

class RedisGameLogService
{
    public static function put(string $company, string $gameUser, string $method, string $id, array $data, int $ttl = 6000): bool
    {
        if (empty($company) || empty($gameUser) || empty($method) || empty($id)) {
            Log::error('Missing required fields for RedisGameLogService::put', compact('company', 'gameUser', 'method', 'id', 'data'));
            return false;
        }

        $key = self::makeKey($company, $gameUser, $method, $id);

        try {
            Log::info('data::put', compact('data'));
            Log::info('key::put', ['key' => $key]);
            Redis::connection('gamelog')->setex($key, $ttl, json_encode($data));

            $indexKey = self::makeIndexKey($company, $gameUser, $method);
            Log::info('indexKey::put', ['indexKey' => $indexKey]);
            Redis::connection('gamelog')->zadd($indexKey, now()->timestamp, $key);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to write log to Redis', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected static function makeKey(string $company, string $gameUser, string $method, string $id): string
    {
        return "log:{$company}:{$gameUser}:{$method}:{$id}";
    }

    protected static function makeIndexKey(string $company, string $gameUser, string $method): string
    {
        return "index:log:{$company}:{$gameUser}:{$method}";
    }

    public static function findSortedByMethod(string $company, string $gameUser, string $method, int $limit = 100, ?string $filterField = null, $filterValue = null, string $con4Condition = 'any'): array
    {
        $indexKey = self::makeIndexKey($company, $gameUser, $method);
        $results = [];

        try {
            $keys = Redis::connection('gamelog')->zrevrange($indexKey, 0, $limit - 1);
            foreach ($keys as $key) {
                $data = Redis::connection('gamelog')->get($key);
                if (! $data) continue;

                $log = json_decode($data, true);
                if (! $log) continue;

                if ($filterValue !== null && ($log[$filterField] ?? null) !== $filterValue) continue;

                $con4Match = match ($con4Condition) {
                    'null' => ($log['con_4'] ?? null) === null,
                    'not_null' => ($log['con_4'] ?? null) !== null,
                    default => true,
                };

                if ($con4Match) {
                    $results[$key] = $log;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve logs from Redis findSortedByMethod', ['error' => $e->getMessage()]);
        }

        uasort($results, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
        return $results;
    }

    public static function updateField(string $company, string $gameUser, string $method, string $id, string $field, $value): bool
    {
        $key = self::makeKey($company, $gameUser, $method, $id);
        try {
            $data = Redis::connection('gamelog')->get($key);
            if (! $data) return false;

            $log = json_decode($data, true);
            if (! is_array($log)) return false;

            $log[$field] = $value;
            $ttl = Redis::connection('gamelog')->ttl($key);
            Redis::connection('gamelog')->setex($key, max($ttl, 60), json_encode($log));
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to update log field in Redis', ['error' => $e->getMessage(), 'key' => $key]);
            return false;
        }
    }

    public static function findByRound(string $company, string $gameUser, string $roundId, string $con4Condition = 'null'): array
    {
        $redis = Redis::connection('gamelog');
        $client = $redis->client();

        $prefix = "log:"; // หรือ prefix ที่ถูกต้อง
        $pattern = "{$prefix}{$company}:{$gameUser}:*:*";

        Log::info('Pattern in findByRound', ['pattern' => $pattern]);

        $cursor = 0;
        $results = [];

        try {
            do {
                $scanResult = $client->scan($cursor, $pattern, 100);
                [$cursor, $keys] = $scanResult;

                Log::info('Scanning Redis keys', [
                    'cursor' => $cursor,
                    'keys' => $keys,
                ]);

                if (!$keys) continue;

                foreach ($keys as $key) {
                    $value = $redis->get($key);
                    if (!$value) continue;

                    $data = json_decode($value, true);
                    if (!$data || !isset($data['con_2'])) continue;

                    $matchRound = $data['con_2'] === $roundId;
                    $matchCon4 = match ($con4Condition) {
                        'null' => ($data['con_4'] ?? null) === null,
                        'not_null' => ($data['con_4'] ?? null) !== null,
                        default => true,
                    };

                    if ($matchRound && $matchCon4) {
                        $results[$key] = $data;
                    }
                }
            } while ((int) $cursor !== 0);
        } catch (\Throwable $e) {
            Log::error('Redis scan exception in findByRound', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }

        uasort($results, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
        return $results;
    }

    public static function findById(string $company, string $gameUser, string $id): array
    {
        $redis = Redis::connection('gamelog');
        $client = $redis->client();

        $pattern = "log:{$company}:{$gameUser}:*:{$id}";
        $cursor = 0;
        $results = [];

        try {
            do {
                $keys = $client->scan($cursor, $pattern, 100);
                if ($keys === false) break;

                foreach ($keys as $key) {
                    $value = $redis->get($key);
                    if ($value) {
                        $results[$key] = json_decode($value, true);
                    }
                }
            } while ((int) $cursor !== 0);
        } catch (\Throwable $e) {
            Log::error('Redis scan exception in findById', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }

        return $results;
    }

    public static function findFirstLog(string $company, string $gameUser, callable $filter): ?array
    {
        $logs = self::findLogs($company, $gameUser, $filter);
        return reset($logs) ?: null;
    }

    public static function findLogs(string $company, string $gameUser, callable $filter, array $hints = []): array
    {
        $redis = Redis::connection('gamelog');
        $client = $redis->client();

        $method = $hints['method'] ?? null;
        $con1 = $hints['con_1'] ?? null;

        $pattern = match (true) {
            $method && $con1 => "log:{$company}:{$gameUser}:{$method}:{$con1}",
            $method => "log:{$company}:{$gameUser}:{$method}:*",
            $con1 => "log:{$company}:{$gameUser}:*:{$con1}",
            default => "log:{$company}:{$gameUser}:*:*",
        };

        $cursor = 0;
        $results = [];

        try {
            do {
                $keys = $client->scan($cursor, $pattern, 100);
                if ($keys === false) break;

                foreach ($keys as $key) {
                    $value = $redis->get($key);
                    $data = json_decode($value, true);
                    if ($data && $filter($data, $key)) {
                        $results[$key] = $data;
                    }
                }
            } while ((int) $cursor !== 0);
        } catch (\Throwable $e) {
            Log::error('Redis scan exception in findLogs', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }

        uasort($results, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
        return $results;
    }

    protected static function getPrefix(): string
    {
        try {
            $client = Redis::connection('gamelog')->client();
            if ($client instanceof Rediss) {
                return $client->getOption(Rediss::OPT_PREFIX) ?? '';
            }
        } catch (\Throwable $e) {
            Log::warning('RedisGameLogService::getPrefix fallback to config', ['error' => $e->getMessage()]);
        }

        return config('database.redis.options.prefix') ?? '';
    }
}