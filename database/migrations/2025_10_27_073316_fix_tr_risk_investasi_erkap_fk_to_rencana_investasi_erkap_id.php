<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->dropForeign(['erkap_id']);

            $table->unsignedBigInteger('erkap_id')->change();

            $table->foreign('erkap_id', 'tri_erkap_to_ri_erkap_fk')
                  ->references('erkap_id')
                  ->on('rencana_investasi')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('tr_risk_investasi', function (Blueprint $table) {
            $table->dropForeign('tri_erkap_to_ri_erkap_fk');
            $table->foreign('erkap_id')
                  ->references('id')
                  ->on('rencana_investasi')
                  ->onDelete('cascade');
        });
    }
};
