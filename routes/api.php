<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

//Route::get('/status', fn() => view('status.index'));


$apiRoute = config('gametech.api_url') ?? 'api';


Route::domain("$apiRoute." . (is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {



        Route::group(['middleware' => ['api']], function () {

            Route::post('/track/event', 'TrackController@event')->name('api.track.event');
            Route::post('/track/presence', 'TrackController@presence')->name('api.track.presence');


            Route::post('/daily-report', 'ReportController@daily')->name('api.daily.report');

        });


});

