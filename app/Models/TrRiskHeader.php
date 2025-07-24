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
        // 'target_satu_tahun',
        // 'target_satu_tahun_other',
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
        'target_satu_tahun' => 'decimal:2',                    // DOUBLE(15,2) field
        'biaya_perlakuan_risiko' => 'decimal:2',
        'target_quantitative_satu_tahun' => 'decimal:2',
        'residual_target_posisi_risiko' => 'integer',
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

    // UPDATED: sesuai dengan field yang ada di migration dan ERD
    public function optionWaktuSelesai(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_satu_tahun_option', 'name');
    }

    // UPDATED: sesuai dengan field yang ada di migration
    public function optionWaktuSelesaiPosition(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_satu_tahun_position', 'name');
    }

    // NOTE: Field 'risk_range_id' tidak ada di migration,
    // jika masih dibutuhkan, tambahkan ke migration atau hapus relasi ini
    // public function riskRange(): BelongsTo
    // {
    //     return $this->belongsTo(MstHeatmapRiskRange::class, 'risk_range_id');
    // }

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
