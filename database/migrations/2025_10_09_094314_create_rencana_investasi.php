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
        Schema::create('rencana_investasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('erkap_id')->nullable()->comment('ID Rencana investasi dari aplikasi erkap');
            $table->string('department_name')->nullable();    
            $table->string('nama_investasi')->nullable();  
            $table->string('kategori_investasi')->nullable();
            $table->integer('year')->nullable();  
            $table->double('nilai_rkap', 15, 2)->nullable();
            $table->double('nilai_revisi', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
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
        Schema::dropIfExists('rencana_investasi');
    }
};
