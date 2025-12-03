<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Knowledgebase;

class KnowledgeUpload extends Model
{
    protected $table = "knowledge_uploads";

    protected $fillable = [
        'knowledge_id',
        'type',
        'path',
        'filename',
        'created_by',
    ];
    public function knowledge()
    {
        return $this->belongsTo(Knowledgebase::class, 'knowledge_id');
    }
}
