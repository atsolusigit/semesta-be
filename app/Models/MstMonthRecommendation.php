<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstMonthRecommendation extends Model
{
    use HasFactory;
    protected $table = 'mst_month_recommendation';

    protected $fillable = ['name', 'required', 'created_by', 'updated_by'];

    public $timestamps = true;

    protected $casts = [
        'required' => 'boolean',
    ];

       public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')
                    ->select(['id', 'name', 'username']);
    }

   public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function riskMonthlies()
    {
        return $this->hasMany(TrRiskMonthly::class, 'month', 'id');
    }

}
