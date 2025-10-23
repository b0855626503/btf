<?php

return [
    'name' => 'DEMO',
    'manifest' => [
        'name' => env('APP_NAME', 'My PWA App'),
        'short_name' => 'DEMO',
        'start_url' => '/',
        'background_color' => '#ffffff',
        'theme_color' => '#000000',
        'display' => 'standalone',
        'orientation'=> 'any',
        'status_bar'=> 'black',
        'icons' => [
            '128x128' => [
                'path' => '/images/app/the-128.png',
                'purpose' => 'any'
            ],
            '144x144' => [
                'path' => '/images/app/the-144.png',
                'purpose' => 'any'
            ],
        ],
        'splash' => [
            '640x1136' => '/images/app/sp-640.webp',
            '750x1334' => '/images/app/sp-750.webp',
            '828x1792' => '/images/app/sp-750.webp',
            '1125x2436' => '/images/app/sp-750.webp',
            '1242x2208' => '/images/app/sp-750.webp',
            '1242x2688' => '/images/app/sp-750.webp',
            '1536x2048' => '/images/app/sp-750.webp',
            '1668x2224' => '/images/app/sp-750.webp',
            '1668x2388' => '/images/app/sp-750.webp',
            '2048x2732' => '/images/app/sp-750.webp',
        ],

        'custom' => []
    ]
];
