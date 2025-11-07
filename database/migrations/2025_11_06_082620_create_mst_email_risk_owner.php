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
        Schema::create('mst_email_unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_kerja_id')->unique();
            $table->string('unit_kerja_nama');
            $table->string('unit_kerja_email');
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
        Schema::dropIfExists('mst_email_unit_kerja');
    }
};
