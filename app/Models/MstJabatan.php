<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MstJabatan extends Model
{
    use HasFactory;

    protected $table = 'mst_jabatan';

    protected $fillable = [
        'name',
        'nipp',
        'department_id'
    ];

    /**
     * Relationship dengan MstDepartment
     */
    public function department()
    {
        return $this->belongsTo(MstDepartment::class, 'department_id');
    }

    /**
     * Relationship dengan MstApproval
     */
    public function approvals()
    {
        return $this->hasMany(MstApproval::class, 'jabatan_id');
    }
}
