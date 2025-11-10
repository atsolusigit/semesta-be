<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasi extends Model
{
    protected $table = 'rencana_investasi';

    protected $fillable = [
        'erkap_id',
        'department_id',
        'department_name',
        'nama_investasi',
        'kategori_investasi',
        'jenis_investasi',
        'year',
        'nilai_rkap',
        'nilai_revisi',
        'nilai_budget_transfer',
        'nilai_realisasi',
        'target_timeline',
        'realisasi_timeline',
        'ld_inherent',
        'dampak_inherent',
        'ld_current',
        'lk_current',
        'level_current',
        'dampak_current',
        'level_residual',
        'dampak_residual',
        'keterangan',
        'status',
        'unit_kerja_id',
        'nilai_kontrak_total',
        'kategori_id',
        'jenis_transfer',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'year'                   => 'integer',
        'department_id'          => 'integer',
        'unit_kerja_id'          => 'integer',
        'lk_current'             => 'integer',
        'level_current'          => 'integer',
        'kategori_id'            => 'integer',

        'nilai_rkap'             => 'decimal:2',
        'nilai_revisi'           => 'decimal:2',
        'nilai_budget_transfer'  => 'decimal:2',
        'nilai_realisasi'        => 'decimal:2',
        'nilai_kontrak_total'    => 'decimal:2',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')
            ->select(['id', 'name', 'username']);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function riskInvestasi()
    {
        return $this->hasOne(TrRiskInvestasi::class, 'erkap_id', 'erkap_id');
    }

    public function periods()
    {
        return $this->hasMany(RencanaInvestasiPeriod::class, 'rencana_investasi_id');
    }
}
