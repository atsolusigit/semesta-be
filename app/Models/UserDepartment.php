<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MstDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserDepartment extends Model
{
    protected $table = 'tr_user_department';

    protected $fillable = ['department_id', 'user_id', 'created_by'];
}
