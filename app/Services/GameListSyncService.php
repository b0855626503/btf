<?php

namespace App\Services;

use Gametech\API\Models\GameList as GameListProxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * ซิงก์รายการเกมจาก Provider API -> MongoDB
 * - Upsert เกมที่เข้ามา
 * - ปิด (enable=false) เกมที่ "หายไป" จากลิสต์ล่าสุด
 * - คุมแคชสำหรับแสดงผล และ timestamp ซิงก์ล่าสุดต่อค่าย
 * - ป้องกันชนกันด้วย distributed lock
 */
class GameListSyncService
{
    /**
     * ซิงก์จากผลลัพธ์ API
     *
     * @param string $productId รหัสค่าย (เช่น AMBSUPERAPI / HUAYDRAGON ฯลฯ)
     * @param array $apiGames รายการเกม (array ของ item)
     * @param array $opts ['disable_missing' => true] เพื่อตั้งค่าให้ปิดเกมที่หายไป
     * @return array               ['success'=>bool,'msg'=>string,'count'=>int]
     */
    public function syncFromApi(string $productId, array $apiGames, array $opts = []): array
    {
        $productId = Str::upper(trim($productId));
        $disableMissing = $opts['disable_missing'] ?? true;
        $now = GameListProxy::mongoNow();

        // 1) Normalize ข้อมูลขาเข้า + เก็บ incoming codes
        $incoming = [];
        foreach ($apiGames as $g) {
            $row = $this->normalizeGameRow($productId, $g);
            if ($row === null) {
                continue; // ข้าม item ที่ไม่มี code
            }
            $incoming[$row['code']] = $row;
        }
        $incomingCodes = array_keys($incoming);

        // 2) ดึงของเดิม (เฉพาะฟิลด์ที่ใช้ตัดสินใจ)
        $existing = GameListProxy::where('product', $productId)
            ->get(['code', 'name', 'category', 'type', 'img', 'rank', 'enable', 'click', 'game', 'hash'])
            ->keyBy('code');

        // 3) Upsert เฉพาะที่จำเป็น (ใช้ hash ลด write)
        foreach ($incoming as $code => $row) {
            $hash = $this->calcHash($row);
            $payload = $row + ['hash' => $hash, 'disabled_at' => null];

            if (isset($existing[$code])) {
                $exists = $existing[$code];
                if (($exists->hash ?? null) !== $hash || $exists->enable !== true) {
                    GameListProxy::updateOrCreate(
                        ['product' => $productId, 'code' => $code],
                        $payload
                    );
                }
            } else {
                GameListProxy::updateOrCreate(
                    ['product' => $productId, 'code' => $code],
                    $payload + ['click' => 0]
                );
            }
        }

        // 4) ปิดเกมที่ไม่อยู่ในผลลัพธ์รอบนี้
        if ($disableMissing) {
            $query = GameListProxy::where('product', $productId)->where('enable', true);
            if (count($incomingCodes) > 0) {
                $query->whereNotIn('code', $incomingCodes);
            }
            $query->update(['enable' => false, 'disabled_at' => $now]);
        }

        // 5) Invalidate แคช + mark timestamp
        Cache::forget($this->cacheKey($productId));
        $this->markSyncedNow($productId);

        return [
            'success' => true,
            'msg' => 'Synced',
            'count' => count($incomingCodes),
        ];
    }

    /**
     * คืนลิสต์เกมจากแคช (ถ้าไม่มีจะอ่านจาก DB แล้วแคชให้)
     * เรียงตาม click desc, rank desc
     */
    public function getCachedList(string $productId, int $ttlSec = 600): array
    {
        $productId = Str::upper(trim($productId));
        $key = $this->cacheKey($productId);

        return Cache::remember($key, $ttlSec, function () use ($productId) {
            return GameListProxy::where('product', $productId)
                ->where('enable', true)
                ->orderByDesc('click')
                ->orderByDesc('rank')
                ->get()
                ->toArray();
        });
    }

    /**
     * กุญแจแคชของลิสต์เกมต่อค่าย
     */
    public function cacheKey(string $productId): string
    {
        return 'game_list:' . Str::upper(trim($productId));
    }

    /**
     * อ่านเวลา (unix ts) ซิงก์ล่าสุดของค่าย
     */
    public function getLastSyncedAt(string $productId): ?int
    {
        $productId = Str::upper($productId);
        return Cache::get("game_last_synced:{$productId}");
    }

    /**
     * อัปเดตเวลา (unix ts) ซิงก์ล่าสุดของค่าย = ตอนนี้
     */
    public function markSyncedNow(string $productId): void
    {
        $productId = Str::upper($productId);
        Cache::put("game_last_synced:{$productId}", time(), 86400); // เก็บ 1 วัน
    }

    /**
     * เช็กว่าควรซิงก์ตาม TTL หรือยัง
     */
    public function shouldSync(string $productId, int $ttlSec): bool
    {
        $last = $this->getLastSyncedAt($productId);
        if (!$last) {
            return true;
        }
        return (time() - $last) >= $ttlSec;
    }

    /**
     * รัน callback ภายใต้ distributed lock ต่อค่าย เพื่อกันชนกันเวลาซิงก์
     *
     * @return bool สำเร็จ (ได้ล็อกและรันแล้ว) = true, ไม่ได้ล็อก/ไม่รัน = false
     */
    public function withSyncLock(string $productId, int $lockSec, callable $callback): bool
    {
        $lock = Cache::lock($this->lockKey($productId), $lockSec);

        try {
            if ($lock->get()) {
                try {
                    $callback();
                } finally {
                    $lock->release();
                }
                return true;
            }
        } catch (LockTimeoutException $e) {
            // เงียบ ๆ ไป ไม่ถือเป็นความผิดพลาดร้ายแรง
        } catch (\Throwable $e) {
            try {
                $lock->release();
            } catch (\Throwable $ignore) {
            }
            throw $e;
        }

        return false;
    }

    /**
     * กุญแจ lock สำหรับซิงก์ต่อค่าย
     */
    protected function lockKey(string $productId): string
    {
        return 'lock:game_sync:' . Str::upper(trim($productId));
    }

    // =========================
    // Helpers ภายใน
    // =========================

    /**
     * แปลง item เกมจาก API ให้อยู่ในรูปแบบที่เก็บใน Mongo
     * คืนค่า null ถ้าไม่มี code
     */
    protected function normalizeGameRow(string $productId, array $g): ?array
    {
        $code = (string)($g['code'] ?? $g['game'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'product' => $productId,
            'code' => $code,
            'game' => $code,
            'name' => (string)($g['name'] ?? $code),
            'category' => (string)($g['category'] ?? 'EGAMES'),
            'type' => (string)($g['type'] ?? 'SLOT'),
            'img' => (string)($g['img'] ?? ''),
            'rank' => (int)($g['rank'] ?? 0),
            'enable' => true,
        ];
    }

    /**
     * สร้าง hash สำหรับตัดสินใจอัปเดตเฉพาะเมื่อข้อมูลสำคัญเปลี่ยน
     */
    protected function calcHash(array $row): string
    {
        return md5(json_encode([
            $row['name'] ?? null,
            $row['category'] ?? null,
            $row['type'] ?? null,
            $row['img'] ?? null,
            $row['rank'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }
}