<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            // Tambahkan kolom baru jenis_investasi
            $table->string('jenis_investasi')->nullable()->after('kategori_investasi');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            $table->dropColumn('jenis_investasi');
        });
    }
};
