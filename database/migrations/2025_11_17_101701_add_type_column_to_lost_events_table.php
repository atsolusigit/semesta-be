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
            $table->string('type', 50)->nullable()->after('jenis_risiko_id')->comment('Type: kuantitatif, kualitatif, independent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lost_events', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
