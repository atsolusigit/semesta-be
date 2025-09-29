<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrRcsaResidual extends Model
{
    protected $table = 'tr_rcsa_residual';

     protected $fillable = [
        'kuartal',
        'rcsa_id',
        'residual_eksposur_risiko_kualitatif',
        'residual_eksposur_risiko_kuantitatif',
        'residual_level_risiko',
        'residual_nilai_dampak',
        'residual_nilai_probabilitas',
        'residual_skala_dampak',
        'residual_skala_probabilitas',
        'residual_skala_risiko'
    ];

    /**
     * Relationship dengan TrRcsaHeader
     */
    public function rcsahdr()
    {
        return $this->belongsTo(TrRcsaHeader::class, 'rcsa_id');
    }
}
