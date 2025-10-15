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
      Schema::table('tr_risk_monthly', function (Blueprint $table) {
        $table->text('note_recommendation')->nullable();
        $table->boolean('is_submitted_recommendation')->default(false);
        $table->unsignedBigInteger('recommendation_submitted_by')->nullable();
        $table->timestamp('recommendation_submitted_at')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            $table->dropColumn([
            'note_recommendation',
            'is_submitted_recommendation',
            'recommendation_submitted_by',
            'recommendation_submitted_at',
        ]);
        });
    }
};
