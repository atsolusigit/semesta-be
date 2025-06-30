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
        'created_at',
        'updated_at'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'tr_role_page','role_id','page_id');
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

}

