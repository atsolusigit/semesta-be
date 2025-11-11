<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (!Schema::hasColumn('tr_risk_investasi', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('tr_risk_investasi', 'synced_at')) {
                $table->dropColumn('synced_at');
            }
        });
    }
};
