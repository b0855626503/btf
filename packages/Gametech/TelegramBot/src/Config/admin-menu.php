<?php

return [
    [
        'key' => 'telegrambot',
        'name' => 'Telegram Bot',
        'route' => 'admin.telegram_config.index',
        'sort' => 85,
        'icon-class' => 'fa-gamepad',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1,
    ],
    [
        'key' => 'telegrambot.telegram_config',
        'name' => 'ตั้งค่า',
        'route' => 'admin.telegram_config.index',
        'sort' => 1,
        'icon-class' => 'fa-gamepad',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1,
    ],
    [
        'key' => 'telegrambot.telegram_customer_menu',
        'name' => 'เมนูลูกค้า',
        'route' => 'admin.telegram_customer_menu.index',
        'sort' => 2,
        'icon-class' => 'fa-gamepad',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1,
    ],
];
