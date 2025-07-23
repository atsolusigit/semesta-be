<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrRiskHeader extends Model
{
    protected $table = 'tr_risk_header';

    protected $fillable = [
        'risk_code',
        'process_code',
        'jenis_risiko',
        'sasaran',
        'peristiwa_risiko',
        'penyebab_risiko',
        'dampak_risiko',
        'inherent_risk_level_dampak',
        'inherent_risk_level_kemungkinan',
        'inherent_risk_posisi_risiko',
        'inherent_risk_level_risiko',
        'internal_control',
        'target_waktu_selesai',
        'target_waktu_selesai_option',
        'target_waktu_selesai_other',
        'target_waktu_selesai_notes',
        'target_waktu_selesai_position',
        'biaya_perlakuan_risiko',
        'residual_target_level_dampak',
        'residual_target_level_kemungkinan',
        'residual_target_posisi_risiko',
        'residual_target_level_risiko',
        'department_id',
        'year',
    ];

    protected $casts = [
        'target_waktu_selesai' => 'date',
        'biaya_perlakuan_risiko' => 'decimal:2',
    ];

    // =====================================
    // MONTHLY DATA RELATION
    // =====================================

    public function monthlyData(): HasMany
    {
        return $this->hasMany(TrRiskMonthly::class, 'header_id');
    }

    public function monthly(): HasMany
    {
        return $this->monthlyData();
    }

    // =====================================
    // RELATIONS (FIXED)
    // =====================================

    public function riskCode(): BelongsTo
    {
        return $this->belongsTo(MstRiskCode::class, 'risk_code', 'id');
    }

    public function irDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'inherent_risk_level_dampak');
    }

    public function irKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'inherent_risk_level_kemungkinan');
    }

    public function rrDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'residual_target_level_dampak');
    }

    public function rrKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'residual_target_level_kemungkinan');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(MstDepartment::class, 'department_id');
    }

    public function optionWaktuSelesai(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_waktu_selesai_option', 'name');
    }

    public function optionWaktuSelesaiPosition(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_waktu_selesai_position', 'name');
    }

    public function riskRange(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapRiskRange::class, 'risk_range_id');
    }

    // =====================================
    // HELPER METHODS
    // =====================================

    public function getMonthlyData($month)
    {
        return $this->monthlyData()->where('month', $month)->first();
    }

    public function getOrderedMonthlyData()
    {
        return $this->monthlyData()->orderBy('month')->get();
    }

    public function hasMonthlyData($month)
    {
        return $this->monthlyData()->where('month', $month)->exists();
    }

    // =====================================
    // SCOPES
    // =====================================

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeWithMonthly($query)
    {
        return $query->with('monthlyData');
    }
}
