<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mst_heatmap', function (Blueprint $table) {
            $table->id(); // UNSIGNED BIGINT
            $table->unsignedBigInteger('dampak');
            $table->unsignedBigInteger('kemungkinan'); // alias kemungkinan
            $table->integer('result');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('dampak')->references('id')->on('mst_heatmap_dampak')->onDelete('cascade');
            $table->foreign('kemungkinan')->references('id')->on('mst_heatmap_kemungkinan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_heatmap');
    }
};
