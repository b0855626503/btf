<?php

namespace Gametech\TelegramBot\Providers;

use Gametech\TelegramBot\Models\BroadcastSession;
use Gametech\TelegramBot\Models\TelegramAdmin;
use Gametech\TelegramBot\Models\TelegramConfig;
use Gametech\TelegramBot\Models\TelegramConfigProxy;
use Gametech\TelegramBot\Models\TelegramCustomerMenu;
use Gametech\TelegramBot\Models\TelegramWelcomeMessage;
use Gametech\TelegramBot\Observers\TelegramConfigObserver;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        TelegramConfig::class,
        TelegramAdmin::class,
        BroadcastSession::class,
        TelegramCustomerMenu::class,
        TelegramWelcomeMessage::class,
    ];

}
