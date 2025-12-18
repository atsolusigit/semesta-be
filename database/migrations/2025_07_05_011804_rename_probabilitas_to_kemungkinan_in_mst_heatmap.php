<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameProbabilitasToKemungkinanInMstHeatmap extends Migration
{
    public function up()
    {
        Schema::table('mst_heatmap', function (Blueprint $table) {
            $table->renameColumn('probabilitas', 'kemungkinan');
        });
    }

    public function down()
    {
        Schema::table('mst_heatmap', function (Blueprint $table) {
            $table->renameColumn('kemungkinan', 'probabilitas');
        });
    }
}
