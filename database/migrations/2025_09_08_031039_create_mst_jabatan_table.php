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
        Schema::create('mst_jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                   // Nama jabatan (Manager, GM, dll)
            $table->string('nipp', 50)->nullable();        // NIPP pegawai yg menjabat
            $table->unsignedBigInteger('department_id');   // Relasi ke tabel department
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_jabatan');
    }
};
