<?php

namespace App\Services\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Auth;

class CacheService
{
    public function __construct(
        protected CacheFactory $cache,
        protected CacheKeyManager $keys,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function remember(
        string $baseKey,
        Closure $callback,
        DateTimeInterface|DateInterval|int|null $ttl = null,
        array $tags = [],
        array $context = [],
        bool $perUser = false,
    ): mixed {
        if ($perUser) {
            $context['user_id'] = Auth::id() ?: 'guest';
        }

        $context['locale'] = app()->getLocale();

        $cacheKey = $this->keys->make($baseKey, $context);
        $ttl ??= config('performance.fragments.default_ttl');

        $repository = $this->repository($tags, config('cache.default'));

        return $repository->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Store an item in the guest page cache.
     */
    public function putGuestPage(string $cacheKey, array $payload, DateTimeInterface|DateInterval|int|null $ttl = null): void
    {
        $ttl ??= config('performance.full_page_cache.ttl');

        $this->repository(CacheTags::guestPages(), config('performance.full_page_cache.store'))
            ->put($cacheKey, $payload, $ttl);
    }

    /**
     * Attempt to fetch a guest page cache payload.
     */
    public function getGuestPage(string $cacheKey): ?array
    {
        $cached = $this->repository(CacheTags::guestPages(), config('performance.full_page_cache.store'))
            ->get($cacheKey);

        return is_array($cached) ? $cached : null;
    }

    /**
     * Flush all guest page cache entries.
     */
    public function flushGuestPages(): void
    {
        $this->repository(CacheTags::guestPages(), config('performance.full_page_cache.store'))->flush();
    }

    /**
     * Flush entries associated with the provided tags.
     */
    public function flushTags(array $tags): void
    {
        $this->repository($tags, config('cache.default'))->flush();
    }

    protected function repository(array $tags = [], ?string $store = null): Repository
    {
        $store = $this->cache->store($store ?? config('cache.default'));

        if ($tags !== []) {
            return $store->tags($tags);
        }

        return $store;
    }
}
