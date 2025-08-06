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
          Schema::table('tr_risk_monthly_upload', function (Blueprint $table) {
            $table->unsignedBigInteger('risk_monthly_entry_id')->nullable()->after('risk_monthly_id');

            $table->foreign('risk_monthly_entry_id')
                ->references('id')
                ->on('tr_risk_monthly_entry')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly_upload', function (Blueprint $table) {
            $table->dropForeign(['risk_monthly_entry_id']);
            $table->dropColumn('risk_monthly_entry_id');
        });
    }
};
