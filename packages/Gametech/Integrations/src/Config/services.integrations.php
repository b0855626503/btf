<?php

return [
    'vegus' => [
        'username' => env('VEGUS_USERNAME', ''),
        'password' => env('VEGUS_PASSWORD',''),
    ],
    'kraken' => [
        'key' => env('KRAKEN_KEY',''),
    ],
    // เพิ่มคีย์ของ provider อื่น ๆ ที่จำเป็นในอนาคต
];
