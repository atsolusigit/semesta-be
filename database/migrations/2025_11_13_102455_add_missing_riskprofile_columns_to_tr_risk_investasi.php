<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {

            if (!Schema::hasColumn('tr_risk_investasi', 'nilai')) {
                $table->decimal('nilai', 20, 2)->nullable()->after('unit_kerja_nama');
            }

            if (!Schema::hasColumn('tr_risk_investasi', 'with_sub_pekerjaan')) {
                $table->boolean('with_sub_pekerjaan')->nullable()->after('nilai');
            }

            if (!Schema::hasColumn('tr_risk_investasi', 'eksposure_kode_awal')) {
                $table->string('eksposure_kode_awal', 10)->nullable()->after('eksposure_ltmh_awal');
            }

            if (!Schema::hasColumn('tr_risk_investasi', 'eksposure_color_awal')) {
                $table->string('eksposure_color_awal', 7)->nullable()->after('eksposure_kode_awal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('tr_risk_investasi', 'nilai')) {
                $table->dropColumn('nilai');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'with_sub_pekerjaan')) {
                $table->dropColumn('with_sub_pekerjaan');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'eksposure_kode_awal')) {
                $table->dropColumn('eksposure_kode_awal');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'eksposure_color_awal')) {
                $table->dropColumn('eksposure_color_awal');
            }
        });
    }
};
