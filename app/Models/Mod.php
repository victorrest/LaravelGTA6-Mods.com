<?php

namespace App\Models;

use App\Models\ModCategory;
use App\Models\ModComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mod extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending review',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'description',
        'version',
        'hero_image_path',
        'download_url',
        'file_size',
        'rating',
        'likes',
        'downloads',
        'featured',
        'status',
        'published_at',
    ];

    protected $casts = [
        'featured' => 'bool',
        'published_at' => 'datetime',
        'file_size' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ModCategory::class)->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ModComment::class);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('downloads')->orderByDesc('likes');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function heroImageUrl(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->hero_image_path && str_starts_with($this->hero_image_path, ['http://', 'https://'])) {
                return $this->hero_image_path;
            }

            if ($this->hero_image_path) {
                return asset('storage/' . ltrim($this->hero_image_path, '/'));
            }

            return 'https://placehold.co/600x400/ec4899/1f2937?text=GTA6+Mod';
        });
    }

    protected function fileSizeLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->file_size ? number_format((float) $this->file_size, 2) . ' MB' : null);
    }

    protected function categoryNames(): Attribute
    {
        return Attribute::get(fn (): string => $this->categories->pluck('name')->join(', '));
    }
}
