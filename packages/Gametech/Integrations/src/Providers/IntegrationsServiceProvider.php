<?php

namespace Gametech\Integrations\Providers;

use Gametech\Integrations\Services\WithdrawOrchestrator;
use Gametech\Integrations\Support\ConfigStore;
use Illuminate\Support\ServiceProvider;
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\AclAuthorizer; // ← แก้ namespace ให้ตรงกับตำแหน่งไฟล์จริง
use Gametech\Integrations\Services\DepositOrchestrator;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // autoload config
        $this->mergeConfigFrom(__DIR__.'/../Config/gametech_providers.php', 'integrations.providers');
        $this->mergeConfigFrom(__DIR__.'/../Config/services.integrations.php', 'integrations.services');
        $this->mergeConfigFrom(__DIR__.'/../Config/flows.php', 'flows');
        $this->mergeConfigFrom(__DIR__.'/../Config/access.php', 'access');

        // ProviderManager
        $this->app->singleton(ProviderManager::class, function ($app) {
            $map = config('integrations.providers.map', []);
            return new ProviderManager($map);
        });
        $this->app->when(ProviderManager::class)
            ->needs('$map')
            ->give(fn () => config('integrations.providers.map', []));

        // ACL
        $this->app->singleton(AclAuthorizer::class, fn () => new AclAuthorizer());

        // ConfigStore
        $this->app->singleton(ConfigStore::class, fn () => new ConfigStore());

        // DepositOrchestrator
        $this->app->singleton(DepositOrchestrator::class, function ($app) {
            return new DepositOrchestrator(
                $app->make(ProviderManager::class),
                $app->make(AclAuthorizer::class),
                $app->make(\Gametech\Payment\Repositories\BankPaymentRepository::class),
                $app->make(ConfigStore::class),
            );
        });

        // WithdrawOrchestrator
        $this->app->singleton(WithdrawOrchestrator::class, function ($app) {
            return new WithdrawOrchestrator(
                $app->make(ProviderManager::class),
                $app->make(AclAuthorizer::class),
                $app->make(\Gametech\Payment\Repositories\WithdrawRepository::class),
                $app->make(ConfigStore::class),
            );
        });
    }

    public function boot(): void
    {
        // ถ้ามี publish/route/views เพิ่มในอนาคต ใส่ในนี้
    }
}
