<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            $table->unsignedBigInteger('erkap_id')->change();
            $table->unique('erkap_id', 'ri_erkap_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            $table->dropUnique('ri_erkap_id_unique');
        });
    }
};
