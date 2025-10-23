<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('mongo:test', function () {
    $dsn = config('database.connections.mongodb.dsn') ?? env('MONGODB_DSN');
    $ns  = config('database.connections.mongodb.collection.game_logs', 'gamelogs.gamelog');
    $this->info('Using DSN: ' . $dsn);

    $manager = new MongoDB\Driver\Manager($dsn);
    $cursor = $manager->executeCommand('admin', new MongoDB\Driver\Command(['ping' => 1]));
    $this->info('Mongo ping ok: ' . json_encode($cursor->toArray()));
});


//Artisan::command('mongo:test', function () {
//    $dsn = config('database.mongodb.dsn');
//    $ns  = config('database.mongodb.collection.game_logs', 'game.game_logs');
//
//    $manager = new MongoDB\Driver\Manager($dsn);
//
//    // ping
//    $cmd = new MongoDB\Driver\Command(['ping' => 1]);
//    $cursor = $manager->executeCommand('admin', $cmd);
//    $this->info('Mongo ping ok: ' . json_encode($cursor->toArray()));
//
//    // ลองเขียน/อ่านเล็กน้อย
//    $bulk = new MongoDB\Driver\BulkWrite();
//    $bulk->insert(['hello' => 'world', 'ts' => new MongoDB\BSON\UTCDateTime()]);
//    $manager->executeBulkWrite($ns, $bulk);
//    $this->info("Write to {$ns} ok");
//});

