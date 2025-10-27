<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) เพิ่มคอลัมน์ bank_code ถ้ายังไม่มี
        if (!Schema::hasColumn('bank_payment', 'bank_code')) {
            Schema::table('bank_payment', function (Blueprint $table) {
                $table->index('bank_code', 'idx_bank_payment_bank_code');
            });
        }

        // 2) อัปเดต bank_code โดยเทียบ bank_payment.bankname กับ banks.shortcode
        //
        // ปรับชื่อคอลัมน์ตาราง banks ได้ที่นี่ ถ้าโครงสร้างจริงต่างจากนี้:
        //   - banks.shortcode   = ค่าที่ยิงมาเทียบกับ bankname
        //   - banks.code        = ค่าที่ต้องการนำมาใส่ bank_payment.bank_code
        //
        // ใช้ normalize: lower + ตัดเว้นวรรค + ตัดจุด เพื่อกันสเปซ/สไตล์เขียนต่างกัน
        DB::statement("
            UPDATE bank_payment bp
            INNER JOIN banks b
                ON REPLACE(REPLACE(LOWER(TRIM(bp.bankname)), ' ', ''), '.', '') =
                   REPLACE(REPLACE(LOWER(TRIM(b.shortcode)), ' ', ''), '.', '')
            SET bp.bank_code = b.code
            WHERE bp.bankname IS NOT NULL AND bp.bankname <> ''
        ");

        // (ออปชัน) ถ้าอยากมี fallback เทียบกับชื่อเต็มภาษาไทย/อังกฤษ:
        // ปลดคอมเมนต์บล็อกนี้ถ้าในตาราง banks มีคอลัมน์ name_th / name_en
        /*
        DB::statement("
            UPDATE bank_payment bp
            INNER JOIN banks b
                ON REPLACE(REPLACE(LOWER(TRIM(bp.bankname)), ' ', ''), '.', '') IN (
                       REPLACE(REPLACE(LOWER(TRIM(b.shortcode)), ' ', ''), '.', ''),
                       REPLACE(REPLACE(LOWER(TRIM(b.name_th)),   ' ', ''), '.', ''),
                       REPLACE(REPLACE(LOWER(TRIM(b.name_en)),   ' ', ''), '.', '')
                   )
            SET bp.bank_code = b.code
            WHERE (bp.bank_code IS NULL OR bp.bank_code = '')
              AND bp.bankname IS NOT NULL AND bp.bankname <> ''
        ");
        */
    }

    public function down(): void
    {
        // ย้อนกลับแบบไม่ทำลายสคีมา: เคลียร์ค่าที่เราเติม
        if (Schema::hasColumn('bank_payment', 'bank_code')) {
            DB::statement("UPDATE bank_payment SET bank_code = NULL");
            // ถ้าต้องการลบคอลัมน์ออกจริง ๆ ให้ปลดคอมเมนต์ด้านล่าง
            /*
            Schema::table('bank_payment', function (Blueprint $table) {
                $table->dropIndex('idx_bank_payment_bank_code');
                $table->dropColumn('bank_code');
            });
            */
        }
    }
};
