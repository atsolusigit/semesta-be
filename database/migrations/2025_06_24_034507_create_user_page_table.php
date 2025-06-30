<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPageTable extends Migration
{
    public function up()
    {
        Schema::create('user_page', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mst_page_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('mst_page_id')->references('id')->on('mst_page')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_page');
    }
};

