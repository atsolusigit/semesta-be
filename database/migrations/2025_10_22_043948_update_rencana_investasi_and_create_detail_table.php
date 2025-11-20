<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Update main table (add only if missing)
        if (Schema::hasTable('rencana_investasi')) {
            Schema::table('rencana_investasi', function (Blueprint $table) {
                if (!Schema::hasColumn('rencana_investasi', 'jenis_investasi')) {
                    $table->string('jenis_investasi')->nullable()->after('kategori_investasi');
                }
                if (!Schema::hasColumn('rencana_investasi', 'sasaran')) {
                    $table->string('sasaran')->nullable()->after('jenis_investasi');
                }
                if (!Schema::hasColumn('rencana_investasi', 'catatan_svp_unit')) {
                    $table->text('catatan_svp_unit')->nullable()->after('status');
                }
                if (!Schema::hasColumn('rencana_investasi', 'catatan_svp_menrisk')) {
                    $table->text('catatan_svp_menrisk')->nullable()->after('catatan_svp_unit');
                }
            });
        }

        // 2) Create detail table only if it doesn't exist
        if (!Schema::hasTable('rencana_investasi_detail')) {
            Schema::create('rencana_investasi_detail', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rencana_investasi_id');

                // JSON arrays
                $table->json('peristiwa_risiko')->nullable();
                $table->json('penyebab_risiko')->nullable();
                $table->json('kontrol_internal_eksternal')->nullable();
                $table->json('mitigasi_inherent')->nullable();
                $table->json('mitigasi_residual')->nullable();

                // Inherent values
                $table->integer('inherent_dampak')->nullable();
                $table->integer('inherent_kemungkinan')->nullable();
                $table->string('inherent_eksposur_level')->nullable();
                $table->string('inherent_eksposur_kode')->nullable();
                $table->string('inherent_risiko')->nullable();

                // Residual values
                $table->integer('residual_dampak')->nullable();
                $table->integer('residual_kemungkinan')->nullable();
                $table->string('residual_eksposur_level')->nullable();
                $table->string('residual_eksposur_kode')->nullable();
                $table->string('residual_risiko')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('rencana_investasi_id')
                      ->references('id')->on('rencana_investasi')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rencana_investasi')) {
            Schema::table('rencana_investasi', function (Blueprint $table) {
                // Drop only if columns exist
                $drops = [];
                foreach (['jenis_investasi','sasaran','catatan_svp_unit','catatan_svp_menrisk'] as $col) {
                    if (Schema::hasColumn('rencana_investasi', $col)) {
                        $drops[] = $col;
                    }
                }
                if (!empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('rencana_investasi_detail')) {
            Schema::dropIfExists('rencana_investasi_detail');
        }
    }
};
