<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update data yang existing dari 'submitted' menjadi 'submit'
        DB::table('lost_events')
            ->where('status', 'submitted')
            ->update(['status' => 'submit']);

        // Drop kolom status yang lama
        Schema::table('lost_events', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // Tambah kolom status dengan enum yang baru
        Schema::table('lost_events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submit', 'approved', 'rejected'])
                  ->default('draft')
                  ->after('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update data yang existing dari 'submit' kembali menjadi 'submitted'
        DB::table('lost_events')
            ->where('status', 'submit')
            ->update(['status' => 'submitted']);

        // Drop kolom status yang baru
        Schema::table('lost_events', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // Tambah kolom status dengan enum yang lama
        Schema::table('lost_events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                  ->default('draft')
                  ->after('updated_by');
        });
    }
};
