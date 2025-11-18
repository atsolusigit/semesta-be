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
            // Menambahkan kolom penjelasan_realisasi setelah kolom realization_option_position
            $table->text('penjelasan_realisasi')->nullable()->after('realization_option_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            $table->dropColumn('penjelasan_realisasi');
        });
    }
};
