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
        'target_satu_tahun_option',
        'target_satu_tahun_notes',
        'target_satu_tahun_position',
        'target_quantitative_satu_tahun',
        'biaya_perlakuan_risiko',
        'residual_target_level_dampak',
        'residual_target_level_kemungkinan',
        'residual_target_posisi_risiko',
        'residual_target_level_risiko',
        'department_id',
        'year',
    ];

    protected $casts = [
        'biaya_perlakuan_risiko' => 'decimal:2',
        'target_quantitative_satu_tahun' => 'decimal:2',
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
    // RELATIONS (UPDATED)
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

    public function optionTargetSatuTahun(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_satu_tahun_option', 'id');
    }

    // Accessor untuk mengubah target_satu_tahun_option menjadi name saat di-load dengan relationship
    public function getTargetSatuTahunOptionAttribute($value)
    {
        // Jika relationship optionTargetSatuTahun sudah di-load dan ada data
        if ($this->relationLoaded('optionTargetSatuTahun') && $this->optionTargetSatuTahun) {
            return $this->optionTargetSatuTahun->name;
        }

        // Jika tidak, return ID asli
        return $value;
    }

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

    public function getTargetPositionAttribute()
    {
        return $this->target_satu_tahun_position;
    }

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
