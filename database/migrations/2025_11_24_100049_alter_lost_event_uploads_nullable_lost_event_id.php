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
          Schema::table('lost_event_uploads', function (Blueprint $table) {
        // Allow null
        $table->unsignedBigInteger('lost_event_id')->nullable()->change();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
           Schema::table('lost_event_uploads', function (Blueprint $table) {
        $table->unsignedBigInteger('lost_event_id')->nullable(false)->change();
    });
    }
};
