<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstPage extends Model
{
    protected $table = 'mst_page';

    protected $fillable = [
        'name',
        'head_url',
        'is_web',
        'is_mobile',
        'created_by',
        'deleted_by',
        'status',
        'user_id',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_page',
            'page_id',
            'user_id'
        );
    }

   public function roles()
{
    return $this->belongsToMany(MstRole::class, 'tr_role_page', 'page_id', 'role_id')
                ->withPivot('access')
                ->withTimestamps();
}


}
