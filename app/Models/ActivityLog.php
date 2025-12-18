<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_type',
        'action',
        'table',
        'row_id',
        'description',
        'payload',
        'curl',
        'request_id',
        'ip_address',
        'status_code',
        'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
