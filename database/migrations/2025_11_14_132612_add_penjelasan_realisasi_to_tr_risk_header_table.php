<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPenjelasanRealisasiToTrRiskHeaderTable extends Migration
{
    public function up()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->longText('penjelasan_realisasi')->nullable()->after('target_satu_tahun_notes');
        });
    }

    public function down()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn('penjelasan_realisasi');
        });
    }
}
