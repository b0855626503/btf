<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            // ขนาด/ชนิดคอลัมน์เลือกแบบปลอดภัย + nullable เผื่อใช้ร่วมกับข้อมูลเดิม

            if (!Schema::hasColumn('bank_payment', 'bankname')) {
                $table->string('bankname', 64)->nullable()->after('bank');
            }

            if (!Schema::hasColumn('bank_payment', 'channel')) {
                $table->string('channel', 32)->default('API')->after('bankname');
                $table->index('channel', 'bp_channel_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'tranferer')) {
                // ชื่อฟิลด์สะกดตามของเดิม "tranferer" (ไม่แก้สะกดเพื่อเข้ากับโค้ด)
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
                $table->tinyInteger('status')->default(0)->after('checkstatus'); // 0=init,1=done,2=clear,etc.
                $table->index('status', 'bp_status_idx');
            }
            if (!Schema::hasColumn('bank_payment', 'topupstatus')) {
                $table->char('topupstatus', 1)->default('N')->after('status'); // Y/N (ใช้ทำ CAS กันซ้ำ)
                $table->index('topupstatus', 'bp_topupstatus_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'source')) {
                $table->string('source', 64)->nullable()->after('topupstatus'); // manual/webhook/gateway...
                $table->index('source', 'bp_source_idx');
            }
            if (!Schema::hasColumn('bank_payment', 'source_ref')) {
                $table->string('source_ref', 128)->nullable()->after('source'); // ref จาก provider
                $table->index('source_ref', 'bp_source_ref_idx');
            }

            if (!Schema::hasColumn('bank_payment', 'user_create')) {
                $table->string('user_create', 128)->nullable()->after('source_ref');
                $table->index('user_create', 'bp_user_create_idx');
            }

            // แนะนำอย่างยิ่ง: ให้ unique ตามการใช้งาน firstOrNew(['tx_hash','bank'])
            // ถ้ามีทั้ง 2 คอลัมน์อยู่แล้ว และยังไม่มี unique index
            // หมายเหตุ: เช็คการมีอยู่ของ index ต้องใช้ doctrine/dbal; ถ้าไม่ได้ติดตั้ง ให้คอมเมนต์บรรทัดนี้ไว้ก่อน
            if (Schema::hasColumn('bank_payment', 'tx_hash') && Schema::hasColumn('bank_payment', 'bank')) {
                // ลองสร้าง unique ซ้ำจะ error ได้ ถ้าโปรเจ็กต์คุณยังไม่ได้ติดตั้ง doctrine/dbal
                // แนะนำให้ตั้งชื่อ index เฉพาะตัวและค่อย run ทีละสภาพแวดล้อม
                $table->unique(['tx_hash', 'bank'], 'bp_txhash_bank_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            // เอาออกแบบระวัง (อย่าลบข้อมูลสำคัญในโปรดักชัน ถ้าไม่จำเป็น)
            if (Schema::hasColumn('bank_payment', 'bankname'))    $table->dropColumn('bankname');
            if (Schema::hasColumn('bank_payment', 'channel'))     { $table->dropIndex('bp_channel_idx'); $table->dropColumn('channel'); }
            if (Schema::hasColumn('bank_payment', 'tranferer'))   { $table->dropIndex('bp_tranferer_idx'); $table->dropColumn('tranferer'); }
            if (Schema::hasColumn('bank_payment', 'detail'))      $table->dropColumn('detail');
            if (Schema::hasColumn('bank_payment', 'checking'))    $table->dropColumn('checking');
            if (Schema::hasColumn('bank_payment', 'checkstatus')) $table->dropColumn('checkstatus');
            if (Schema::hasColumn('bank_payment', 'status'))      { $table->dropIndex('bp_status_idx'); $table->dropColumn('status'); }
            if (Schema::hasColumn('bank_payment', 'topupstatus')) { $table->dropIndex('bp_topupstatus_idx'); $table->dropColumn('topupstatus'); }
            if (Schema::hasColumn('bank_payment', 'source'))      { $table->dropIndex('bp_source_idx'); $table->dropColumn('source'); }
            if (Schema::hasColumn('bank_payment', 'source_ref'))  { $table->dropIndex('bp_source_ref_idx'); $table->dropColumn('source_ref'); }
            if (Schema::hasColumn('bank_payment', 'user_create')) { $table->dropIndex('bp_user_create_idx'); $table->dropColumn('user_create'); }

            // unique index
            try { $table->dropUnique('bp_txhash_bank_unique'); } catch (\Throwable $e) {}
        });
    }
};
