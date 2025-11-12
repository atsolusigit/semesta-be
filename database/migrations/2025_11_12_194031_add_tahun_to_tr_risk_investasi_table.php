<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (!Schema::hasColumn('tr_risk_investasi', 'tahun')) {
                $table->integer('tahun')->nullable()->after('erkap_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('tr_risk_investasi', 'tahun')) {
                $table->dropColumn('tahun');
            }
        });
    }
};
