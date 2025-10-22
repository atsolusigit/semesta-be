<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasiDetail extends Model
{
    protected $table = 'rencana_investasi_detail';

    protected $fillable = [
        'rencana_investasi_id',
        'peristiwa_risiko',
        'penyebab_risiko',
        'kontrol_internal_eksternal',
        'mitigasi_inherent',
        'mitigasi_residual',
        'inherent_dampak',
        'inherent_kemungkinan',
        'inherent_eksposur_level',
        'inherent_eksposur_kode',
        'inherent_risiko',
        'residual_dampak',
        'residual_kemungkinan',
        'residual_eksposur_level',
        'residual_eksposur_kode',
        'residual_risiko',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'peristiwa_risiko'           => 'array',
        'penyebab_risiko'            => 'array',
        'kontrol_internal_eksternal' => 'array',
        'mitigasi_inherent'          => 'array',
        'mitigasi_residual'          => 'array',
        'inherent_dampak'            => 'integer',
        'inherent_kemungkinan'       => 'integer',
        'residual_dampak'            => 'integer',
        'residual_kemungkinan'       => 'integer',
    ];

    public function header()
    {
        return $this->belongsTo(RencanaInvestasi::class, 'rencana_investasi_id');
    }
}
