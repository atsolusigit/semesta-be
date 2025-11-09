<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tr_risk_investasi', 'erkap_list_risk_json')) {
            Schema::table('tr_risk_investasi', function (Blueprint $t) {
                $t->json('erkap_list_risk_json')->nullable()->after('mitigasi_risiko');
            });
        }

        if (!Schema::hasColumn('tr_risk_investasi', 'eksposure_kode_akhir')) {
            Schema::table('tr_risk_investasi', function (Blueprint $t) {
                $t->string('eksposure_kode_akhir', 10)->nullable()->after('eksposure_ltmh_akhir');
            });
        }

        if (!Schema::hasColumn('tr_risk_investasi', 'eksposure_color_akhir')) {
            Schema::table('tr_risk_investasi', function (Blueprint $t) {
                $t->string('eksposure_color_akhir', 7)->nullable()->after('eksposure_kode_akhir');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $t) {
            if (Schema::hasColumn('tr_risk_investasi', 'erkap_list_risk_json')) {
                $t->dropColumn('erkap_list_risk_json');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'eksposure_kode_akhir')) {
                $t->dropColumn('eksposure_kode_akhir');
            }
            if (Schema::hasColumn('tr_risk_investasi', 'eksposure_color_akhir')) {
                $t->dropColumn('eksposure_color_akhir');
            }
        });
    }
};
