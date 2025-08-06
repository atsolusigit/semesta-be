<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrRiskMonthlyEntry extends Model
{
    protected $table = 'tr_risk_monthly_entry';

    protected $fillable = [
    'header_id',
    'monthly_id',
    'tr_risk_header_entry_id',
    'month',
    'risk_code',
    'status_risiko',
    'process_code',
    'start_date',
    'expired_date',

    'realization_quantitative',
    'realization_option',
    'realization_note',
    'realization_option_position',

    'target_quantitative',
    'target_option',
    'target_notes',
    'target_option_position',

    'residual_risk_level_dampak',
    'residual_risk_level_kemungkinan',
    'residual_risk_posisi_risiko',
    'residual_risk_level_risiko',

    'residual_risk_satutahun_level_dampak',
    'residual_risk_satutahun_level_kemungkinan',
    'residual_risk_satutahun_posisi_risiko',
    'residual_risk_satutahun_level_risiko',

    'is_finalize',
    'finalized_at',
    'finalized_by',
    'created_by',
];

    protected $casts = [
        'start_date' => 'date',
        'expired_date' => 'date',
        'realization_quantitative' => 'decimal:2',
        'target_quantitative' => 'decimal:2',
    ];

    public $timestamps = true;

    // ============================
    // RELATIONS
    // ============================

    public function monthly(): BelongsTo
    {
        return $this->belongsTo(TrRiskMonthly::class, 'monthly_id');
    }

    public function riskCode(): BelongsTo
    {
        return $this->belongsTo(MstRiskCode::class, 'risk_code', 'id');
    }

    public function rrLevelDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'residual_risk_level_dampak');
    }

    public function rrLevelKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'residual_risk_level_kemungkinan');
    }

    public function rrYearLevelDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'residual_risk_satutahun_level_dampak');
    }

    public function rrYearLevelKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'residual_risk_satutahun_level_kemungkinan');
    }

    public function targetOption(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_option');
    }

    public function targetOptionPosition(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_option_position', 'position');
    }

    public function realizationOption(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'realization_option');
    }

    public function realizationOptionPosition(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'realization_option_position', 'position');
    }
    public function headerEntry()
    {
        return $this->belongsTo(TrRiskHeaderEntry::class, 'tr_risk_header_entry_id','id');
    }
    public function uploads()
    {
        return $this->hasMany(TrRiskMonthlyUpload::class, 'risk_monthly_entry_id', 'id');
    }
    public function header()
    {
        return $this->belongsTo(TrRiskHeader::class, 'header_id');
    }


}
