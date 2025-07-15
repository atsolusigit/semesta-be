<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MstHeatmapDampak extends Model
{
     use HasFactory;

    protected $table = 'mst_heatmap_dampak';

    protected $fillable = [
        'dampak',
        'label',
    ];

    public function heatmaps()
{
    return $this->hasMany(MstHeatmap::class, 'dampak');
}
 public $timestamps = true;
}
