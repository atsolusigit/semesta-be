<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rencana_investasi_timeline_years', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('erkap_id')->index();
            $t->integer('year')->index();
            $t->json('timeline_json')->nullable();
            $t->string('source_hash', 64)->nullable()->index();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique(['erkap_id','year'], 'uniq_erkap_year');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('rencana_investasi_timeline_years');
    }
};
