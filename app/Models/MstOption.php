<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MstOption extends Model
{
    protected $table = 'mst_option';

    protected $fillable = [
        'name',
        'position',
        'type',
    ];

    public $timestamps = true;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Constants untuk type values
     */
    const TYPE_KUANTITATIF = 'kuantitatif';
    const TYPE_KUALITATIF = 'kualitatif';

    /**
     * Get available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_KUANTITATIF => 'Kuantitatif',
            self::TYPE_KUALITATIF => 'Kualitatif',
        ];
    }

    /**
     * Scope untuk filter berdasarkan type
     */
    public function scopeKuantitatif($query)
    {
        return $query->where('type', self::TYPE_KUANTITATIF);
    }

    public function scopeKualitatif($query)
    {
        return $query->where('type', self::TYPE_KUALITATIF);
    }

    /**
     * Check if option is kuantitatif
     */
    public function isKuantitatif(): bool
    {
        return $this->type === self::TYPE_KUANTITATIF;
    }

    /**
     * Check if option is kualitatif
     */
    public function isKualitatif(): bool
    {
        return $this->type === self::TYPE_KUALITATIF;
    }
}
