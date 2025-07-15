<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tr_risk_header', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_code')->nullable(); // FK ke mst_risk_code
            $table->string('process_code')->default(1);

            $table->text('prefix_risiko')->nullable();
            $table->text('sasaran')->nullable();
            $table->text('permasalahan_risiko')->nullable();
            $table->text('dampak')->nullable();
            $table->text('dampak_risiko')->nullable();

            $table->unsignedBigInteger('ir_level_dampak')->nullable();        // FK ke mst_heatmap_dampak
            $table->unsignedBigInteger('ir_level_kemungkinan')->nullable();   // FK ke mst_heatmap_kemungkinan
            $table->string('ir_posisi_risiko')->nullable();                   // hasil kalkulasi angka
            $table->string('ir_level_risiko')->nullable();                    // hasil level risiko (warna)

            $table->text('internal_control')->nullable();

            $table->date('target_waktu_selesai')->nullable();
            $table->string('target_waktu_selesai_option')->nullable();
            $table->text('target_waktu_selesai_other')->nullable();
            $table->text('target_waktu_selesai_notes')->nullable();
            $table->string('target_waktu_selesai_position')->nullable();

            $table->double('biaya_pertolongan_risiko', 15, 2)->nullable();

            $table->unsignedBigInteger('rr_level_dampak')->nullable();        // FK ke mst_heatmap_dampak
            $table->unsignedBigInteger('rr_level_kemungkinan')->nullable();   // FK ke mst_heatmap_kemungkinan
            $table->integer('rr_posisi_risiko')->nullable();
            $table->string('rr_level_risiko')->nullable();

            $table->unsignedBigInteger('department_id')->nullable(); // ini dulu branch sekarang jadi department
            $table->integer('year')->nullable();                 // contoh: 2024

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_risk_header');
    }
};
