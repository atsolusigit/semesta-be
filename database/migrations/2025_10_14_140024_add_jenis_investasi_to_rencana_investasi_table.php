<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
<<<<<<< HEAD
=======
            // Tambahkan kolom baru jenis_investasi
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
