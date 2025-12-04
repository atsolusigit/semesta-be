<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LostEventUpload extends Model
{
    use HasFactory;

    protected $table = 'lost_event_uploads';

    protected $fillable = [
        'lost_event_id',
        'user_id',
        'filepath',
        'domain',
        'is_confirmed',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
    ];

    /**
     * Relasi ke lost event
     */
    public function lostEvent()
    {
        return $this->belongsTo(LostEvent::class, 'lost_event_id');
    }
}
