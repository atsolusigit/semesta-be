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
        Schema::table('tr_rcsa_header', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_rcsa_header', function (Blueprint $table) {
            $table->dropColumn('submitted_by');
        });
    }
};
