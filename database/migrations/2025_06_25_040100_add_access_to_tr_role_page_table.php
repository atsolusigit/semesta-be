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
    Schema::table('tr_role_page', function (Blueprint $table) {
        $table->string('access')->default('viewer'); // atau json kalau multi-role
    });
}

public function down(): void
{
    Schema::table('tr_role_page', function (Blueprint $table) {
        $table->dropColumn('access');
    });
}

};
