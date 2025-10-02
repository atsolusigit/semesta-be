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
            $table->text('menrisk_note')->nullable()->after('approval_notes');
            $table->unsignedBigInteger('menrisk_by')->nullable()->after('menrisk_note');
            $table->timestamp('menrisk_at')->nullable()->after('menrisk_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
             $table->dropColumn(['menrisk_note', 'menrisk_by', 'menrisk_at']);
        });
    }
};
