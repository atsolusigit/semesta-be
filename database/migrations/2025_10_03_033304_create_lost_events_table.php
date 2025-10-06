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
        Schema::create('lost_events', function (Blueprint $table) {
            $table->id();

            // 1. Tahun/Periode
            $table->string('tahun', 4);

            // 2. Risk Owner/Department
            $table->string('risk_owner_department');

            // 3. Jenis Risiko
            $table->string('jenis_risiko');

            // 4. Nama Kejadian
            $table->string('nama_kejadian');

            // 5. Identifikasi Kejadian/Peristiwa Risiko
            $table->text('identifikasi_kejadian');

            // 6. Kategori Kejadian
            $table->string('kategori_kejadian')->nullable();

            // 7. Sumber Penyebab Kejadian
            $table->text('sumber_penyebab_kejadian')->nullable();

            // 8. Penyebab Kejadian (dari RCSA)
            $table->text('penyebab_kejadian')->nullable();

            // 9. Penanganan Saat Kejadian
            $table->text('penanganan_saat_kejadian')->nullable();

            // 10. Deskripsi Kejadian - Risk Event
            $table->text('deskripsi_kejadian')->nullable();

            // 11. Pihak Terkait
            $table->string('pihak_terkait')->nullable();

            // 12. Status Asuransi
            $table->string('status_asuransi')->nullable();

            // 13. Kategori Risiko BUMN (dari RCSA)
            $table->string('kategori_risiko_bumn')->nullable();

            // 14. Kategori Risiko T2 & T3 KBUMN (dari RCSA)
            $table->string('kategori_risiko_t2_t3_kbumn')->nullable();

            // 15. Penjelasan Kerugian
            $table->text('penjelasan_kerugian')->nullable();

            // 16. Nilai Kerugian
            $table->decimal('nilai_kerugian', 20, 2)->nullable();

            // 17. Kejadian Berulang
            $table->string('kejadian_berulang')->nullable();

            // 18. Frekuensi Kejadian
            $table->string('frekuensi_kejadian')->nullable();

            // 19. Mitigasi Yang Direncanakan
            $table->text('mitigasi_yang_direncanakan')->nullable();

            // 20. Realisasi Mitigasi
            $table->text('realisasi_mitigasi')->nullable();

            // 21. Perbaikan Mendatang
            $table->text('perbaikan_mendatang')->nullable();

            // 22. Nilai Premi
            $table->decimal('nilai_premi', 20, 2)->nullable();

            // 23. Nilai Klaim
            $table->decimal('nilai_klaim', 20, 2)->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes untuk optimasi query
            $table->index('tahun');
            $table->index('risk_owner_department');
            $table->index('jenis_risiko');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_events');
    }
};
