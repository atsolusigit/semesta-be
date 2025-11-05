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
        Schema::table('lost_events', function (Blueprint $table) {
            $table->unsignedBigInteger('rcsa_id')->nullable()->after('header_id');

            $table->foreign('rcsa_id')
                  ->references('id')
                  ->on('tr_rcsa_header')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lost_events', function (Blueprint $table) {
            $table->dropForeign(['rcsa_id']);
            $table->dropColumn('rcsa_id');
        });
    }
};
