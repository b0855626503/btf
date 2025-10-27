<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) เติมจาก checktime ก่อน (แม่นสุด เพราะเป็นเวลา system แปลงมาจาก UNIX)
//        DB::statement("
//            UPDATE bank_payment
//            SET time = checktime
//            WHERE time IS NULL AND checktime IS NOT NULL
//        ");

        // 2) ยัง NULL อยู่ ให้ใช้ date_create เป็น fallback
        DB::statement("
            UPDATE bank_payment
            SET time = date_create
            WHERE time IS NULL AND date_create IS NOT NULL
        ");
    }

    public function down(): void
    {
        // ย้อนกลับยาก (ข้อมูลเดิมหายไปแล้ว) — เลือกล้างเฉพาะที่เราตั้งค่า
        // ถ้าไม่ต้องการล้าง ให้ปล่อยว่างได้
        // DB::statement("UPDATE bank_payment SET time = NULL");
    }
};
