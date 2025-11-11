<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            if (!Schema::hasColumn('rencana_investasi', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('erkap_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('rencana_investasi', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }
};
