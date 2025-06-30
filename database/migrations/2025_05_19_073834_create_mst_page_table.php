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
        Schema::create('mst_page', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('head_url');
            $table->integer('is_web')->default(1);
            $table->integer('is_mobile')->default(0);
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_page');
    }
};
