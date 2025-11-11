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
            $table->boolean('reviewed')->default(false)->after('approved_at');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn(['reviewed', 'reviewed_by', 'reviewed_at']);
        });
    }
};
