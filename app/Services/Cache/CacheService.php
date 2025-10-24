<?php

namespace App\Services\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Predis\Connection\ConnectionException as PredisConnectionException;
use Throwable;

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

        return $this->callWithStore(
            $tags,
            config('cache.default'),
            $cacheKey,
            function (Repository $store, string $key, string $resolvedStore) use ($ttl, $callback) {
                return $store->remember($key, $ttl, $callback);
            },
        );
    }

    /**
     * Store an item in the guest page cache.
     */
    public function putGuestPage(string $cacheKey, array $payload, DateTimeInterface|DateInterval|int|null $ttl = null): void
    {
        $ttl ??= config('performance.full_page_cache.ttl');

        $this->callWithStore(
            CacheTags::guestPages(),
            config('performance.full_page_cache.store'),
            $cacheKey,
            function (Repository $store, string $key, string $resolvedStore) use ($payload, $ttl) {
                $store->put($key, $payload, $ttl);

                return null;
            },
        );
    }

    /**
     * Attempt to fetch a guest page cache payload.
     */
    public function getGuestPage(string $cacheKey): ?array
    {
        $cached = $this->callWithStore(
            CacheTags::guestPages(),
            config('performance.full_page_cache.store'),
            $cacheKey,
            function (Repository $store, string $key, string $resolvedStore) {
                return $store->get($key);
            },
        );

        return is_array($cached) ? $cached : null;
    }

    /**
     * Flush all guest page cache entries.
     */
    public function flushGuestPages(): void
    {
        $this->flushTagged(CacheTags::guestPages(), config('performance.full_page_cache.store'));
    }

    /**
     * Flush entries associated with the provided tags.
     */
    public function flushTags(array $tags): void
    {
        $this->flushTagged($tags, config('cache.default'));
    }

    protected function callWithStore(
        array $tags,
        ?string $store,
        string &$cacheKey,
        callable $callback,
    ): mixed {
        $originalKey = $cacheKey;
        $resolvedStore = null;
        $repository = $this->prepareStore($tags, $store, $cacheKey, $resolvedStore);

        try {
            return $callback($repository, $cacheKey, $resolvedStore);
        } catch (Throwable $exception) {
            if (! $this->shouldFallbackToArray($resolvedStore, $exception)) {
                throw $exception;
            }

            $cacheKey = $originalKey;

            $fallbackResolved = null;
            $fallbackRepository = $this->prepareStore($tags, 'array', $cacheKey, $fallbackResolved);

            return $callback($fallbackRepository, $cacheKey, $fallbackResolved);
        }
    }

    protected function prepareStore(array $tags, ?string $store, string &$cacheKey, ?string &$resolvedStore = null): Repository
    {
        $storeName = $store ?? config('cache.default');
        $resolvedStore = $storeName;
        $repository = $this->cache->store($storeName);

        if ($tags === []) {
            return $repository;
        }

        if ($this->supportsTags($repository)) {
            return $repository->tags($tags);
        }

        $cacheKey = $this->taggedCacheKey($repository, $tags, $cacheKey, $storeName);

        return $repository;
    }

    protected function flushTagged(array $tags, ?string $store): void
    {
        $storeName = $store ?? config('cache.default');
        $repository = $this->cache->store($storeName);

        try {
            if ($tags === []) {
                $repository->flush();

                return;
            }

            if ($this->supportsTags($repository)) {
                $repository->tags($tags)->flush();

                return;
            }

            foreach ($tags as $tag) {
                $this->rotateTagVersion($repository, $tag, $storeName);
            }
        } catch (Throwable $exception) {
            if (! $this->shouldFallbackToArray($storeName, $exception)) {
                throw $exception;
            }

            $this->flushTagged($tags, 'array');
        }
    }

    protected function taggedCacheKey(Repository $repository, array $tags, string $cacheKey, string $storeName): string
    {
        $versions = array_map(
            fn (string $tag) => $this->getTagVersion($repository, $tag, $storeName),
            $tags,
        );

        $payload = [];

        foreach ($tags as $index => $tag) {
            $payload[] = $tag.':'.$versions[$index];
        }

        $hash = sha1(implode('|', $payload));

        return 'tagged:'.$hash.':'.$cacheKey;
    }

    protected function getTagVersion(Repository $repository, string $tag, string $storeName): string
    {
        $versionKey = $this->tagVersionKey($tag, $storeName);
        $version = $repository->get($versionKey);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = (string) Str::uuid();
        $repository->forever($versionKey, $version);

        return $version;
    }

    protected function rotateTagVersion(Repository $repository, string $tag, string $storeName): void
    {
        $repository->forever($this->tagVersionKey($tag, $storeName), (string) Str::uuid());
    }

    protected function tagVersionKey(string $tag, string $storeName): string
    {
        return '__tag_versions:'.$storeName.':'.$tag;
    }

    protected function supportsTags(Repository $repository): bool
    {
        return method_exists($repository, 'supportsTags') && $repository->supportsTags();
    }

    protected function shouldFallbackToArray(?string $store, Throwable $exception): bool
    {
        if ($store === null || $store === 'array' || $store === 'null') {
            return false;
        }

        if ($exception instanceof PredisConnectionException) {
            return true;
        }

        if (class_exists('\\RedisException') && $exception instanceof \RedisException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'connection refused')
            || str_contains($message, 'unable to connect')
            || str_contains($message, 'failed to connect');
    }
}
