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
            $table->boolean('is_finalize')->default(false)->after('created_by');
            $table->timestamp('finalized_at')->nullable()->after('is_finalize');
            $table->unsignedBigInteger('finalized_by')->nullable()->after('finalized_at');

            // Index untuk performance query finalisasi
            $table->index('is_finalize');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('tr_risk_monthly_entry', function (Blueprint $table) {
            $table->dropIndex(['is_finalize']);
            $table->dropColumn(['is_finalize', 'finalized_at', 'finalized_by']);
        });
    }
};
