<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledgebase', function (Blueprint $table) {
            $table->string('title')->after('img_path');
        });
    }

    public function down(): void
    {
        Schema::table('knowledgebase', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
