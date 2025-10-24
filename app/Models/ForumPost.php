<?php

namespace App\Models;

use App\Support\EditorJs;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'forum_thread_id',
        'user_id',
        'body',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'bool',
    ];

    protected $appends = [
        'body_html',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function bodyHtml(): Attribute
    {
        return Attribute::get(fn (): string => EditorJs::render($this->attributes['body'] ?? ''));
    }
}
