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
        Schema::create('mst_approval', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');     // Relasi ke tr_risk_header.id
            $table->integer('tahun');                      // Tahun dokumen
            $table->integer('posisi');                     // Urutan approval (1,2,3...)
            $table->unsignedBigInteger('jabatan_id');      // Relasi ke mst_jabatan
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->timestamp('tanggal')->nullable();      // Waktu approve/reject
            $table->text('note')->nullable();              // Catatan kalau ditolak
            $table->timestamps();

            // Foreign key
            $table->foreign('jabatan_id')->references('id')->on('mst_jabatan')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_approval');
    }
};
