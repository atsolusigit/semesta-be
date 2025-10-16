<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MstJenisRisiko extends Model
{
    use HasFactory;

    protected $table = 'mst_jenis_risiko';

    protected $fillable = [
        'nama_jenis_risiko',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
// Relasi dengan User (created_by)
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
