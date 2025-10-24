<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ModVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'submitted_by',
        'youtube_id',
        'youtube_url',
        'video_title',
        'video_description',
        'duration',
        'thumbnail_path',
        'thumbnail_attachment_id',
        'status',
        'position',
        'is_featured',
        'featured_at',
        'report_count',
        'submitted_at',
        'moderated_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'featured_at' => 'datetime',
        'submitted_at' => 'datetime',
        'moderated_at' => 'datetime',
    ];

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ModVideoReport::class, 'video_id');
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        // Fall back to YouTube thumbnail
        return "https://i.ytimg.com/vi/{$this->youtube_id}/maxresdefault.jpg";
    }

    public function getThumbnailSmallUrlAttribute(): string
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg";
    }

    public function getThumbnailLargeUrlAttribute(): string
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return "https://i.ytimg.com/vi/{$this->youtube_id}/sddefault.jpg";
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'reported']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
