<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePage extends Model
{
    protected $table = 'tr_role_page';

    protected $fillable = ['name', 'created_by','access','role_id', 'page_id', 'created_at', 'updated_at'];

    public function pages()
    {
        return $this->belongsToMany(MstPage::class, 'tr_role_page', 'role_id', 'page_id');
    }
    public function roles()
{
    return $this->belongsToMany(MstRole::class, 'tr_role_page', 'page_id', 'role_id')
                ->withPivot('access')
                ->withTimestamps();
}

}
