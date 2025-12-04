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
        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Ubah kolom jenis_risiko dari text menjadi unsignedBigInteger
            $table->unsignedBigInteger('jenis_risiko')->nullable()->change();

            // Tambahkan foreign key ke mst_jenis_risiko
            $table->foreign('jenis_risiko')
                  ->references('id')
                  ->on('mst_jenis_risiko')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Hapus foreign key terlebih dahulu
            $table->dropForeign(['jenis_risiko']);

            // Kembalikan ke text
            $table->text('jenis_risiko')->nullable()->change();
        });
    }
};
