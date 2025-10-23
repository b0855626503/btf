<?php

namespace Gametech\Core\Providers;

use Gametech\Core\Models\ConfigProxy;
use Gametech\Core\Observers\ConfigObserver;
use Illuminate\Support\ServiceProvider;

class CoreBootServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // รวมคอนฟิกตรงนี้ เพื่อให้ติดไปกับ config:cache เสมอ
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl.php', 'acl');

        // โหลด helpers ผ่าน composer.json จะดีกว่า (ดูหมายเหตุด้านล่าง)
        $helpers = __DIR__ . '/../Http/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        // ถ้าต้องใช้ EventServiceProvider เสมอ ให้ register ที่นี่ (ไม่หนัก)
        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void
    {
        // ต้องมั่นใจว่าถูกรันทุกรีเควสต์ ⇒ อยู่ใน provider ที่ไม่ deferrable
        ConfigProxy::observe(ConfigObserver::class);

        // $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'core');
    }
}
