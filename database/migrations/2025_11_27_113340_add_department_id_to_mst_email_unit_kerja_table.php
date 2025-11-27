<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_email_unit_kerja', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')
                  ->nullable()
                  ->after('unit_kerja_email');

            $table->foreign('department_id', 'fk_email_unit_kerja_department')
                  ->references('id')
                  ->on('mst_department')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('mst_email_unit_kerja', function (Blueprint $table) {
            $table->dropForeign('fk_email_unit_kerja_department');
            $table->dropColumn('department_id');
        });
    }
};
