<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ModComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'user_id',
        'parent_id',
        'body',
        'status',
    ];

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';

    protected static ?bool $statusColumnExists = null;

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
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function scopeApproved($query)
    {
        if (! self::statusColumnIsAvailable()) {
            return $query;
        }

        return $query->where('status', self::STATUS_APPROVED);
    }

    public static function statusColumnIsAvailable(): bool
    {
        if (self::$statusColumnExists === null) {
            self::$statusColumnExists = Schema::hasColumn((new self())->getTable(), 'status');
        }

        return self::$statusColumnExists;
    }
}
