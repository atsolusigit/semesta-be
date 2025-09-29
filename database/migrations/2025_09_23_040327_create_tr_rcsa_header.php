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
        Schema::create('tr_rcsa_header', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->string('status')->nullable();

            $table->text('pilihan_sasaran')->nullable();
            $table->text('pilihan_strategi')->nullable();
            $table->text('hasil_yang_diharapkan_perusahaan')->nullable();
            $table->text('nilai_risiko_yang_akan_timbul')->nullable();
            $table->text('nilai_limit_risiko')->nullable();
            $table->boolean('keputusan_penetapan')->default(false)->nullable();

            $table->string('perkiraan_waktu_terpapar_risiko')->nullable();
            $table->text('deskripsi_dampak')->nullable();
            $table->boolean('kategori_dampak')->default(false)->nullable();
            $table->string('penilaian_efektivitas_kontrol')->nullable();
            $table->string('jenis_existing_control')->nullable();
            $table->text('existing_control')->nullable();
            $table->string('kategori_threshold_kri_bahaya')->nullable();
            $table->string('kategori_threshold_kri_hati_hati')->nullable();
            $table->string('kategori_threshold_kri_aman')->nullable();
            $table->string('key_risk_indicators')->nullable();
            $table->string('unit_satuan_kri')->nullable();
            $table->text('penyebab_risiko')->nullable();
            $table->text('deskripsi_peristiwa_risiko')->nullable();
            $table->text('peristiwa_risiko')->nullable();
            $table->string('kategori_risiko_t2_t3_kbumn')->nullable();
            $table->string('kategori_risiko_bumn')->nullable();
            $table->string('nama_bumn')->nullable();
            $table->string('kode_bumn')->nullable();
            $table->string('sasaran_kbumn')->nullable();

            $table->string('opsi_perlakuan_risiko')->nullable();
            $table->text('rencana_perlakuan_risiko')->nullable();
            $table->string('output_perlakuan_risiko')->nullable();
            $table->unsignedBigInteger('biaya_perlakuan_risiko')->nullable();
            $table->string('jenis_program_dalam_rkap')->nullable();
            $table->string('pic')->nullable();
            $table->timestamp('timeline_bulan_akhir')->nullable();
            $table->timestamp('timeline_bulan_awal')->nullable();
            $table->text('asumsi_perhitungan_dampak')->nullable();
            $table->string('inherent_level_risiko')->nullable();
            $table->integer('inherent_skala_risiko')->nullable();
            $table->text('inherent_eksposur_risiko_kualitatif')->nullable();
            $table->unsignedBigInteger('inherent_eksposur_risiko_kuantitatif')->nullable();
            $table->integer('inherent_nilai_probabilitas')->nullable();
            $table->integer('inherent_skala_probabilitas')->nullable();
            $table->unsignedBigInteger('inherent_nilai_dampak')->nullable();
            $table->integer('inherent_skala_dampak')->nullable();
            $table->integer('year')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tr_rcsa_header');
    }
};
