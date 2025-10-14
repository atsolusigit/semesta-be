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
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->text('vp_menrisk_note')->nullable()->after('menrisk_at');
            $table->unsignedBigInteger('vp_menrisk_by')->nullable()->after('vp_menrisk_note');
            $table->timestamp('vp_menrisk_at')->nullable()->after('vp_menrisk_by');
        });
    }

    /**
     * Reverse migration.
     */
    public function down(): void
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn(['vp_menrisk_note', 'vp_menrisk_by', 'vp_menrisk_at']);
        });
    }
};
