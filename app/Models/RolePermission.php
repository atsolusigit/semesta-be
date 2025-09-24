<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $fillable = [
        'role_id',
        'permission_id',
        'created_by',
        'created_at',
        'updated_at',
    ];

    public function role()
    {
        return $this->belongsTo(\App\Models\MstRole::class, 'role_id');
    }

    public function permission()
    {
        return $this->belongsTo(\App\Models\Permission::class, 'permission_id');
    }
}
