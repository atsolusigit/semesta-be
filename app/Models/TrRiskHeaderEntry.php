<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrRiskHeaderEntry extends Model
{
    protected $table = 'tr_risk_header_entry';

    protected $fillable = [
        'tr_risk_header_id',
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
        'target_quantitative_satu_tahun' => 'decimal:2',
        'biaya_perlakuan_risiko' => 'decimal:2',
        'process_code' => 'integer',
    ];

    // --- Relasi yang sama dengan TrRiskHeader ---

    public function header(): BelongsTo
    {
        return $this->belongsTo(TrRiskHeader::class, 'tr_risk_header_id');
    }

    public function monthly_entry_data(): HasMany
    {
        return $this->hasMany(TrRiskMonthlyEntry::class, 'tr_risk_header_entry_id', 'id');
    }

    public function riskCode(): BelongsTo
    {
        return $this->belongsTo(MstRiskCode::class, 'risk_code');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(MstDepartment::class, 'department_id');
    }

    public function irDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'inherent_risk_level_dampak');
    }

    public function irKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'inherent_risk_level_kemungkinan');
    }

    public function irPosisi(): BelongsTo
    {
        return $this->belongsTo(MstHeatmap::class, 'inherent_risk_level_risiko');
    }

    public function rrDampak(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapDampak::class, 'residual_target_level_dampak');
    }

    public function rrKemungkinan(): BelongsTo
    {
        return $this->belongsTo(MstHeatmapKemungkinan::class, 'residual_target_level_kemungkinan');
    }

    public function rrPosisi(): BelongsTo
    {
        return $this->belongsTo(MstHeatmap::class, 'residual_target_level_risiko');
    }

    public function optionTargetSatuTahun(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'target_satu_tahun_option');
    }
    public function monthlyEntryData() {
    return $this->hasMany(TrRiskMonthlyEntry::class, 'tr_risk_header_entry_id', 'id');
}

}
