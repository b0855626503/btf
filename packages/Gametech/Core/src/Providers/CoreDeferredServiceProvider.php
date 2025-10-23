<?php

// CoreDeferredServiceProvider.php
namespace Gametech\Core\Providers;

use Gametech\Core\Core;
use Gametech\Core\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class CoreDeferredServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function provides(): array
    {
        return ['core', \Illuminate\Contracts\Debug\ExceptionHandler::class];
    }

    public function register(): void
    {
        $this->app->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, \Gametech\Core\Exceptions\Handler::class);

        // ให้คอนเทนเนอร์ประกอบ Core พร้อม dependencies
        $this->app->singleton('core', fn($app) => $app->make(\Gametech\Core\Core::class));
        $this->app->alias('core', \Gametech\Core\Core::class);

        \Illuminate\Foundation\AliasLoader::getInstance()->alias('Core', \Gametech\Core\Facades\Core::class);
    }

}
