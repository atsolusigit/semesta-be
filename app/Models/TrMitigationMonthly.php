<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrMitigationMonthly extends Model
{
    protected $table = 'tr_mitigation_monthly';

    protected $fillable = [
        'header_id',
        'detail_id',
        'notes',
        'timestamp',
        'risk_monthly_id'
    ];

    public $timestamps = true;

    public function riskHeader()
    {
        return $this->belongsTo(TrRiskHeader::class, 'header_id');
    }

    // public function riskDetail()
    // {
    //     return $this->belongsTo(TrRiskDetail::class, 'detail_id');
    // }

    public function riskMonthly()
    {
        return $this->belongsTo(TrRiskMonthly::class, 'risk_monthly_id');
    }
}
