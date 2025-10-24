<?php

namespace App\Services\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Config;

class CacheService
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly CacheKeyManager $keys
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $tags
     * @template TValue
     * @param  Closure():TValue  $callback
     * @return TValue
     */
    public function remember(
        string $namespace,
        Closure $callback,
        array $context = [],
        array $tags = [],
        DateInterval|DateTimeInterface|int|null $ttl = null,
        ?string $store = null,
        ?Authenticatable $user = null
    ): mixed {
        $key = $this->keys->resolve($namespace, $context, $tags, $user);
        $repository = $this->repository($store);

        $cache = $this->applyTags($repository, $key);

        if ($ttl === null) {
            return $cache->rememberForever($key->key(), $callback);
        }

        return $cache->remember($key->key(), $ttl, $callback);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $tags
     */
    public function forget(string $namespace, array $context = [], array $tags = [], ?string $store = null, ?Authenticatable $user = null): bool
    {
        $key = $this->keys->resolve($namespace, $context, $tags, $user);
        $repository = $this->repository($store);

        $cache = $this->applyTags($repository, $key);

        return $cache->forget($key->key());
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $tags
     */
    public function key(string $namespace, array $context = [], array $tags = [], ?Authenticatable $user = null): CacheKey
    {
        return $this->keys->resolve($namespace, $context, $tags, $user);
    }

    public function repository(?string $store = null): Repository
    {
        $store ??= Config::get('performance.cache.default_store', Config::get('cache.default'));

        return $this->cache->store($store);
    }

    /**
     * @return Repository|TaggedCache
     */
    private function applyTags(Repository $repository, CacheKey $key): Repository|TaggedCache
    {
        $tags = $key->tags();

        if (empty($tags) || ! method_exists($repository, 'tags')) {
            return $repository;
        }

        /** @phpstan-ignore-next-line */
        return $repository->tags($tags);
    }
}
