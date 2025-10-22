<?php

namespace App\Support;

use Lostlink\LaravelEditorJs\Facades\LaravelEditorJs;
use Throwable;

class EditorJsRenderer
{
    public static function decode(?string $json): ?array
    {
        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['blocks']) || ! is_array($decoded['blocks'])) {
            return null;
        }

        return $decoded;
    }

    public static function renderHtml(?string $json): string
    {
        if (! is_string($json) || trim($json) === '') {
            return '';
        }

        try {
            return LaravelEditorJs::render($json);
        } catch (Throwable) {
            return nl2br(e((string) $json));
        }
    }

    public static function toPlainText(?string $json, ?array $decoded = null): string
    {
        if ($decoded === null) {
            $decoded = static::decode($json);

            if (! $decoded) {
                return static::normalizeWhitespace((string) $json);
            }
        }

        $segments = [];

        foreach ($decoded['blocks'] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            switch ($type) {
                case 'paragraph':
                case 'header':
                case 'quote':
                    $segments[] = static::normalizeWhitespace($data['text'] ?? '');
                    break;
                case 'code':
                    $segments[] = static::normalizeWhitespace($data['code'] ?? '');
                    break;
                case 'list':
                    if (isset($data['items']) && is_array($data['items'])) {
                        foreach ($data['items'] as $item) {
                            $segments[] = static::normalizeWhitespace($item);
                        }
                    }
                    break;
                case 'table':
                    if (isset($data['content']) && is_array($data['content'])) {
                        foreach ($data['content'] as $row) {
                            if (! is_array($row)) {
                                continue;
                            }

                            foreach ($row as $cell) {
                                $segments[] = static::normalizeWhitespace($cell);
                            }
                        }
                    }
                    break;
                default:
                    if (isset($data['text'])) {
                        $segments[] = static::normalizeWhitespace($data['text']);
                    }
            }
        }

        $plain = trim(implode(' ', array_filter($segments)));

        return $plain === '' ? '' : preg_replace('/\s+/u', ' ', $plain) ?? '';
    }

    protected static function normalizeWhitespace(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $text = strip_tags($value);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = trim($text);

        return $text === '' ? '' : preg_replace('/\s+/u', ' ', $text) ?? '';
    }
}
