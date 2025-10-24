<?php

namespace App\Services\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class CacheKeyManager
{
    public function __construct(
        private readonly string $prefix
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $tags
     */
    public function resolve(string $namespace, array $context = [], array $tags = [], ?Authenticatable $user = null): CacheKey
    {
        $user ??= Auth::user();

        return new CacheKey(
            prefix: $this->prefix,
            namespace: $namespace,
            context: $this->normalize($context),
            user: $user,
            tags: $tags,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function normalize(array $context): array
    {
        $context = Arr::where($context, static fn ($value) => $value !== null);

        ksort($context);

        return array_map(static function ($value): string {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            try {
                return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
            } catch (\JsonException $exception) {
                return hash('sha256', serialize($value));
            }
        }, $context);
    }
}
