<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LostEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lost_events';

    protected $fillable = [
        'header_id',
        'tahun',
        'risk_owner_department',
        'jenis_risiko',
        'nama_kejadian',
        'identifikasi_kejadian',
        'kategori_kejadian',
        'sumber_penyebab_kejadian',
        'penyebab_kejadian',
        'penanganan_saat_kejadian',
        'deskripsi_kejadian',
        'pihak_terkait',
        'status_asuransi',
        'kategori_risiko_bumn',
        'kategori_risiko_t2_t3_kbumn',
        'penjelasan_kerugian',
        'nilai_kerugian',
        'kejadian_berulang',
        'frekuensi_kejadian',
        'mitigasi_yang_direncanakan',
        'realisasi_mitigasi',
        'perbaikan_mendatang',
        'nilai_premi',
        'nilai_klaim',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'nilai_kerugian' => 'decimal:2',
        'nilai_premi' => 'decimal:2',
        'nilai_klaim' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke TrRiskHeader
     */
    public function header()
    {
        return $this->belongsTo(TrRiskHeader::class, 'header_id');
    }

    /**
     * Relasi ke User yang membuat
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke User yang mengupdate
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope untuk filter berdasarkan tahun
     */
    public function scopeFilterByYear($query, $year)
    {
        if ($year) {
            return $query->where('tahun', $year);
        }
        return $query;
    }

    /**
     * Scope untuk filter berdasarkan department
     */
    public function scopeFilterByDepartment($query, $department)
    {
        if ($department) {
            return $query->where('risk_owner_department', 'like', '%' . $department . '%');
        }
        return $query;
    }

    /**
     * Scope untuk filter berdasarkan jenis risiko
     */
    public function scopeFilterByJenisRisiko($query, $jenisRisiko)
    {
        if ($jenisRisiko) {
            return $query->where('jenis_risiko', 'like', '%' . $jenisRisiko . '%');
        }
        return $query;
    }

    /**
     * Scope untuk search global
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('nama_kejadian', 'like', '%' . $search . '%')
                  ->orWhere('identifikasi_kejadian', 'like', '%' . $search . '%')
                  ->orWhere('deskripsi_kejadian', 'like', '%' . $search . '%');
            });
        }
        return $query;
    }
}
