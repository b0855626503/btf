<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) เพิ่มคอลัมน์ชั่วคราวเป็น DATETIME
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dateTime('time_dt')->nullable()->after('time');
            $table->dateTime('checktime_dt')->nullable()->after('checktime');
        });

        // 2) แปลง/ย้ายข้อมูลเดิมลงคอลัมน์ใหม่
        // - time (VARCHAR) → DATETIME
        //   หมายเหตุ: ถ้า format ไม่ใช่ 'YYYY-MM-DD HH:MM:SS' ให้ปรับเป็น STR_TO_DATE ตามจริง
        DB::statement("
            UPDATE bank_payment
            SET time_dt = CASE
                WHEN time IS NULL
                  OR time = ''
                  OR time = '0000-00-00 00:00:00'
                THEN NULL
                ELSE CAST(time AS DATETIME)
            END
        ");

        // - checktime (BIGINT; อาจเป็นวินาทีหรือมิลลิวินาที) → DATETIME
        DB::statement("
            UPDATE bank_payment
            SET checktime_dt = CASE
                WHEN checktime IS NULL OR checktime <= 0 THEN NULL
                WHEN checktime > 9999999999 THEN FROM_UNIXTIME(checktime / 1000) -- ms
                ELSE FROM_UNIXTIME(checktime)                                    -- sec
            END
        ");

        // 3) ลบคอลัมน์เดิม แล้วรีเนมคอลัมน์ใหม่ให้เป็นชื่อเดิม
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dropColumn('time');
            $table->dropColumn('checktime');
        });

        Schema::table('bank_payment', function (Blueprint $table) {
            $table->renameColumn('time_dt', 'time');
            $table->renameColumn('checktime_dt', 'checktime');
        });

        // 4) (ออปชัน) ทำดัชนี ถ้าใช้กรอง/เรียงบ่อย
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->index('time', 'idx_bank_payment_time');
            $table->index('checktime', 'idx_bank_payment_checktime');
        });
    }

    public function down(): void
    {
        // ย้อนกลับ: กลับไปเป็น time (VARCHAR) และ checktime (BIGINT)
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->string('time_old', 19)->nullable()->after('time');          // 'YYYY-MM-DD HH:MM:SS'
            $table->unsignedBigInteger('checktime_old')->nullable()->after('checktime');
        });

        // แปลงกลับ DATETIME → VARCHAR(19) และ → UNIX 秒
        DB::statement("
            UPDATE bank_payment
            SET time_old = CASE
                WHEN time IS NULL THEN NULL
                ELSE DATE_FORMAT(time, '%Y-%m-%d %H:%i:%s')
            END
        ");
        DB::statement("
            UPDATE bank_payment
            SET checktime_old = CASE
                WHEN checktime IS NULL THEN NULL
                ELSE UNIX_TIMESTAMP(checktime)
            END
        ");

        // ลบดัชนีใหม่
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dropIndex('idx_bank_payment_time');
            $table->dropIndex('idx_bank_payment_checktime');
        });

        // ลบคอลัมน์ใหม่ แล้วรีเนมกลับเป็นชื่อเดิม
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dropColumn('time');
            $table->dropColumn('checktime');
        });

        Schema::table('bank_payment', function (Blueprint $table) {
            $table->renameColumn('time_old', 'time');
            $table->renameColumn('checktime_old', 'checktime');
        });
    }
};
