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
            $table->string('risk_code', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          Schema::table('tr_risk_monthly', function (Blueprint $table) {
            $table->integer('risk_code')->change();
        });
    }
};
