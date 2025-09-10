<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MstHeatmap;

class TrRiskMonthly extends Model
{
    protected $table = 'tr_risk_monthly';

    protected $fillable = [
        'header_id',
        'risk_code',
        'month',
        'status_risiko',
        'process_code',
        'start_date',
        'expired_date',
        'is_confirmed',

        'realization_quantitative',
        'realization_kualitatif',
        'realization_option',
        'realization_other',
        'realization_note',
        'realization_option_position',

        'target_quantitative',
        'target_kualitatif',
        'target_option',
        'target_other',
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
        'created_by',
        'updated_by',
        'updated_at',
        'finalized_by',
        'entries',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expired_date' => 'date',
        'is_finalize' => 'boolean',
    ];

    public $timestamps = true;

    // =====================================
    // MAIN RELATIONS
    // =====================================

    public function header(): BelongsTo
    {
        return $this->belongsTo(TrRiskHeader::class, 'header_id');
    }

    public function mitigations(): HasMany
    {
        return $this->hasMany(TrMitigationMonthly::class, 'risk_monthly_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(TrRiskMonthlyUpload::class, 'risk_monthly_id');
    }

    // Perbaikan relasi risk code (support multiple ID)
    public function riskCodes()
    {
        $ids = $this->risk_code_array;
        return MstRiskCode::whereIn('id', $ids)->get();
    }

    // Getter risk_code jadi array otomatis
    public function getRiskCodeArrayAttribute()
    {
        if (!$this->risk_code) return [];
        return array_filter(explode(',', $this->risk_code));
    }

    public function residualHeatmap()
    {
        return $this->belongsTo(MstHeatmapRiskRange::class, 'residual_risk_posisi_risiko', 'id');
    }

    public function residualHeatmapRange()
    {
        return MstHeatmapRiskRange::where('start', '<=', $this->residual_risk_posisi_risiko)
            ->where('end', '>=', $this->residual_risk_posisi_risiko)
            ->first();
    }

    public function entries()
    {
        return $this->hasMany(TrRiskMonthlyEntry::class, 'monthly_id');
    }

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =====================================
    // HEATMAP RELATIONS (UPDATED)
    // =====================================

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

    // =====================================
    // OPTION RELATIONS (realization & target)
    // =====================================

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

    // =====================================
    // SCOPES & METHODS
    // =====================================

    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeForHeader($query, $headerId)
    {
        return $query->where('header_id', $headerId);
    }

    public function getMonthNameAttribute()
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$this->month] ?? '';
    }
}
