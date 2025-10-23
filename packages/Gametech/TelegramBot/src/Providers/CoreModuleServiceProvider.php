<?php

namespace Gametech\TelegramBot\Providers;

use Gametech\TelegramBot\Models\TelegramConfigProxy;
use Gametech\TelegramBot\Observers\TelegramConfigObserver;
use Konekt\Concord\BaseModuleServiceProvider;

class CoreModuleServiceProvider extends BaseModuleServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api-routes.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'telegrambot');

//        TelegramConfigProxy::observe(TelegramConfigObserver::class);

    }

    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/admin-menu.php', 'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php', 'acl'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/concord.php', 'concord'
        );

    }
}
