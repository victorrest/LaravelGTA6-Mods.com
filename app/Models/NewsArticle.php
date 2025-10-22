<?php

namespace App\Models;

use App\Support\EditorJs;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'body_html',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function bodyHtml(): Attribute
    {
        return Attribute::get(fn (): string => EditorJs::render($this->attributes['body'] ?? ''));
    }

    protected function bodyRaw(): Attribute
    {
        return Attribute::get(fn (): string => $this->attributes['body'] ?? '');
    }
}
