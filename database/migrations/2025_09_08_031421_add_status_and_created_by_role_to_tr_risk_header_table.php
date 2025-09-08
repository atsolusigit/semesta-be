<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_role')->nullable()->after('created_by');
        });
    }

    public function down()
    {
        Schema::table('tr_risk_header', function (Blueprint $table) {
            $table->dropColumn('created_by_role');
        });
    }
};
