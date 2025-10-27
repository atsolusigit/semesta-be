<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop existing foreign key dynamically if it exists
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tr_risk_investasi'
              AND COLUMN_NAME = 'erkap_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fk && isset($fk->CONSTRAINT_NAME)) {
            DB::statement("ALTER TABLE `tr_risk_investasi` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // 2) Ensure type is correct
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->unsignedBigInteger('erkap_id')->change();
        });

        // 3) Add correct FK to rencana_investasi.erkap_id
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->foreign('erkap_id', 'tri_erkap_to_ri_erkap_fk')
                  ->references('erkap_id')
                  ->on('rencana_investasi')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('tr_risk_investasi', function (Blueprint $table) {
                $table->dropForeign('tri_erkap_to_ri_erkap_fk');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->foreign('erkap_id')
                  ->references('id')
                  ->on('rencana_investasi')
                  ->onDelete('cascade');
        });
    }
};
