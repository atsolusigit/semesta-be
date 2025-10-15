<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstRole extends Model
{
    protected $table = 'mst_role';

    protected $fillable = [
        'name',
        'description',
        'access',
        'created_by',
        'level',
        'status',
        'is_default',

    ];

   public function users()
    {
        return $this->hasMany(User::class, 'role_id'); // Perbaiki relasi
    }

    public function pages()
{
    return $this->belongsToMany(MstPage::class, 'tr_role_page', 'role_id', 'page_id')
                ->withPivot('access')
                ->withTimestamps();
}
    public function role()
{
    return $this->belongsTo(MstRole::class, 'role_id');
}

public function approvalFlows()
    {
        return $this->hasMany(RoleApprovalFlow::class, 'role_id');
    }

     public function canApprove()
    {
        return $this->hasMany(RoleApprovalFlow::class, 'can_approve_role_id');
    }

// Relasi many-to-many dengan Permission melalui tabel pivot role_permissions
    public function permissions()
{
    return $this->belongsToMany(
        \App\Models\Permission::class,
        'role_permissions',
        'role_id',
        'permission_id'
    );
}

}

