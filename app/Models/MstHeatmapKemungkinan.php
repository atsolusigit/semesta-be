<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstHeatmapKemungkinan extends Model
{
        protected $table = 'mst_heatmap_kemungkinan';
    protected $fillable = ['id','kemungkinan', 'label'];

    public function heatmaps()
{
    return $this->hasMany(MstHeatmap::class, 'kemungkinan');
}
 public $timestamps = true;
}
