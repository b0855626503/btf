<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            $table->dateTime('date_topup')->nullable()->default(null)->change();
        });

        // ล้างค่าที่ไม่ใช่ NULL ให้เป็น NULL ด้วย
        DB::statement("UPDATE bank_payment 
                       SET date_topup = NULL 
                       WHERE date_topup = '0000-00-00 00:00:00' OR date_topup = ''");
    }

    public function down(): void
    {
        Schema::table('bank_payment', function (Blueprint $table) {
            // กรณีอยาก revert กลับให้ not null (ตามค่าเดิม)
            $table->dateTime('date_topup')->nullable(false)->default('0000-00-00 00:00:00')->change();
        });
    }
};
