<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasi extends Model
{
    protected $table = 'rencana_investasi';

    protected $fillable = [
        'erkap_id',
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
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
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
        return $this->belongsTo(\App\Models\RencanaInvestasi::class, 'erkap_id', 'erkap_id');
    }
}
