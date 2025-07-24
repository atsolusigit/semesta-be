<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
            Schema::create('tr_risk_monthly', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('header_id');
    $table->integer('month'); // 1–12
    $table->string('status_risiko', 50);
    $table->string('process_code')->default(1);
    $table->date('start_date');
    $table->date('expired_date');

    // Realization data
    $table->double('realization_quantitative', 15, 2)->nullable();
    $table->string('realization_option', 255)->nullable();
    // $table->text('realization_other')->nullable();
    $table->text('realization_note')->nullable();
    $table->string('realization_option_position', 5)->nullable();

    // Target data
    $table->double('target_quantitative', 15, 2)->nullable();
    $table->string('target_option', 255)->nullable();
    // $table->text('target_other')->nullable();
    $table->text('target_notes')->nullable();
    $table->string('target_option_position', 255)->nullable();

    // Residual Risk monthly
    $table->unsignedBigInteger('residual_risk_level_dampak')->nullable();
    $table->unsignedBigInteger('residual_risk_level_kemungkinan')->nullable();
    $table->integer('residual_risk_posisi_risiko')->nullable(); // ubah ke integer
    $table->string('residual_risk_level_risiko', 255)->nullable();

    // Residual Risk year
    $table->unsignedBigInteger('residual_risk_satutahun_level_dampak')->nullable();
    $table->unsignedBigInteger('residual_risk_satutahun_level_kemungkinan')->nullable();
    $table->integer('residual_risk_satutahun_posisi_risiko')->nullable(); // ubah ke integer
    $table->string('residual_risk_satutahun_level_risiko', 255)->nullable();

    $table->boolean('is_finalize')->default(false);
    $table->timestamp('finalized_at')->nullable();
    $table->unsignedBigInteger('finalized_by')->nullable();

    $table->timestamps();

    // Constraints
    $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
    $table->unique(['header_id', 'month']); // Satu header, satu bulan

    // Index untuk performa
    $table->index(['header_id', 'month']);
});
    }


    public function down(): void {
        Schema::dropIfExists('tr_risk_monthly');
    }
};
