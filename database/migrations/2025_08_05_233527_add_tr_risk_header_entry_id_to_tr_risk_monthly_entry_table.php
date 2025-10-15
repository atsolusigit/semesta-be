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
      Schema::table('tr_risk_monthly_entry', function (Blueprint $table) {
    $table->unsignedBigInteger('tr_risk_header_entry_id')->nullable()->after('id');
    $table->foreign('tr_risk_header_entry_id')->references('id')->on('tr_risk_header_entry')->onDelete('cascade');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly_entry', function (Blueprint $table) {
            //
        });
    }
};
