<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrRcsaRencanaRisikoList extends Model
{
    protected $table = 'tr_rcsa_rencana_risiko_list';

    protected $fillable = [
        'jenis_rencana_perlakuan_risiko',
        'rcsa_id'
    ];

    /**
     * Relationship dengan TrRcsaHeader
     */
    public function rcsahdr()
    {
        return $this->belongsTo(TrRcsaHeader::class, 'rcsa_id');
    }
}
