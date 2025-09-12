<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Drop kolom status yang lama
            $table->dropColumn('status');
        });

        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Tambahkan kolom status yang baru dengan enum
            $table->enum('status', ['draft', 'submit', 'approved', 'rejected', 'close'])
                  ->default('draft')
                  ->after('is_complete'); // Sesuaikan posisi kolom sesuai kebutuhan
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Drop kolom status yang baru
            $table->dropColumn('status');
        });

        Schema::table('tr_risk_header', function (Blueprint $table) {
            // Kembalikan kolom status yang lama
            $table->enum('status', ['draft', 'approved', 'rejected', 'close'])
                  ->default('draft')
                  ->after('is_complete');
        });
    }
};
