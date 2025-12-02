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
        Schema::create('knowledge_uploads', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('knowledge_id')->nullable(); // nanti diupdate setelah KB dibuat
            $table->string('type'); // img_path | doc_path
            $table->string('path'); // path file dari storage
            $table->unsignedBigInteger('created_by');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_uploads');
    }
};
