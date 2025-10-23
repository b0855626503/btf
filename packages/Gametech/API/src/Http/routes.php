<?php

Route::domain('api.'.(is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {

    Route::prefix('api')->group(function () {

        Route::group(['namespace' => 'Gametech\API\Http\Controllers', 'middleware' => ['api', 'whitelist']], function () {

            include 'routessub.php';

        });

    });

    Route::prefix('api')->group(function () {

        Route::group(['namespace' => 'Gametech\API\Http\Controllers', 'middleware' => ['api']], function () {
            Route::post('app/login', 'AnnounceController@AppLogin')->name('api.app.login');
            Route::post('broadcast', 'AnnounceController@broadcast')->name('api.app.baordcast');
            Route::get('announce', 'AnnounceController@index');

            Route::post('ttb/{mobile}/webhook_superrich', 'AnnounceController@ttb_superrich');
            Route::post('ttb/{mobile}/webhook_superrich69', 'AnnounceController@ttb_superrich69');

            Route::post('scb/{mobile}/webhook_superrich', 'AnnounceController@scb_superrich');
            Route::post('scb/{mobile}/webhook_superrich69', 'AnnounceController@scb_superrich69');
	        
	        Route::get('/games/{type}/{provider}', 'GameController@getGames')->name('api.games.get');
	        Route::get('/list/provider/{type}', 'GameController@getProviders')->name('api.providers.get');
			
            include 'routeshotdog.php';

        });

    });

});

Route::domain('api2.'.(is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {

    Route::prefix('api')->group(function () {

        Route::group(['namespace' => 'Gametech\API\Http\Controllers', 'middleware' => ['api', 'whitelist']], function () {

            include 'routessub.php';

        });

    });

   

});
