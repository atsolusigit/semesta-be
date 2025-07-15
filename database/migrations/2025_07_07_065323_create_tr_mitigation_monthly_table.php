<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tr_mitigation_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_id');
            $table->unsignedBigInteger('detail_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->unsignedBigInteger('risk_monthly_id');

            $table->foreign('header_id')->references('id')->on('tr_risk_header')->onDelete('cascade');
            $table->foreign('risk_monthly_id')->references('id')->on('tr_risk_monthly')->onDelete('cascade');

             $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_mitigation_monthly');
    }
};
