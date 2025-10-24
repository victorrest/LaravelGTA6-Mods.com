<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModVideo extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'mod_id',
        'user_id',
        'platform',
        'external_id',
        'title',
        'slug',
        'thumbnail_path',
        'duration',
        'channel_title',
        'position',
        'status',
        'payload',
        'approved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::creating(function (ModVideo $video) {
            if (empty($video->slug)) {
                $video->slug = Str::slug(Str::limit($video->title, 90), '-');
            }
        });
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        if (Str::startsWith($this->thumbnail_path, ['http://', 'https://'])) {
            return $this->thumbnail_path;
        }

        return Storage::disk('public')->url($this->thumbnail_path);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED)->orderBy('position');
    }
}
