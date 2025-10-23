<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//    return redirect()->route('customer.session.index');
// });

//Route::get('/', 'PageController@index')->defaults('_config', [
//    'view' => 'module.index',
//])->name('welcome.index');




$domain = config('app.admin_url') === ''
    ? (config('app.admin_domain_url') ?? config('app.domain_url'))
    : config('app.admin_url').'.'.(config('app.admin_domain_url') ?? config('app.domain_url'));

Route::domain($domain)->group(function () {
    Route::get('/status', function () {
        return view('status.index');
    })->name('status.index');
});
