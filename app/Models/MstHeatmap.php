<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class MstHeatmap extends Model
{
    protected $table = 'mst_heatmap';
    protected $fillable = ['dampak', 'kemungkinan', 'result'];

    public function dampak()
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'dampak');
    }

    public function kemungkinan()
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'kemungkinan');
    }

   public function riskRange()
{
    return $this->belongsTo(MstHeatmapRiskRange::class, 'result', 'id');
}

public function getRiskRangeAttribute()
{
    return \App\Models\MstHeatmapRiskRange::where('start', '<=', $this->result)
        ->where('end', '>=', $this->result)
        ->first();
}


     public $timestamps = true;
}
