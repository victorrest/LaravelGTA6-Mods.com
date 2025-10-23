<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TemporaryUpload extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = 'token';

    protected $keyType = 'string';

    protected $fillable = [
        'token',
        'user_id',
        'category',
        'disk',
        'relative_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'completed_at',
        'expires_at',
        'claimed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->expires_at;

        return $expiresAt instanceof CarbonInterface ? $expiresAt->isPast() : false;
    }
}
