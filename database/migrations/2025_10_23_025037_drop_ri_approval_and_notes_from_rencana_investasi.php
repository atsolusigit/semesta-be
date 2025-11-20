<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            if (Schema::hasColumn('rencana_investasi', 'sasaran')) $table->dropColumn('sasaran');
            if (Schema::hasColumn('rencana_investasi', 'catatan_svp_unit')) $table->dropColumn('catatan_svp_unit');
            if (Schema::hasColumn('rencana_investasi', 'catatan_svp_menrisk')) $table->dropColumn('catatan_svp_menrisk');
            if (Schema::hasColumn('rencana_investasi', 'approved_by')) $table->dropColumn('approved_by');
            if (Schema::hasColumn('rencana_investasi', 'approved_at')) $table->dropColumn('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_investasi', function (Blueprint $table) {
            $table->text('sasaran')->nullable();
            $table->text('catatan_svp_unit')->nullable();
            $table->text('catatan_svp_menrisk')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
    }

};
