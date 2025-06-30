<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MstDepartment extends Model
{
    protected $table = 'mst_department';

    protected $fillable = ['name','abbreviation', 'created_by'];

    // Relasi ke User (via pivot)
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tr_user_department',
            'department_id',
            'user_id'
        );
    }

}
