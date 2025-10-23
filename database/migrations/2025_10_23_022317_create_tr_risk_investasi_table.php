<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_risk_investasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('erkap_id');
            $table->string('kategori_risiko')->nullable();
            $table->string('sub_kategori_risiko')->nullable();
            $table->text('sasaran')->nullable();
            $table->json('peristiwa_risiko')->nullable();
            $table->json('penyebab_risiko')->nullable();
            $table->text('dampak_inherent')->nullable();
            $table->integer('dampak_risiko_awal')->nullable();
            $table->integer('kemungkinan_awal')->nullable();
            $table->integer('eksposure_level_awal')->nullable();
            $table->string('eksposure_ltmh_awal')->nullable();
            $table->json('internal_external')->nullable();
            $table->json('mitigasi_risiko')->nullable();
            $table->text('dampak_residual')->nullable();
            $table->integer('dampak_risiko_akhir')->nullable();
            $table->integer('kemungkinan_akhir')->nullable();
            $table->integer('eksposure_level_akhir')->nullable();
            $table->string('eksposure_ltmh_akhir')->nullable();
            $table->bigInteger('biaya_mitigasi_risiko')->nullable();
            $table->string('status')->nullable();
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('vp_menrisk_note')->nullable();
            $table->unsignedBigInteger('vp_menrisk_by')->nullable();
            $table->timestamp('vp_menrisk_at')->nullable();
            $table->text('menrisk_note')->nullable();
            $table->unsignedBigInteger('menrisk_by')->nullable();
            $table->timestamp('menrisk_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('erkap_id')->references('id')->on('rencana_investasi')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_risk_investasi');
    }
};
