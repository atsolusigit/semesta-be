<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mst_heatmap_dampak', function (Blueprint $table) {
            $table->id(); // ini otomatis UNSIGNED BIGINT
            $table->integer('dampak'); // nilai dampak, misal 1-5
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_heatmap_dampak');
    }
};
