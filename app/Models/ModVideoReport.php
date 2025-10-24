<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModVideoReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'user_id',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public $timestamps = false;

    public function video(): BelongsTo
    {
        return $this->belongsTo(ModVideo::class, 'video_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
