<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            // Ubah kolom process_code ke integer
            $table->integer('process_code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('tr_risk_monthly', function (Blueprint $table) {
            // Kembalikan ke string jika di-rollback
            $table->string('process_code')->default(1)->change();
        });
    }
};
