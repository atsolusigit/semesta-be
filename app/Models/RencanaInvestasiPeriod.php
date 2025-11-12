<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasiPeriod extends Model
{
    protected $table = 'rencana_investasi_periods';

    protected $fillable = [
        'rencana_investasi_id','erkap_id','year','month','week',
        'nilai_rkap','nilai_revisi','nilai_budget_transfer','nilai_kontrak_total',
        'nilai_realisasi_keuangan','nilai_realisasi_fisik','jenis_transfer',
        'detail_json','list_risk_json','source_hash','synced_at',
    ];

    protected $casts = [
        'detail_json'     => 'array',
        'list_risk_json'  => 'array',
        'nilai_rkap'            => 'decimal:2',
        'nilai_revisi'          => 'decimal:2',
        'nilai_budget_transfer' => 'decimal:2',
        'nilai_kontrak_total'   => 'decimal:2',
        'nilai_realisasi_keuangan' => 'decimal:2',
        'nilai_realisasi_fisik'    => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function master()
    {
        return $this->belongsTo(RencanaInvestasi::class, 'rencana_investasi_id');
    }
}
