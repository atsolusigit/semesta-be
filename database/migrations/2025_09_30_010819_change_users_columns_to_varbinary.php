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
        Schema::table('varbinary', function (Blueprint $table) {
            DB::statement('ALTER TABLE users MODIFY COLUMN name VARBINARY(255)');
            DB::statement('ALTER TABLE users MODIFY COLUMN username VARBINARY(255)');
            DB::statement('ALTER TABLE users MODIFY COLUMN email VARBINARY(255)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('varbinary', function (Blueprint $table) {
            DB::statement('ALTER TABLE users MODIFY COLUMN name VARCHAR(255)');
            DB::statement('ALTER TABLE users MODIFY COLUMN username VARCHAR(255)');
            DB::statement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255)');
        });
    }
};
