<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'flair',
        'body',
        'replies_count',
        'last_posted_at',
        'pinned',
        'locked',
    ];

    protected $casts = [
        'pinned' => 'bool',
        'locked' => 'bool',
        'last_posted_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    public function scopeLatestActivity(Builder $query): Builder
    {
        return $query->orderByDesc('pinned')->orderByDesc('last_posted_at')->orderByDesc('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
