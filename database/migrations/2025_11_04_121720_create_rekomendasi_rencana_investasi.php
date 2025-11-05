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
        Schema::create('rekomendasi_rencana_investasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('erkap_id')->nullable()->comment('ID Rencana investasi dari aplikasi erkap'); 
            $table->string('nama_investasi')->nullable();  
            $table->string('kategori_investasi')->nullable();
            $table->integer('tahun')->nullable();  
            $table->text('rekomendasi')->nullable();
            $table->string('kirim_ke')->nullable();
            $table->string('status')->nullable();
            $table->string('dikirim_oleh')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_rencana_investasi');
    }
};
