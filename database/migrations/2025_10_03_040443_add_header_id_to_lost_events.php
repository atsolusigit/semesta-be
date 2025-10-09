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
            // Tambah kolom header_id
            $table->unsignedBigInteger('header_id')->nullable()->after('id');

            // Index untuk optimasi query
            $table->index('header_id');

            // Unique constraint: satu header hanya boleh punya 1 lost event
            $table->unique('header_id');

            // Optional: Foreign key jika ingin relasi strict
            // $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lost_events', function (Blueprint $table) {
            // Drop foreign key jika diaktifkan
            // $table->dropForeign(['header_id']);

            // Drop unique dan index
            $table->dropUnique(['header_id']);
            $table->dropIndex(['header_id']);

            // Drop kolom
            $table->dropColumn('header_id');
        });
    }
};
