<?php

namespace App\Observers;

use App\Models\Mod;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;

class ModObserver
{
    public function __construct(protected CacheService $cache)
    {
    }

    public function saved(Mod $mod): void
    {
        $this->flushCaches($mod);
    }

    public function deleted(Mod $mod): void
    {
        $this->flushCaches($mod);
    }

    public function restored(Mod $mod): void
    {
        $this->flushCaches($mod);
    }

    protected function flushCaches(Mod $mod): void
    {
        $mod->loadMissing('categories');

        $this->cache->flushTags(array_unique(array_merge(
            CacheTags::mods($mod->getKey()),
            CacheTags::categories($mod->categories),
            CacheTags::users($mod->user_id),
            CacheTags::home(),
        )));

        $this->cache->flushGuestPages();
    }
}
