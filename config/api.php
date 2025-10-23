<?php

return [
    'time_budget' => [
        // ใช้ค่าเดียวกับ prod ได้เลย แล้วคุมผ่าน .env
        'placebets' => env('PLACEBETS_TIME_LIMIT', 3.5),
    ],
    'test' => [
        // อนุญาตใช้ header/param เพื่อหน่วงเวลาเฉพาะ env ที่กำหนด
        'allow_delay' => env('ALLOW_TEST_DELAY', false),
        // default delay (ms) ถ้าไม่ส่งตัวเลขมา
        'default_delay_ms' => env('DEFAULT_TEST_DELAY_MS', 3800),
        // ชื่อ header สำหรับสั่งหน่วง
        'delay_header' => env('TEST_DELAY_HEADER', 'X-Test-Delay-Ms'),
        // ชื่อ query สำหรับสั่งหน่วง
        'delay_query'  => env('TEST_DELAY_QUERY', '_delay_ms'),
    ],
];
