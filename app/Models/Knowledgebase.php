<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\KnowledgeBaseReader;

class Knowledgebase extends Model
{
    protected $table = 'knowledgebase';

    protected $fillable = [
        'creator_id', 'img_path', 'doc_path','description', 'long_description', 'type','title','created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
