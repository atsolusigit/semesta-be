<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MstEmailRiskOwner extends Model
{
    use HasFactory;

    protected $table = 'mst_email_unit_kerja';
    protected $primaryKey = 'unit_kerja_id';
<<<<<<< HEAD
=======
    public $incrementing = false;
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

    protected $fillable = [
        'unit_kerja_id',
        'unit_kerja_nama',
        'unit_kerja_email',
<<<<<<< HEAD
=======
        'department_id', 
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
<<<<<<< HEAD
=======

    public function department()
    {
        return $this->belongsTo(MstDepartment::class, 'department_id');
    }
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
}
