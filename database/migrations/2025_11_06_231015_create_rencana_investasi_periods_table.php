<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rencana_investasi_periods', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rencana_investasi_id')->nullable()->index();
            $t->unsignedBigInteger('erkap_id')->index();
            $t->integer('year')->index();
            $t->integer('month')->index();
            $t->integer('week')->index();
            $t->decimal('nilai_rkap', 20, 2)->nullable();
            $t->decimal('nilai_revisi', 20, 2)->nullable();
            $t->decimal('nilai_budget_transfer', 20, 2)->nullable();
            $t->decimal('nilai_kontrak_total', 20, 2)->nullable();
            $t->decimal('nilai_realisasi_keuangan', 20, 2)->nullable();
            $t->decimal('nilai_realisasi_fisik', 20, 2)->nullable();
            $t->string('jenis_transfer', 50)->nullable();
            $t->json('detail_json')->nullable();
            $t->json('list_risk_json')->nullable();
            $t->string('source_hash', 64)->nullable()->index();
            $t->timestamp('synced_at')->nullable();

            $t->timestamps();

            $t->unique(['erkap_id','year','month','week'], 'uniq_period_erkap_year_month_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_investasi_periods');
    }
};
