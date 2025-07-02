<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\KnowledgeBaseReader;

class KnowledgeBase extends Model
{
    protected $table = 'knowledgebase';

    protected $fillable = [
        'creator_id', 'img_path', 'description', 'long_description', 'type'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
