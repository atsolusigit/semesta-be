<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameProbabilitasToKemungkinanInMstHeatmap extends Migration
{
    public function up()
    {
<<<<<<< HEAD
        // Schema::table('mst_heatmap', function (Blueprint $table) {
        //     $table->renameColumn('probabilitas', 'kemungkinan');
        // });
=======
        Schema::table('mst_heatmap', function (Blueprint $table) {
            $table->renameColumn('probabilitas', 'kemungkinan');
        });
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    }

    public function down()
    {
        Schema::table('mst_heatmap', function (Blueprint $table) {
            $table->renameColumn('kemungkinan', 'probabilitas');
        });
    }
}
