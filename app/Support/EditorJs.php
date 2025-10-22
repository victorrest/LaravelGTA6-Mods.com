<?php

namespace App\Support;

use Illuminate\Support\Str;
use Lostlink\LaravelEditorJs\Facades\LaravelEditorJs;
use Throwable;

class EditorJs
{
    public static function render(?string $json): string
    {
        if (blank($json)) {
            return '';
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['blocks'])) {
            return nl2br(e($json));
        }

        try {
            return LaravelEditorJs::render($json);
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }
    }

    public static function toPlainText(?string $json): string
    {
        $html = static::render($json);

        if ($html === '') {
            return '';
        }

        $breakReplacements = [
            '</p>' => "\n",
            '</div>' => "\n",
            '</li>' => "\n",
            '</tr>' => "\n",
            '</h1>' => "\n",
            '</h2>' => "\n",
            '</h3>' => "\n",
            '</h4>' => "\n",
            '</h5>' => "\n",
            '</h6>' => "\n",
            '<br>' => "\n",
            '<br/>' => "\n",
            '<br />' => "\n",
        ];

        $normalized = str_replace(array_keys($breakReplacements), array_values($breakReplacements), $html);
        $text = strip_tags($normalized);
        $lines = preg_split('/\s*\n\s*/u', $text) ?: [];

        return Str::of(implode("\n", $lines))
            ->replaceMatches('/\n{2,}/u', "\n")
            ->trim()
            ->toString();
    }

    public static function decode(?string $json): array
    {
        if (blank($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
