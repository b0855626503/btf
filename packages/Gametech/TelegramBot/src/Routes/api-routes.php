<?php

use Illuminate\Support\Facades\Route;

Route::domain('api.'.(is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {

    Route::prefix('api')->group(function () {

        Route::group(['namespace' => 'Gametech\TelegramBot\Http\Controllers', 'middleware' => ['api']], function () {

            Route::post('telegram/webhook', 'TelegramWebhookController@handle');

        });

    });

});
