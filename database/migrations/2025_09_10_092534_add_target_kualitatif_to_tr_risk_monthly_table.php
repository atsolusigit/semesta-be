<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            // Ubah realization_quantitative menjadi text untuk bisa menampung angka dan kalimat
            $table->text('realization_quantitative')->nullable()->change();

            // Tambah kolom baru untuk realization_kualitatif
            $table->string('realization_kualitatif')->nullable()->after('realization_quantitative');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tr_risk_monthly', function (Blueprint $table) {
            // Kembalikan realization_quantitative ke numeric
            $table->decimal('realization_quantitative', 20, 2)->nullable()->change();

            // Hapus kolom realization_kualitatif
            $table->dropColumn('realization_kualitatif');
        });
    }
};
