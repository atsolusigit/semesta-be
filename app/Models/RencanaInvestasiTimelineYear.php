<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasiTimelineYear extends Model
{
    protected $table = 'rencana_investasi_timeline_years';

    protected $fillable = [
        'erkap_id', 'year', 'timeline_json', 'source_hash', 'synced_at',
    ];

    protected $casts = [
        'timeline_json' => 'array',
        'synced_at'     => 'datetime',
    ];
}
