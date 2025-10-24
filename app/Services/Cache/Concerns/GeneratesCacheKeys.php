<?php

namespace App\Services\Cache\Concerns;

use App\Services\Cache\CacheKey;
use App\Services\Cache\CacheService;
use Illuminate\Contracts\Auth\Authenticatable;

trait GeneratesCacheKeys
{
    protected function cacheService(): CacheService
    {
        return app(CacheService::class);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $tags
     */
    protected function cacheKey(string $namespace, array $context = [], array $tags = [], ?Authenticatable $user = null): CacheKey
    {
        return $this->cacheService()->key($namespace, $context, $tags, $user);
    }
}
