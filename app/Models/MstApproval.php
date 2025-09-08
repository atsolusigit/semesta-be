<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstApproval extends Model
{
    protected $table = 'mst_approval';

    protected $fillable = [
        'document_id',
        'tahun',
        'posisi',
        'jabatan_id',
        'status',
        'tanggal',
        'note',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'tahun' => 'integer',
        'posisi' => 'integer',
    ];

    /**
     * Get the risk header that owns the approval.
     */
    public function riskHeader(): BelongsTo
    {
        return $this->belongsTo(TrRiskHeader::class, 'document_id', 'id');
    }

    /**
     * Get the jabatan (position) that owns the approval.
     */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(MstJabatan::class, 'jabatan_id', 'id');
    }
}
