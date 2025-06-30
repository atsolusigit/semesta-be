<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\KnowledgeBase;

class KnowledgebaseReader extends Model
{
    protected $table = 'knowledgebase_reader';

    protected $fillable = ['user_id', 'id_knowledge'];

    public function knowledge()
    {
        return $this->belongsTo(KnowledgeBase::class, 'id_knowledge');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
