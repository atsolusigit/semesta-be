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
            $table->text('mitigasi')->nullable()->after('internal_control');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn('mitigasi');
        });
    }
};
