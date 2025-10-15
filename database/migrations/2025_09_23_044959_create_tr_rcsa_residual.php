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
        Schema::create('tr_rcsa_residual', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rcsa_id');

            $table->foreign('rcsa_id')->references('id')->on('tr_rcsa_header')->onDelete('cascade');
            $table->integer('kuartal')->nullable();
            $table->integer('residual_skala_dampak')->nullable();
            $table->unsignedBigInteger('residual_nilai_dampak')->nullable();
            $table->integer('residual_skala_probabilitas')->nullable();
            $table->integer('residual_nilai_probabilitas')->nullable();
            $table->unsignedBigInteger('residual_eksposur_risiko_kuantitatif')->nullable();
            $table->text('residual_eksposur_risiko_kualitatif')->nullable();
            $table->integer('residual_skala_risiko')->nullable();
            $table->string('residual_level_risiko')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tr_rcsa_residual');
    }
};
