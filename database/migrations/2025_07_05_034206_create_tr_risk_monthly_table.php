<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tr_risk_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_id')->unique();
            $table->integer('month'); // 1–12
            $table->String('status_risiko',50);
            $table->date('start_date');
            $table->date('expired_date');

            $table->double('realization_quantitative', 15, 2);
            $table->string('realization_option', 255);
            $table->text('realization_other')->nullable();
            $table->text('realization_note')->nullable();
            $table->string('realization_option_position', 5);

            $table->double('target_quantitative', 15, 2);
            $table->string('target_option', 255);
            $table->text('target_other')->nullable();
            $table->text('target_notes')->nullable();
            $table->string('target_option_position', 255);

            $table->unsignedBigInteger('rr_level_dampak');
            $table->unsignedBigInteger('rr_level_kemungkinan');
            $table->unsignedBigInteger('rr_posisi_risiko');
            $table->string('rr_level_risiko', 255);

            $table->timestamps();

            $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tr_risk_monthly');
    }
};
