<?php

namespace Tests\Feature;

use App\Models\Mod;
use App\Models\ModCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCacheTest extends TestCase
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

    public function test_home_page_is_cached_and_invalidated(): void
    {
        $category = ModCategory::factory()->create();

        $first = $this->get('/');
        $first->assertOk();
        $this->assertSame('MISS', $first->headers->get('X-Cache'));

        $second = $this->get('/');
        $second->assertOk();
        $this->assertSame('HIT', $second->headers->get('X-Cache'));

        $mod = Mod::factory()->create();
        $mod->categories()->attach($category);

        $third = $this->get('/');
        $third->assertOk();
        $this->assertSame('MISS', $third->headers->get('X-Cache'));
    }
}
