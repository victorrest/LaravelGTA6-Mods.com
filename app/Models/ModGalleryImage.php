<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModGalleryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'path',
        'caption',
        'position',
    ];

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->path && Str::startsWith($this->path, ['http://', 'https://'])) {
                return $this->path;
            }

            return Storage::disk('public')->url($this->path);
        });
    }
}
