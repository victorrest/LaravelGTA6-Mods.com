<?php

namespace Tests\Feature;

use App\Models\Mod;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'performance.full_page_cache.store' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);
    }

    public function test_mod_update_flushes_tagged_cache(): void
    {
        $mod = Mod::factory()->create();
        $cache = app(CacheService::class);

        $initial = $cache->remember(
            'mods:test:list',
            fn () => 'initial',
            ttl: 60,
            tags: CacheTags::mods($mod->getKey()),
        );

        $this->assertSame('initial', $initial);

        $cachedAgain = $cache->remember(
            'mods:test:list',
            fn () => 'stale',
            ttl: 60,
            tags: CacheTags::mods($mod->getKey()),
        );

        $this->assertSame('initial', $cachedAgain);

        $mod->update(['title' => 'Updated Title']);

        $fresh = $cache->remember(
            'mods:test:list',
            fn () => 'fresh',
            ttl: 60,
            tags: CacheTags::mods($mod->getKey()),
        );

        $this->assertSame('fresh', $fresh);
    }
}
