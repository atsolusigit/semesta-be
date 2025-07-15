<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstOption extends Model
{
    protected $table = 'mst_option';

    protected $fillable = [
        'name',
        'position',
    ];
     public $timestamps = true;
}
