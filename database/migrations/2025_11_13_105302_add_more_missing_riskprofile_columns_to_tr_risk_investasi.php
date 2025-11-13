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
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (!Schema::hasColumn('tr_risk_investasi', 'risk_kategori_id')) {
                $table->unsignedBigInteger('risk_kategori_id')->nullable()->after('kategori_risiko');
            }

            if (!Schema::hasColumn('tr_risk_investasi', 'capex_sub_id')) {
                $table->unsignedBigInteger('capex_sub_id')->nullable()->after('with_sub_pekerjaan');
            }

            if (!Schema::hasColumn('tr_risk_investasi', 'nama_sub_pekerjaan')) {
                $table->string('nama_sub_pekerjaan', 255)->nullable()->after('capex_sub_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('tr_risk_investasi', 'risk_kategori_id')) {
                $table->dropColumn('risk_kategori_id');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'capex_sub_id')) {
                $table->dropColumn('capex_sub_id');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'nama_sub_pekerjaan')) {
                $table->dropColumn('nama_sub_pekerjaan');
            }
        });
    }
};
