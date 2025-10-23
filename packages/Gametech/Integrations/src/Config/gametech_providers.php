<?php

return [
    // map group_bot -> provider class
    'map' => [
        '1' => \Gametech\Integrations\Providers\VegusProvider::class,
        '2' => \Gametech\Integrations\Providers\IgoalProvider::class,
        '3' => \Gametech\Integrations\Providers\KrakenProvider::class,
        '4' => \Gametech\Integrations\Providers\LsmProvider::class,
    ],

    // ค่ามาตรฐานสำหรับ timeout/retry
    'timeouts' => 15,
    'retries'  => [
        'times'    => 2,
        'sleep_ms' => 300,
    ],
];
