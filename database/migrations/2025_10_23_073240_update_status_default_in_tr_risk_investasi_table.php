<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tr_risk_investasi')
            ->whereNull('status')
            ->update(['status' => 'draft']);

        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->string('status')->default('draft')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->string('status')->nullable()->default(null)->change();
        });
    }
};
