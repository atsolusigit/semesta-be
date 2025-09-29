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
        Schema::create('tr_rcsa_rencana_risiko_list', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rcsa_id');

            $table->foreign('rcsa_id')->references('id')->on('tr_rcsa_header')->onDelete('cascade');
            $table->string('jenis_rencana_perlakuan_risiko')->nullable();
            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tr_rcsa_rencana_risiko_list');
    }
};
