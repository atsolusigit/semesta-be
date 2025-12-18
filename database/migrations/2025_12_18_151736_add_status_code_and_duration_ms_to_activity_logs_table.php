<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('status_code')->nullable()->index()->after('action');
            $table->unsignedInteger('duration_ms')->nullable()->index()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['status_code', 'duration_ms']);
        });
    }
};
