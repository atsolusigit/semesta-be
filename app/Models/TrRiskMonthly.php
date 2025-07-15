<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrRiskMonthly extends Model
{
    protected $table = 'tr_risk_monthly';

    protected $fillable = [
        'header_id',
        'month',
        'status_risiko',
        'start_date',
        'expired_date',

        'realization_quantitative',
        'realization_option',
        'realization_other',
        'realization_note',
        'realization_option_position',

        'target_quantitative',
        'target_option',
        'target_other',
        'target_notes',
        'target_option_position',

        'rr_level_dampak',
        'rr_level_kemungkinan',
        'rr_posisi_risiko',
        'rr_level_risiko',
    ];

    public $timestamps = true;

    //  Relasi ke Header Risiko Bulanan
    public function header(): BelongsTo
    {
        return $this->belongsTo(TrRiskHeader::class, 'risk_header_id');
    }

    // Relasi ke Mitigasi Bulanan
    public function mitigations(): HasMany
    {
        return $this->hasMany(TrMitigationMonthly::class, 'risk_monthly_id');
    }

    // Relasi ke Upload Dokumen
    public function uploads(): HasMany
    {
        return $this->hasMany(TrRiskMonthlyUpload::class, 'risk_monthly_id');
    }

    //  Relasi ke mst_option
    public function mitigationType(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'mitigation_type')
                    ->where('position', 'mitigation_type');
    }

    // Relasi ke mst_option
    public function mitigationStatus(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'mitigation_status')
                    ->where('position', 'mitigation_status');
    }

    // Relasi ke mst_option
    public function statusPic(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'status_pic')
                    ->where('position', 'status_pic');
    }

    //  Relasi ke mst_option
    public function statusAnggaran(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'status_anggaran')
                    ->where('position', 'status_anggaran');
    }

    // Relasi ke mst_option
    public function statusWaktu(): BelongsTo
    {
        return $this->belongsTo(MstOption::class, 'status_waktu')
                    ->where('position', 'status_waktu');
    }

    // Target Option
public function targetOption(): BelongsTo
{
    return $this->belongsTo(MstOption::class, 'target_option', 'name');
}

public function targetOptionPosition(): BelongsTo
{
    return $this->belongsTo(MstOption::class, 'target_option_position', 'position');
}

// Realization Option
public function realizationOption(): BelongsTo
{
    return $this->belongsTo(MstOption::class, 'realization_option', 'name');
}

public function realizationOptionPosition(): BelongsTo
{
    return $this->belongsTo(MstOption::class, 'realization_option_position', 'position');
}

}
