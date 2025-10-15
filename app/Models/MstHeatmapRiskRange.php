<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstHeatmapRiskRange extends Model
{
    protected $table = 'mst_heatmap_risk_range';

    protected $fillable = [
       'id',
       'name',
       'start',
       'end',
        'color',
    ];
     public $timestamps = true;
}

