<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tr_risk_monthly_entry', function (Blueprint $table) {
            $table->id();

            // Relasi ke tr_risk_header
            $table->unsignedBigInteger('header_id');

            // Relasi ke tr_risk_monthly
            $table->unsignedBigInteger('monthly_id'); // pastikan saat insert DIISI, JANGAN kosong

            $table->tinyInteger('month');

            $table->unsignedBigInteger('risk_code')->nullable();
            $table->string('status_risiko', 50)->nullable();
            $table->string('process_code')->default(1);
            $table->date('start_date')->nullable();
            $table->date('expired_date')->nullable();

            // Realization data
            $table->double('realization_quantitative', 15, 2)->nullable();
            $table->string('realization_option', 255)->nullable();
            $table->text('realization_note')->nullable();
            $table->string('realization_option_position', 5)->nullable();

            // Target data
            $table->double('target_quantitative', 15, 2)->nullable();
            $table->string('target_option', 255)->nullable();
            $table->text('target_notes')->nullable();
            $table->string('target_option_position', 255)->nullable();

            // Residual Risk monthly
            $table->unsignedBigInteger('residual_risk_level_dampak')->nullable();
            $table->unsignedBigInteger('residual_risk_level_kemungkinan')->nullable();
            $table->integer('residual_risk_posisi_risiko')->nullable();
            $table->string('residual_risk_level_risiko', 255)->nullable();

            // Residual Risk year
            $table->unsignedBigInteger('residual_risk_satutahun_level_dampak')->nullable();
            $table->unsignedBigInteger('residual_risk_satutahun_level_kemungkinan')->nullable();
            $table->integer('residual_risk_satutahun_posisi_risiko')->nullable();
            $table->string('residual_risk_satutahun_level_risiko', 255)->nullable();

            // Optional informasi user input
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
            $table->foreign('monthly_id')->references('id')->on('tr_risk_monthly')->onDelete('cascade');

            $table->unique(['header_id', 'month']);

            $table->index('header_id');
            $table->index('monthly_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tr_risk_monthly_entry');
    }
};
