<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateGameLogIndexes extends Command
{
    protected $signature = 'gamelog:create-indexes';
    protected $description = 'Create MongoDB indexes for gamelog collection';

    public function handle()
    {
        $col = DB::connection('mongodb')->collection('gamelog');

        $this->info('Creating indexes for gamelog...');

        // TTL index
        $col->raw(function($c) {
            return $c->createIndex(['expireAt' => 1], [
                'expireAfterSeconds' => 0,
                'name' => 'ttl_expireAt'
            ]);
        });

        // Unique transaction IDs
        $col->raw(fn($c) => $c->createIndex(['input.id' => 1], [
            'unique' => true,
            'name'   => 'uniq_inputId'
        ]));
        $col->raw(fn($c) => $c->createIndex(['input.txns.id' => 1], [
            'unique' => true,
            'sparse' => true,
            'name'   => 'uniq_txnId'
        ]));

        // Lookup by user
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'game_user' => 1, 'created_at' => -1],
            ['name' => 'byCompany_user_createdAt_desc']
        ));

        // Lookup by method
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'method' => 1, 'created_at' => -1],
            ['name' => 'byCompany_method_createdAt_desc']
        ));

        // Con1 + Con2
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'method' => 1, 'con_1' => 1, 'con_2' => 1],
            ['name' => 'byCompany_method_con1_con2']
        ));

        // By response
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'response' => 1, 'created_at' => -1],
            ['name' => 'byCompany_response_createdAt']
        ));

        // By product/game
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'input.productId' => 1, 'created_at' => -1],
            ['name' => 'byCompany_product_createdAt']
        ));
        $col->raw(fn($c) => $c->createIndex(
            ['company' => 1, 'input.gameCode' => 1, 'created_at' => -1],
            ['name' => 'byCompany_gameCode_createdAt']
        ));

        // Output id
        $col->raw(fn($c) => $c->createIndex(
            ['output.id' => 1],
            ['name' => 'byOutputId']
        ));

        $this->info('All indexes created successfully.');
    }
}
