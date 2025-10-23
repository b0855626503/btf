<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/


//Broadcast::channel('Gametech.Member.Models.Member.{code}', function ($user, $code) {
//    return (int) $user->code === (int) $code;
//});

Broadcast::channel(env('APP_NAME').'_members.{id}', function ($user, $id) {
    return (int) $user->code === (int) $id;
});

Broadcast::channel(env('APP_NAME').'_admins.{id}', function ($user, $id) {
    return (int) $user->code === (int) $id;
});


Broadcast::channel(env('APP_NAME').'_events', function ($user) {
    return true;
});
//
