<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'user_id',
        'parent_id',
        'body',
    ];

    protected $withCount = ['likes'];

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ModComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ModComment::class, 'parent_id')->with('author', 'replies')->withCount('likes');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mod_comment_likes', 'mod_comment_id', 'user_id')->withTimestamps();
    }

    public function isLikedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
