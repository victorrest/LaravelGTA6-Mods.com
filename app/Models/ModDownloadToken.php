<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ModDownloadToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'user_id',
        'token',
        'is_external',
        'external_url',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function wasUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function markUsed(): void
    {
        if ($this->wasUsed()) {
            return;
        }

        $this->forceFill(['used_at' => now()])->save();
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
