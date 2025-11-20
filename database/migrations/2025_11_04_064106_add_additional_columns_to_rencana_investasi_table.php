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
        Schema::table('rencana_investasi', function (Blueprint $table) {
            // Integer columns
            $table->integer('nilai_budget_transfer')->nullable()->after('nilai_revisi');
            $table->integer('nilai_realisasi')->nullable()->after('nilai_budget_transfer');
            $table->integer('ld_inherent')->nullable()->after('nilai_realisasi');
            $table->integer('ld_current')->nullable()->after('ld_inherent');
            $table->integer('lk_current')->nullable()->after('ld_current');
            $table->integer('level_current')->nullable()->after('lk_current');

            // String columns
            $table->string('target_timeline')->nullable()->after('nilai_realisasi');
            $table->string('realisasi_timeline')->nullable()->after('target_timeline');
            $table->string('level_residual')->nullable()->after('level_current');

            // Text columns (for descriptions / dampak)
            $table->text('dampak_inherent')->nullable()->after('ld_inherent');
            $table->text('dampak_current')->nullable()->after('level_current');
            $table->text('dampak_residual')->nullable()->after('level_residual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            $table->dropColumn([
                'nilai_budget_transfer',
                'nilai_realisasi',
                'target_timeline',
                'realisasi_timeline',
                'ld_inherent',
                'dampak_inherent',
                'ld_current',
                'lk_current',
                'level_current',
                'dampak_current',
                'level_residual',
                'dampak_residual',
            ]);
        });
    }
};
