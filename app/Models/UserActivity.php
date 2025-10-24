<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_type',
        'subject_type',
        'subject_id',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get activity types
     */
    public const TYPE_STATUS_UPDATE = 'status_update';
    public const TYPE_MOD_UPLOAD = 'mod_upload';
    public const TYPE_COMMENT = 'comment';
    public const TYPE_FORUM_POST = 'forum_post';
    public const TYPE_LIKE = 'like';
    public const TYPE_FOLLOW = 'follow';
    public const TYPE_BOOKMARK = 'bookmark';

    /**
     * Get formatted time ago string
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
