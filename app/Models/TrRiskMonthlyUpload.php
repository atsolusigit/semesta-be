<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrRiskMonthlyUpload extends Model
{
    protected $table = 'tr_risk_monthly_upload';
    protected $fillable = [
        'header_id',
        'risk_monthly_id',
        'risk_monthly_entry_id',
        'filepath',
        'domain',
    ];

    public function header()
    {
        return $this->belongsTo(TrRiskHeader::class, 'header_id');
    }

    public function riskMonthly()
    {
        return $this->belongsTo(TrRiskMonthly::class, 'risk_monthly_id');
    }
}
