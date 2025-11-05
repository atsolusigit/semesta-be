<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationInvestasiNotif extends Model
{
    protected $table = 'rekomendasi_rencana_investasi';

    protected $fillable = [
        'erkap_id',
        'nama_investasi',
        'tahun',
        'rekomendasi',
        'kirim_ke',
        'status',
        'dikirim_oleh',
        'created_at',
        'created_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
