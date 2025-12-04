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
        Schema::create('lost_event_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lost_event_id');
            $table->string('filepath');
            $table->string('domain')->nullable();
            $table->boolean('is_confirmed')->default(true);
            $table->timestamps();

            $table->foreign('lost_event_id')->references('id')->on('lost_events')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_event_uploads');
    }
};
