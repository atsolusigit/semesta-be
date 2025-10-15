<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tr_risk_header_entry', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tr_risk_header_id'); // FK ke tr_risk_header

            $table->unsignedBigInteger('risk_code')->nullable();
            $table->string('process_code')->nullable();

            $table->text('jenis_risiko')->nullable();
            $table->text('sasaran')->nullable();
            $table->text('peristiwa_risiko')->nullable();
            $table->text('penyebab_risiko')->nullable();
            $table->text('dampak_risiko')->nullable();

            $table->unsignedBigInteger('inherent_risk_level_dampak')->nullable();
            $table->unsignedBigInteger('inherent_risk_level_kemungkinan')->nullable();
            $table->string('inherent_risk_posisi_risiko')->nullable();
            $table->string('inherent_risk_level_risiko')->nullable();

            $table->text('internal_control')->nullable();

            $table->unsignedBigInteger('target_satu_tahun_option')->nullable();
            $table->text('target_satu_tahun_notes')->nullable();
            $table->string('target_satu_tahun_position')->nullable();
            $table->double('target_quantitative_satu_tahun', 15, 2)->nullable();

            $table->double('biaya_perlakuan_risiko', 15, 2)->nullable();

            $table->unsignedBigInteger('residual_target_level_dampak')->nullable();
            $table->unsignedBigInteger('residual_target_level_kemungkinan')->nullable();
            $table->integer('residual_target_posisi_risiko')->nullable();
            $table->string('residual_target_level_risiko')->nullable();

            $table->unsignedBigInteger('department_id')->nullable();
            $table->integer('year')->nullable();

            $table->timestamps();

            // FK constraint (optional)
            $table->foreign('tr_risk_header_id')
                  ->references('id')
                  ->on('tr_risk_header')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_risk_header_entry');
    }
};
