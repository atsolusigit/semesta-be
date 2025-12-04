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
        Schema::create('mst_jenis_risiko', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jenis_risiko');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign key jika diperlukan
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_jenis_risiko');
    }
};
