<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mst_heatmap_kemungkinan', function (Blueprint $table) {
            $table->id(); // UNSIGNED BIGINT
            $table->integer('kemungkinan'); // nilai 1-5
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_heatmap_kemungkinan');
    }
};
