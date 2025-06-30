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
        Schema::create('knowledgebase', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('creator_id');
        $table->text('img_path');
        $table->text('description')->nullable();
        $table->text('long_description')->nullable();
        $table->unsignedTinyInteger('type')->default(1); // 1=News, dst
        $table->timestamps();

        $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledgebase');
    }
};
