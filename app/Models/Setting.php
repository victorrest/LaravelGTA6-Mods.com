<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'value',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function get(string $key, $default = null)
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function set(string $key, $value, ?string $label = null): Setting
    {
        if (! Schema::hasTable('settings')) {
            throw new \RuntimeException('Settings tábla nem érhető el. Futtasd a migrációkat.');
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'label' => $label ?? $key,
            ]
        );
    }
}
