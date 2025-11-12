<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrRiskInvestasi extends Model
{
    protected $table = 'tr_risk_investasi';

    protected $fillable = [
        'erkap_id',
        'tahun',
        'unit_kerja_id',
        'unit_kerja_nama',
        'nilai',
        'with_sub_pekerjaan',
        'capex_sub_id',
        'nama_sub_pekerjaan',
        'kategori_risiko',
        'risk_kategori_id',
        'sub_kategori_risiko',
        'sasaran',
        'peristiwa_risiko',
        'penyebab_risiko',
        'dampak_inherent',
        'dampak_risiko_awal',
        'kemungkinan_awal',
        'eksposure_level_awal',
        'eksposure_ltmh_awal',
        'eksposure_kode_awal',
        'eksposure_color_awal',
        'internal_external',
        'mitigasi_risiko',
        'erkap_list_risk_json',
        'dampak_residual',
        'dampak_risiko_akhir',
        'kemungkinan_akhir',
        'eksposure_level_akhir',
        'eksposure_ltmh_akhir',
        'eksposure_kode_akhir',
        'eksposure_color_akhir',
        'biaya_mitigasi_risiko',
        'status',
        'approval_notes',
        'approved_by',
        'approved_at',
        'vp_menrisk_note',
        'vp_menrisk_by',
        'vp_menrisk_at',
        'menrisk_note',
        'menrisk_by',
        'menrisk_at',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'peristiwa_risiko'     => 'array',
        'penyebab_risiko'      => 'array',
        'internal_external'    => 'array',
        'mitigasi_risiko'      => 'array',
        'erkap_list_risk_json' => 'array',
        'with_sub_pekerjaan'   => 'boolean',
        'approved_at'          => 'datetime',
        'vp_menrisk_at'        => 'datetime',
        'menrisk_at'           => 'datetime',
        'synced_at'            => 'datetime',
    ];

    public function investasi()
    {
        return $this->belongsTo(\App\Models\RencanaInvestasi::class, 'erkap_id', 'erkap_id');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
