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
            $table->boolean('is_confirmed')->default(false)->after('domain')->comment('false = belum dikonfirmasi, true = sudah disimpan atau final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          Schema::table('tr_risk_monthly_upload', function (Blueprint $table) {
            $table->dropColumn('is_confirmed');
        });
    }
};
