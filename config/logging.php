<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'errorlog', 'syslog'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/daily.log'),
            'level' => 'warning',
            'days' => 3,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/emergency.log'),
        ],

        'cashback' => [
            'driver' => 'single',
            'path' => storage_path('logs/cashback_create.log'),
            'level' => 'info'
        ],

        'slowlog' => [
            'driver' => 'single',
            'path' => storage_path('logs/slow-requests.log'),
            'level' => 'warning',
        ],

        'wildpay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wildpay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wildpay_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wildpay_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],
        'wildpay_cancel_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/cancel_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wildpay_cancel_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wildpay/cancel_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'sulifu_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sulifu/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'sulifu_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sulifu/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'sulifu_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sulifu/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'sulifu_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sulifu/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],
        'xpay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/xpay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'xpay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/xpay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'xpay_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/xpay/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'xpay_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/xpay/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'matepay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/matepay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'matepay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/matepay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],
        'gamelog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/gamelog/redis.log'),
            'level' => 'debug',
            'days' => 2,
        ],
        'wellpay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wellpay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wellpay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wellpay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wellpay_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wellpay/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'wellpay_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/wellpay/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'kingpay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kingpay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'kingpay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kingpay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'kingpay_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kingpay/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'kingpay_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kingpay/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],


        'cloudpay_deposit_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cloudpay/deposit_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'cloudpay_deposit_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cloudpay/deposit_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'cloudpay_withdraw_create' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cloudpay/withdraw_create.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'cloudpay_withdraw_callback' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cloudpay/withdraw_callback.log'),
            'level' => 'info',
            'days' => 14,
        ],
    ],

];
