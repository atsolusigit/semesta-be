<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tr_risk_header', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_code')->nullable(); // FK ke mst_risk_code
            $table->string('process_code')->nullable();

            $table->text('jenis_risiko')->nullable();
            $table->text('sasaran')->nullable();
            $table->text('peristiwa_risiko')->nullable();
            $table->text('penyebab_risiko')->nullable();
            $table->text('dampak_risiko')->nullable();

            $table->unsignedBigInteger('inherent_risk_level_dampak')->nullable();        // FK ke mst_heatmap_dampak
            $table->unsignedBigInteger('inherent_risk_level_kemungkinan')->nullable();   // FK ke mst_heatmap_kemungkinan
            $table->string('inherent_risk_posisi_risiko')->nullable();                   // hasil kalkulasi angka
            $table->string('inherent_risk_level_risiko')->nullable();                    // hasil level risiko (warna)

            $table->text('internal_control')->nullable();

            $table->unsignedBigInteger('target_satu_tahun_option')->nullable();
            $table->text('target_satu_tahun_notes')->nullable();
            $table->string('target_satu_tahun_position')->nullable();
            $table->double('target_quantitative_satu_tahun', 15, 2)->nullable();

            $table->double('biaya_perlakuan_risiko', 15, 2)->nullable();

            $table->unsignedBigInteger('residual_target_level_dampak')->nullable();        // FK ke mst_heatmap_dampak
            $table->unsignedBigInteger('residual_target_level_kemungkinan')->nullable();   // FK ke mst_heatmap_kemungkinan
            $table->integer('residual_target_posisi_risiko')->nullable();
            $table->string('residual_target_level_risiko')->nullable();

            $table->unsignedBigInteger('department_id')->nullable();
            $table->integer('year')->nullable();                 // contoh: 2024

            $table->enum('status', ['draft', 'submit', 'approved', 'rejected', 'close'])
                ->default('draft')
                ->comment('Status approval header');

            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamps();

            // FK constraint
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_risk_header');
    }
};
