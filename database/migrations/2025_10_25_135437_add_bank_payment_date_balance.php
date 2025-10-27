<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- ปรับ status: default = 1, comment 1=deposit, 2=withdraw
        // ใช้ SQL ตรงเพื่อเลี่ยง DBAL
        DB::statement("
            ALTER TABLE `bankaccount`
            MODIFY `status` TINYINT UNSIGNED NOT NULL DEFAULT 1
            COMMENT '1=deposit, 2=withdraw'
        ");

        // --- date_create → DATETIME NULL DEFAULT NULL
        if (Schema::hasColumn('bankaccount', 'date_create')) {
            DB::statement("
                ALTER TABLE `bankaccount`
                MODIFY `date_create` DATETIME NULL DEFAULT NULL
            ");
        } else {
            Schema::table('bankaccount', function (Blueprint $table) {
                $table->dateTime('date_create')->nullable()->default(null);
            });
        }

        // --- date_update → DATETIME NULL DEFAULT NULL
        if (Schema::hasColumn('bankaccount', 'date_update')) {
            DB::statement("
                ALTER TABLE `bankaccount`
                MODIFY `date_update` DATETIME NULL DEFAULT NULL
            ");
        } else {
            Schema::table('bankaccount', function (Blueprint $table) {
                $table->dateTime('date_update')->nullable()->default(null);
            });
        }

        // --- เพิ่ม balance ถ้ายังไม่มี
        if (!Schema::hasColumn('bankaccount', 'balance')) {
            Schema::table('bankaccount', function (Blueprint $table) {
                $table->decimal('balance', 18, 2)
                    ->default(0)
                    ->after('status'); // ย้ายตำแหน่งตามต้องการ
            });
        }
    }

    public function down(): void
    {
        // เอา balance ออก (ถ้าต้องการ rollback ให้เกลี้ยง)
        if (Schema::hasColumn('bankaccount', 'balance')) {
            Schema::table('bankaccount', function (Blueprint $table) {
                $table->dropColumn('balance');
            });
        }

        // ย้อน date_create/date_update กลับเป็น TIMESTAMP NULL (ปรับตามของเดิมได้)
        if (Schema::hasColumn('bankaccount', 'date_create')) {
            DB::statement("
                ALTER TABLE `bankaccount`
                MODIFY `date_create` TIMESTAMP NULL DEFAULT NULL
            ");
        }
        if (Schema::hasColumn('bankaccount', 'date_update')) {
            DB::statement("
                ALTER TABLE `bankaccount`
                MODIFY `date_update` TIMESTAMP NULL DEFAULT NULL
            ");
        }

        // status: เอา comment ออก แต่คง default=1 ไว้ (ถ้าอยากเอา default ออก ให้ปรับเองได้)
        DB::statement("
            ALTER TABLE `bankaccount`
            MODIFY `status` TINYINT UNSIGNED NOT NULL DEFAULT 1
        ");
    }
};
