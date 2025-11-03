<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_rcsa_header', function (Blueprint $table) {
            $table->boolean('isSubmit')->default(false)->after('status');
        });

        DB::table('tr_rcsa_header')
            ->whereNull('isSubmit')
            ->update(['isSubmit' => false]);
    }

    public function down(): void
    {
        Schema::table('tr_rcsa_header', function (Blueprint $table) {
            $table->dropColumn('isSubmit');
        });
    }
};
