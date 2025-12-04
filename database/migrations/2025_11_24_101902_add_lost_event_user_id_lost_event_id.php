<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lost_event_uploads', function (Blueprint $table) {
            // Tambah kolom user_id setelah lost_event_id
            $table->unsignedBigInteger('user_id')->nullable()->after('lost_event_id');

            // Tambah foreign key ke users table
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('lost_event_uploads', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
