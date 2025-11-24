<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lost_event_uploads', function (Blueprint $table) {
            // Drop foreign key constraint dulu
            $table->dropForeign(['lost_event_id']);

            // Ubah kolom jadi nullable
            $table->unsignedBigInteger('lost_event_id')->nullable()->change();

            // Buat ulang foreign key dengan nullable
            $table->foreign('lost_event_id')
                  ->references('id')
                  ->on('lost_events')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('lost_event_uploads', function (Blueprint $table) {
            $table->dropForeign(['lost_event_id']);
            $table->unsignedBigInteger('lost_event_id')->nullable(false)->change();
            $table->foreign('lost_event_id')
                  ->references('id')
                  ->on('lost_events')
                  ->onDelete('cascade');
        });
    }
};
