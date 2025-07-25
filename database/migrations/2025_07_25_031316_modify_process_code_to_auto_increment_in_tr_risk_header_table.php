<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop kolom process_code yang lama (string)
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn('process_code');
        });

        // Tambah kolom process_code baru sebagai integer
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->integer('process_code')->nullable()->after('risk_code');
            $table->index('process_code'); // index untuk performa
        });

        // Populate existing data dengan sequential number
        $records = DB::table('tr_risk_header')->orderBy('id')->get();
        foreach ($records as $index => $record) {
            DB::table('tr_risk_header')
                ->where('id', $record->id)
                ->update(['process_code' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropIndex(['process_code']);
            $table->dropColumn('process_code');
        });

        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->string('process_code')->nullable()->after('risk_code');
        });
    }
};
