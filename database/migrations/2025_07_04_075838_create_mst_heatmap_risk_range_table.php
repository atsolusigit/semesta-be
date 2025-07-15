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
         Schema::create('mst_heatmap_risk_range', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->integer('start');
            $table->integer('end');
            $table->string('color', 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_heatmap_risk_range');
    }
};
