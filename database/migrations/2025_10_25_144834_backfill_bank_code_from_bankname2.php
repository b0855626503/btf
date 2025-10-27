<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ เพิ่มคอลัมน์ account_code ถ้ายังไม่มี
        if (!Schema::hasColumn('bank_payment', 'account_code')) {
            Schema::table('bank_payment', function (Blueprint $table) {
                $table->unsignedBigInteger('account_code')->nullable()->after('bank');
                $table->index('account_code', 'idx_bank_payment_account_code');
            });
        }

        // ✅ ใช้ REGEXP_SUBSTR (MySQL 8.x)
        try {
            DB::statement("
                UPDATE bank_payment bp
                INNER JOIN bankaccount ba
                    ON REGEXP_SUBSTR(bp.bank, '[0-9]{6,}') = ba.accountno
                SET bp.account_code = ba.code
                WHERE bp.bank IS NOT NULL AND bp.bank <> ''
            ");
        } catch (\Throwable $e) {
            // ✅ fallback: สำหรับ MySQL 5.7 ที่ไม่มี REGEXP_SUBSTR()
            DB::statement("
                UPDATE bank_payment bp
                INNER JOIN bankaccount ba
                    ON SUBSTRING_INDEX(bp.bank, '_', -1) = ba.accountno
                SET bp.account_code = ba.code
                WHERE bp.bank IS NOT NULL AND bp.bank <> ''
            ");
        }
    }

    public function down(): void
    {
        // rollback: เคลียร์ค่า แต่ไม่ลบคอลัมน์
        if (Schema::hasColumn('bank_payment', 'account_code')) {
            DB::statement("UPDATE bank_payment SET account_code = NULL");
            // ถ้าต้องการลบจริง ๆ ให้ปลดคอมเมนต์ข้างล่าง
            /*
            Schema::table('bank_payment', function (Blueprint $table) {
                $table->dropIndex('idx_bank_payment_account_code');
                $table->dropColumn('account_code');
            });
            */
        }
    }
};
