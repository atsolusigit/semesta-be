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
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('is_finalize');
                $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->text('approval_notes')->nullable()->after('approved_at');

                $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            //
        });
    }
};
