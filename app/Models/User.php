<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'profile_img',
        'role_id',
        'jtkn',
        'fbtk',
        'department_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Relasi ke tabel mst_roles
     */
    public function role()
    {
        return $this->belongsTo(MstRole::class, 'role_id');
    }

    /**
     * Relasi ke halaman melalui departemen
     */
   public function pages()
{
    return $this->belongsToMany(MstPage::class, 'user_page', 'user_id', 'mst_page_id')
                ->withTimestamps()
                ->withPivot(['created_by']);
}

    /**
     * Relasi ke departemen (banyak ke banyak)
     */
  public function departments()
{
    return $this->belongsToMany(MstDepartment::class, 'tr_user_department', 'user_id', 'department_id')
                ->withTimestamps()
                ->withPivot(['created_by']);
}


    /**
     * (Opsional) Relasi ke divisi
     */
    // public function divisions()
    // {
    //     return $this->belongsToMany(MstDivision::class, 'tr_user_division', 'user_id', 'division_id')
    //                 ->withTimestamps();
    // }
}
