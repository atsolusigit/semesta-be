<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('gender', 10)->nullable()->after('phone_number'); // atau tempatkan sesuai kebutuhan
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('gender');
    });
}

};
