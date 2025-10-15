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
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
           $table->text('target_quantitative')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
             $table->decimal('target_quantitative', 15, 2)->nullable()->change();
        });
    }
};
