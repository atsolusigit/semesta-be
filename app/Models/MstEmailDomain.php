<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class MstEmailDomain extends Model
{
    use HasFactory;

    protected $table = 'mst_email_domains';

    protected $fillable = [
        'domain',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Set status attribute - support multiple formats
     * Accepts: 1/0, true/false, "aktif"/"tidak aktif", "active"/"inactive"
     */
    public function setStatusAttribute($value)
    {
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['aktif', 'active', '1', 'true'])) {
                $this->attributes['status'] = 1;
            } elseif (in_array($value, ['tidak aktif', 'inactive', 'nonaktif', 'non aktif', '0', 'false'])) {
                $this->attributes['status'] = 0;
            } else {
                // Fallback ke boolean cast
                $this->attributes['status'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        } elseif (is_bool($value)) {
            $this->attributes['status'] = $value ? 1 : 0;
        } else {
            $this->attributes['status'] = (int) $value;
        }
    }

    /**
     * Get status as label
     */
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Aktif' : 'Tidak Aktif';
    }

    /**
     * Relationship with User (created by)
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User (updated by)
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope untuk hanya mengambil domain yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Get all active domains as array
     */
    public static function getActiveDomains()
    {
        return self::active()->pluck('domain')->toArray();
    }
}
