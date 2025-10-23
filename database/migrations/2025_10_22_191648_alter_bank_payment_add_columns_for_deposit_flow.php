<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            // ให้สคริปต์ “ปลอดภัยซ้ำรันได้” โดยเช็คก่อนว่ามีคอลัมน์หรือยัง

            if (!Schema::hasColumn('bank_payment', 'bankname')) {
                $table->string('bankname', 64)->nullable()->after('bank');
            }

            if (!Schema::hasColumn('bank_payment', 'channel')) {
                $table->string('channel', 32)->default('API')->after('bankname');
                $table->index('channel', 'bp_channel_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'tranferer')) {
                // คงชื่อสะกดตามเดิมในระบบ (tranferer)
                $table->string('tranferer', 64)->nullable()->after('value');
                $table->index('tranferer', 'bp_tranferer_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'detail')) {
                $table->text('detail')->nullable()->after('tranferer');
            }

            if (!Schema::hasColumn('bank_payment', 'checking')) {
                $table->char('checking', 1)->default('N')->after('detail'); // Y/N
            }
            if (!Schema::hasColumn('bank_payment', 'checkstatus')) {
                $table->char('checkstatus', 1)->default('N')->after('checking'); // Y/N
            }
            if (!Schema::hasColumn('bank_payment', 'status')) {
                $table->tinyInteger('status')->default(0)->after('checkstatus'); // 0=init,1=done,2=clear...
                $table->index('status', 'bp_status_idx');
            }
            if (!Schema::hasColumn('bank_payment', 'topupstatus')) {
                $table->char('topupstatus', 1)->default('N')->after('status'); // Y/N
                $table->index('topupstatus', 'bp_topupstatus_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'source')) {
                $table->string('source', 64)->nullable()->after('topupstatus');
                $table->index('source', 'bp_source_idx');
            }
            if (!Schema::hasColumn('bank_payment', 'source_ref')) {
                $table->string('source_ref', 128)->nullable()->after('source');
                $table->index('source_ref', 'bp_source_ref_idx');
            }

            // ในโมเดลมี create_by แล้ว—ถ้า column ยังไม่มี ให้เพิ่ม
            if (!Schema::hasColumn('bank_payment', 'create_by')) {
                $table->string('create_by', 128)->nullable()->after('source_ref');
                $table->index('create_by', 'bp_create_by_idx');
            }

            // เผื่อใช้งาน tx_hash + bank เป็นตัวกันซ้ำ
            if (Schema::hasColumn('bank_payment', 'tx_hash') && Schema::hasColumn('bank_payment', 'bank')) {
                $table->unique(['tx_hash', 'bank'], 'bp_txhash_bank_unique');
            }

            // ปรับชนิด time/checktime ให้เป็น datetime ถ้ายังไม่ได้เป็น
            if (!Schema::hasColumn('bank_payment', 'time')) {
                $table->dateTime('time')->nullable()->after('txid');
            }
            if (!Schema::hasColumn('bank_payment', 'checktime')) {
                $table->dateTime('checktime')->nullable()->after('time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            // rollback แบบปลอดภัย
            $drops = [
                ['index',  'bp_channel_idx',     'channel'],
                ['index',  'bp_tranferer_idx',   'tranferer'],
                ['index',  'bp_status_idx',      'status'],
                ['index',  'bp_topupstatus_idx', 'topupstatus'],
                ['index',  'bp_source_idx',      'source'],
                ['index',  'bp_source_ref_idx',  'source_ref'],
                ['index',  'bp_create_by_idx',   'create_by'],
            ];
            foreach ($drops as [$type, $idxName, $col]) {
                try { $table->dropIndex($idxName); } catch (\Throwable $e) {}
            }

            try { $table->dropUnique('bp_txhash_bank_unique'); } catch (\Throwable $e) {}

            foreach (['bankname','channel','tranferer','detail','checking','checkstatus','status','topupstatus','source','source_ref','create_by','time','checktime'] as $col) {
                if (Schema::hasColumn('bank_payment', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
