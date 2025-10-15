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
            Schema::create('tr_role_page', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->foreign('page_id')->references('id')->on('mst_page')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('mst_role')->onDelete('cascade');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('tr_role_page');
        }
    };
