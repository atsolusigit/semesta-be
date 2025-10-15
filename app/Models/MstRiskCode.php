<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstRiskCode extends Model
{
 protected $table = 'mst_risk_code'; // Nama tabel

    protected $fillable = [
        'id',
        'code',
        'name',
    ];

    public $timestamps = true;
}
