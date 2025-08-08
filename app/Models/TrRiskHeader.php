<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MstHeatmap;
use App\Models\TrRiskMonthlyEntry;

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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'biaya_perlakuan_risiko' => 'decimal:2',
        'target_quantitative_satu_tahun' => 'decimal:2',
        'process_code' => 'integer',
    ];

    protected $appends = [
        'target_satu_tahun_option_name'
    ];

    // =====================================
    // AUTO INCREMENT PROCESS CODE
    // =====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->process_code)) {
                $model->process_code = static::getNextProcessCode(
                    $model->year,
                    $model->department_id
                );
            }
        });
    }

    public static function getNextProcessCode($year = null, $departmentId = null)
    {
        $query = static::query();

        if ($year) {
            $query->where('year', $year);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $lastCode = $query->max('process_code') ?? 0;
        return $lastCode + 1;
    }

    public function setNextProcessCode()
    {
        $this->process_code = static::getNextProcessCode($this->year, $this->department_id);
        return $this;
    }

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
    // RELATIONS
    // =====================================

        public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

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
    public function uploads(): HasMany
    {
        return $this->hasMany(TrRiskMonthlyUpload::class, 'header_id');
    }

   public function inherentHeatmapRange()
{
    return MstHeatmapRiskRange::where('start', '<=', $this->inherent_risk_posisi_risiko)
        ->where('end', '>=', $this->inherent_risk_posisi_risiko)
        ->first();
}

public function residualTargetHeatmapRange()
{
    return MstHeatmapRiskRange::where('start', '<=', $this->residual_target_posisi_risiko)
        ->where('end', '>=', $this->residual_target_posisi_risiko)
        ->first();
}
    public function residualHeatmap()
    {
        return $this->belongsTo(MstHeatmap::class, 'residual_risk_posisi_risiko', 'result');
    }

    public function headerEntry()
    {
        return $this->hasMany(TrRiskHeaderEntry::class, 'tr_risk_header_id', 'id');
    }

    // =====================================
    // ACCESSORS
    // =====================================

    public function getTargetSatuTahunOptionNameAttribute()
    {
        // Jika target_satu_tahun_option kosong/null
        if (!$this->target_satu_tahun_option) {
            return null;
        }

        // Jika relationship optionTargetSatuTahun sudah di-load
        if ($this->relationLoaded('optionTargetSatuTahun') && $this->optionTargetSatuTahun) {
            return $this->optionTargetSatuTahun->name;
        }

        try {
            $option = MstOption::find($this->target_satu_tahun_option);
            return $option ? $option->name : null;
        } catch (\Exception $e) {
            \Log::error('Error loading MstOption: ' . $e->getMessage());
            return null;
        }
    }

    public function getTargetPositionAttribute()
    {
        return $this->target_satu_tahun_position;
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

    public function scopeByProcessCode($query, $processCode)
    {
        return $query->where('process_code', $processCode);
    }

    public function scopeLatestProcessCode($query)
    {
        return $query->orderBy('process_code', 'desc');
    }
}
