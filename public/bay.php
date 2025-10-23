<?php

use Illuminate\Support\Facades\Http;

$bank_number = '8039427223';
$bank_username = 'Pigmoo121314';
$bank_password = 'Aa121314@';

$url = 'http://203.146.127.170/~anan/bay/apibay.php';
$param = [
    'username' => $bank_username,
    'password' => $bank_password,
    'account' => $bank_number,
];

$response = rescue(function () use ($url,$param) {
    return Http::timeout(30)->asForm()->post($url,$param);

}, function ($e) {

    return $e;

}, true);

if ($response->failed()) {
    return false;
}

$return['body'] = $response->body();
$return['json'] = $response->json();
$return['successful'] = $response->successful();
$return['failed'] = $response->failed();
$return['clientError'] = $response->clientError();
$return['serverError'] = $response->serverError();

dd($return);

