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
        Schema::table('lost_events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                  ->default('draft')
                  ->after('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lost_events', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
