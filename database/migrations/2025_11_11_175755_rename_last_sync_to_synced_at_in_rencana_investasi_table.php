<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('rencana_investasi', 'last_sync')) {
                $table->renameColumn('last_sync', 'synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('rencana_investasi', 'synced_at')) {
                $table->renameColumn('synced_at', 'last_sync');
            }
        });
    }
};
