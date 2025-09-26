<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrRcsaHeader extends Model
{
    protected $table = 'tr_rcsa_header';

    protected $fillable = [
        'asumsi_perhitungan_dampak',
        'biaya_perlakuan_risiko',
        'deskripsi_dampak',
        'deskripsi_peristiwa_risiko',
        'existing_control',
        'hasil_yang_diharapkan_perusahaan',
        'inherent_eksposur_risiko_kualitatif',
        'inherent_eksposur_risiko_kuantitatif',
        'inherent_level_risiko',
        'inherent_nilai_dampak',
        'inherent_nilai_probabilitas',
        'inherent_skala_dampak',
        'inherent_skala_probabilitas',
        'inherent_skala_risiko',
        'jenis_existing_control',
        'jenis_program_dalam_rkap',
        'kategori_dampak',
        'kategori_risiko_bumn',
        'kategori_risiko_t2_t3_kbumn',
        'kategori_threshold_kri_aman',
        'kategori_threshold_kri_bahaya',
        'kategori_threshold_kri_hati_hati',
        'keputusan_penetapan',
        'key_risk_indicators',
        'kode_bumn',
        'nama_bumn',
        'nilai_limit_risiko',
        'nilai_risiko_yang_akan_timbul',
        'opsi_perlakuan_risiko',
        'output_perlakuan_risiko',
        'penilaian_efektivitas_kontrol',
        'penyebab_risiko',
        'peristiwa_risiko',
        'perkiraan_waktu_terpapar_risiko',
        'pic',
        'pilihan_sasaran',
        'pilihan_strategi',
        'rencana_perlakuan_risiko',
        'sasaran_kbumn',
        'status',
        'timeline_bulan_akhir',
        'timeline_bulan_awal',
        'unit_kerja_id',
        'unit_satuan_kri',
        'updated_at',
        'created_at',
        'created_by',
        'updated_by',
        'year',
    ];


    public function rcsaResidual(): HasMany
    {
        return $this->hasMany(TrRcsaResidual::class, 'rcsa_id');
    }

    public function rcsaRisikoList(): HasMany
    {
        return $this->hasMany(TrRcsaRencanaRisikoList::class, 'rcsa_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')
                    ->select(['id', 'name', 'username']);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(MstDepartment::class, 'unit_kerja_id')
        ->select(['id', 'name']);;
    }

}
