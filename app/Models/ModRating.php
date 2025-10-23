<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'user_id',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    protected static function booted(): void
    {
        $recalculate = static function (self $rating): void {
            if ($rating->relationLoaded('mod')) {
                $rating->mod?->updateRatingAggregate();
            } else {
                $rating->mod()->first()?->updateRatingAggregate();
            }
        };

        static::saved($recalculate);
        static::deleted($recalculate);
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
