<?php

use Illuminate\Support\Facades\Route;

Route::domain(config('app.admin_url').'.'.(is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {

    Route::group(['middleware' => ['web', 'admin', 'auth', '2fa'], 'namespace' => 'Gametech\TelegramBot\Http\Controllers'], function () {

        $route = ['name' => 'telegram_config', 'controller' => 'TelegramConfigController'];
        Route::group(['prefix' => $route['name']], function () use ($route) {
            Route::get('/', $route['controller'].'@index')->defaults('_config', [
                'view' => 'admin::module.'.$route['name'].'.index',
            ])->name('admin.'.$route['name'].'.index');

            Route::post('update/{id?}', $route['controller'].'@update')->name('admin.'.$route['name'].'.update');

        });

        $route = ['name' => 'telegram_customer_menu', 'controller' => 'TelegramCustomerMenuController'];
        Route::group(['prefix' => $route['name']], function () use ($route) {
            Route::get('/', $route['controller'].'@index')->defaults('_config', [
                'view' => 'admin::module.'.$route['name'].'.index',
            ])->name('admin.'.$route['name'].'.index');

            Route::post('create', $route['controller'].'@create')->name('admin.'.$route['name'].'.create');

            Route::post('loaddata', $route['controller'].'@loadData')->name('admin.'.$route['name'].'.loaddata');

            Route::post('edit', $route['controller'].'@edit')->name('admin.'.$route['name'].'.edit');

            Route::post('update/{id?}', $route['controller'].'@update')->name('admin.'.$route['name'].'.update');

        });

    });

});
