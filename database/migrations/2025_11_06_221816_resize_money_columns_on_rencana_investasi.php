<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $t) {
            $t->decimal('nilai_rkap', 20, 2)->nullable()->change();
            $t->decimal('nilai_revisi', 20, 2)->nullable()->change();
            $t->decimal('nilai_budget_transfer', 20, 2)->nullable()->change();
            $t->decimal('nilai_realisasi', 20, 2)->nullable()->change();

            if (!Schema::hasColumn('rencana_investasi', 'nilai_kontrak_total')) {
                $t->decimal('nilai_kontrak_total', 20, 2)->nullable();
            }
            if (!Schema::hasColumn('rencana_investasi', 'kategori_id')) {
                $t->unsignedBigInteger('kategori_id')->nullable();
            }
            if (!Schema::hasColumn('rencana_investasi', 'jenis_transfer')) {
                $t->string('jenis_transfer', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $t) {
            $t->decimal('nilai_rkap', 15, 2)->nullable()->change();
            $t->decimal('nilai_revisi', 15, 2)->nullable()->change();
            $t->decimal('nilai_budget_transfer', 15, 2)->nullable()->change();
            $t->decimal('nilai_realisasi', 15, 2)->nullable()->change();
            $t->dropColumn(['nilai_kontrak_total', 'kategori_id', 'jenis_transfer']);
        });
    }
};
