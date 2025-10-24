<?php

namespace App\Services\Cache;

use Illuminate\Http\Request;
use JsonException;

class CacheKeyManager
{
    /**
     * Build a normalized cache key for the given base name and context.
     *
     * @param  array<string, mixed>  $context
     */
    public function make(string $baseKey, array $context = []): string
    {
        if ($context === []) {
            return $baseKey;
        }

        ksort($context);

        try {
            $suffix = sha1(json_encode($context, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            $suffix = sha1(serialize($context));
        }

        return $baseKey.':'.$suffix;
    }

    /**
     * Build a normalized request context for cache keys.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function contextFromRequest(Request $request, array $extra = []): array
    {
        $context = array_merge([
            'host' => $request->getHost(),
            'path' => $request->getPathInfo(),
            'query' => $request->query(),
        ], $extra);

        array_walk_recursive($context, static function (&$value): void {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
        });

        return $context;
    }
}
