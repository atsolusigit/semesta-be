<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tr_risk_monthly_upload', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_id');
            $table->unsignedBigInteger('risk_monthly_id');
            $table->text('filepath');
            $table->string('domain', 255)->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
            $table->foreign('risk_monthly_id')->references('id')->on('tr_risk_monthly')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tr_risk_monthly_upload');
    }
};
