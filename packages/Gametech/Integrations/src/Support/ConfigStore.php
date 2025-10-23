<?php

namespace Gametech\Integrations\Support;

use Gametech\Core\Models\Config as CoreConfigModel;
use Illuminate\Support\Facades\Cache;

class ConfigStore
{
    /**
     * อ่านค่า JSON (หรือ node ย่อยของ JSON) จากตาราง configs
     *
     * @param string $path    - โหมด A: ชื่อแถวใน name_en (ถ้ามี)
     *                        - โหมด B: dot-path ภายในฟิลด์ JSON ของแถว global (เช่น 'ops.deposit')
     * @param string $column  - คอลัมน์ที่เก็บ JSON (ปกติ 'content')
     * @return mixed          - array|null (ถ้าเจอเป็น node ย่อยที่เป็น array), หรือค่าอื่น ๆ ตามจริงของ JSON node นั้น
     */
    public function getJson(string $path, string $column = 'content'): mixed
    {
        // 1) พยายามอ่านแบบ "multi-row" (name_en = $path) ก่อน
        $rawRow = $this->getRawByNameEn($path, $column);
        if (!is_null($rawRow) && $rawRow !== '') {
            try {
                $json = json_decode($rawRow, true);
                return $json; // กรณีนี้คาดหวังเป็นทั้งก้อนของคอลัมน์
            } catch (\Throwable) {
                // ถ้า parse ไม่ได้ ให้ตกไปโหมด global ต่อ
            }
        }

        // 2) fallback: โหมด single-record → อ่านเรกคอร์ด global แล้ว data_get($json, $path)
        $rawGlobal = $this->getRawGlobal($column);
        if (is_null($rawGlobal) || $rawGlobal === '') {
            return null;
        }

        try {
            $json = json_decode($rawGlobal, true);
            if (!is_array($json)) {
                return null;
            }

            // รองรับ dot-notation เช่น 'ops.deposit'
            return data_get($json, $path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * อ่านค่าดิบจากแถวที่ name_en = $key (โหมด multi-row)
     */
    public function getRawByNameEn(string $key, string $column = 'content'): ?string
    {
        $cacheKey = "integrations.config.by_name_en.{$key}.{$column}";

        return Cache::remember($cacheKey, 60, function () use ($key, $column) {
            /** @var CoreConfigModel|null $row */
            $row = CoreConfigModel::query()
                ->where('name_en', $key)
                ->first();

            if (!$row) {
                return null;
            }

            return $row->{$column} ?? null;
        });
    }

    /**
     * อ่านค่าดิบจาก “เรกคอร์ด global” เพียงเรกคอร์ดเดียว (โหมด single-record)
     * นิยาม “global” ว่าเป็นเรกคอร์ดแรกสุดของตาราง (หรือคุณจะล็อกด้วย where('website', ...) ก็ได้)
     */
    public function getRawGlobal(string $column = 'content'): ?string
    {
        $cacheKey = "integrations.config.global.{$column}";

        return Cache::remember($cacheKey, 60, function () use ($column) {
            /** @var CoreConfigModel|null $row */
            $row = CoreConfigModel::query()
                ->orderBy('code', 'asc') // ปรับคอลัมน์จัดเรียงตามที่เหมาะกับสคีมาของคุณ
                ->first();

            if (!$row) {
                return null;
            }

            return $row->{$column} ?? null;
        });
    }
}
