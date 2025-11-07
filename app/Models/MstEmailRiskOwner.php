<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MstEmailRiskOwner extends Model
{
    use HasFactory;

    protected $table = 'mst_email_unit_kerja';

    protected $fillable = [
        'unit_kerja_id',
        'unit_kerja_nama',
        'unit_kerja_email',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

     public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
