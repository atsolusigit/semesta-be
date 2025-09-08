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
            // Kolom untuk rejection
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_note')->nullable()->after('rejected_at');

            // Foreign key constraint (opsional - sesuaikan dengan struktur tabel users Anda)
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            // Drop foreign key constraint dulu
            $table->dropForeign(['rejected_by']);

            // Drop kolom
            $table->dropColumn(['rejected_by', 'rejected_at', 'rejection_note']);
        });
    }
};
