<?php

namespace Gametech\TelegramBot\Providers;

use Gametech\TelegramBot\Models\TelegramConfigProxy;
use Gametech\TelegramBot\Observers\TelegramConfigObserver;
use Illuminate\Support\ServiceProvider;

class TelegramBotServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        TelegramConfigProxy::observe(TelegramConfigObserver::class);

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admin');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api-routes.php');

    }

    /**
     * Register services.
     *
     * @return void
     */
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

//        $this->mergeConfigFrom(
//            dirname(__DIR__).'/Config/concord.php', 'concord'
//        );

    }
}
