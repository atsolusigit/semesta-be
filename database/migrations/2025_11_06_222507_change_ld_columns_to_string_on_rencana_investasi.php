<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $t) {
            $t->string('ld_inherent', 10)->nullable()->change();
            $t->string('ld_current', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $t) {
            // Kembalikan ke integer jika sebelumnya integer
            $t->integer('ld_inherent')->nullable()->change();
            $t->integer('ld_current')->nullable()->change();
        });
    }
};
