<?php
$apiRoute = config('gametech.api_url') ?? 'api';


Route::domain("$apiRoute." . (is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {

    Route::prefix('api')->group(function () {

        Route::group(['namespace' => 'Gametech\Payment\Http\Controllers', 'middleware' => ['api']], function () {

            Route::post('wildpay/deposit/callback', 'WildPayController@deposit_callback')->name('api.wildpay.deposit.callback');
            Route::post('wildpay/withdraw/callback', 'WildPayController@withdraw_callback')->name('api.wildpay.withdraw.callback');

//            Route::post('payment/deposit/callback/usd', 'SulifuPayController@deposit_callback_usd')->name('api.payment.deposit.callback.usd');
//            Route::post('payment/withdraw/callback/usd', 'SulifuPayController@withdraw_callback_usd')->name('api.payment.withdraw.callback.usd');

//            Route::post('payment/deposit/callback/khr', 'SulifuPayController@deposit_callback_khr')->name('api.payment.deposit.callback.khr');
//            Route::post('payment/withdraw/callback/khr', 'SulifuPayController@withdraw_callback_khr')->name('api.payment.withdraw.callback.khr');

//	        Route::post('payment/deposit/callback', 'MatePayController@deposit_callback')->name('api.payment.deposit.callback');
//	        Route::post('payment/withdraw/callback', 'MatePayController@withdraw_callback')->name('api.payment.withdraw.callback');

            Route::post('kingpay/deposit/callback', 'KingPayController@deposit_callback')->name('api.kingpay.deposit.callback');
            Route::post('kingpay/withdraw/callback', 'KingPayController@withdraw_callback')->name('api.kingpay.withdraw.callback');

            Route::post('wellpay/deposit/callback', 'WellPayController@deposit_callback')->name('api.wellpay.deposit.callback');
            Route::post('wellpay/withdraw/callback', 'WellPayController@withdraw_callback')->name('api.wellpay.withdraw.callback');

        });

    });

});

$domain = config('app.user_url') === ''
    ? (config('app.user_domain_url') ?? config('app.domain_url'))
    : config('app.user_url') . '.' . (config('app.user_domain_url') ?? config('app.domain_url'));

Route::domain($domain)->group(function () {
    Route::middleware('web')->group(function () {

        Route::prefix('member')->group(function () {

            Route::middleware(['customer', 'authuser', 'online'])->group(function () {
                // ไว้สำหรับ future route ถ้าจะเพิ่ม

                Route::get('wildpay/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\WildPayController@checkStatus')->name('api.wildpay.deposit.status');
                Route::post('wildpay/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\WildPayController@expire')->name('api.wildpay.deposit.expire');
                Route::post('wildpay/deposit/create', 'Gametech\Payment\Http\Controllers\WildPayController@deposit')->name('api.wildpay.deposit');
                Route::get('wildpay/qrcode/{id}', 'Gametech\Payment\Http\Controllers\WildPayController@index')->name('api.wildpay.index');

                Route::get('kingpay/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\KingPayController@checkStatus')->name('api.kingpay.deposit.status');
                Route::post('kingpay/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\KingPayController@expire')->name('api.kingpay.deposit.expire');
                Route::post('kingpay/deposit/create', 'Gametech\Payment\Http\Controllers\KingPayController@deposit')->name('api.kingpay.deposit');
                Route::get('kingpay/qrcode/{id}', 'Gametech\Payment\Http\Controllers\KingPayController@index')->name('api.kingpay.index');

                Route::get('wellpay/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\WellPayController@checkStatus')->name('api.wellpay.deposit.status');
                Route::post('wellpay/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\WellPayController@expire')->name('api.wellpay.deposit.expire');
                Route::post('wellpay/deposit/create', 'Gametech\Payment\Http\Controllers\WellPayController@deposit')->name('api.wellpay.deposit');
                Route::get('wellpay/qrcode/{id}', 'Gametech\Payment\Http\Controllers\WellPayController@index')->name('api.wellpay.index');


//	            Route::get('payment/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\MatePayController@checkStatus')->name('api.payment.deposit.status');
//	            Route::post('payment/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\MatePayController@expire')->name('api.payment.deposit.expire');
//	            Route::post('payment/deposit/create', 'Gametech\Payment\Http\Controllers\MatePayController@deposit')->name('api.payment.deposit');
//	            Route::get('payment/qrcode/{id}', 'Gametech\Payment\Http\Controllers\MatePayController@index')->name('api.payment.index');

//                                Route::get('payment/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\WildPayController@checkStatus')->name('api.payment.deposit.status');
//                                Route::post('payment/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\WildPayController@expire')->name('api.payment.deposit.expire');
//                                Route::post('payment/deposit/create', 'Gametech\Payment\Http\Controllers\WildPayController@deposit')->name('api.payment.deposit');
//                                Route::get('payment/qrcode/{id}', 'Gametech\Payment\Http\Controllers\WildPayController@index')->name('api.payment.index');

//                Route::get('payment/deposit/status/{txid}', 'Gametech\Payment\Http\Controllers\SulifuPayController@checkStatus')->name('api.payment.deposit.status');
//                Route::post('payment/deposit/expire/{txid}', 'Gametech\Payment\Http\Controllers\SulifuPayController@expire')->name('api.payment.deposit.expire');
//                Route::post('payment/deposit/create/usd', 'Gametech\Payment\Http\Controllers\SulifuPayController@deposit_usd')->name('api.payment.deposit.usd');
//                Route::post('payment/deposit/create/khr', 'Gametech\Payment\Http\Controllers\SulifuPayController@deposit_khr')->name('api.payment.deposit.khr');
//                Route::get('payment/qrcode/{id}', 'Gametech\Payment\Http\Controllers\SulifuPayController@index')->name('api.payment.index');

            });
        });
    });
});
