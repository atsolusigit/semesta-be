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
         Schema::table('tr_risk_header', function (Blueprint $table) {
            // Mengubah dari double ke text agar bisa menerima angka dan huruf
            $table->text('target_quantitative_satu_tahun')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('tr_risk_header', function (Blueprint $table) {
            // Kembalikan ke tipe double jika rollback
            $table->double('target_quantitative_satu_tahun', 15, 2)->nullable()->change();
        });
    }
};
