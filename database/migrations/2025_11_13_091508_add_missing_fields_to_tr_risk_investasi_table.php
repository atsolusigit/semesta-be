<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (!Schema::hasColumn('tr_risk_investasi', 'tahun')) {
                $table->integer('tahun')->nullable()->after('erkap_id');
            }
            if (!Schema::hasColumn('tr_risk_investasi', 'unit_kerja_id')) {
                $table->unsignedBigInteger('unit_kerja_id')->nullable()->after('tahun');
            }
            if (!Schema::hasColumn('tr_risk_investasi', 'unit_kerja_nama')) {
                $table->string('unit_kerja_nama')->nullable()->after('unit_kerja_id');
            }
            if (!Schema::hasColumn('tr_risk_investasi', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('tr_risk_investasi', 'synced_at')) {
                $table->dropColumn('synced_at');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'unit_kerja_nama')) {
                $table->dropColumn('unit_kerja_nama');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'unit_kerja_id')) {
                $table->dropColumn('unit_kerja_id');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'tahun')) {
                $table->dropColumn('tahun');
            }
        });
    }
};
