<?php

namespace App\Observers;

use App\Models\ModCategory;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;

class ModCategoryObserver
{
    public function __construct(protected CacheService $cache)
    {
    }

    public function saved(ModCategory $category): void
    {
        $this->flushCategoryCaches($category);
    }

    public function deleted(ModCategory $category): void
    {
        $this->flushCategoryCaches($category);
    }

    protected function flushCategoryCaches(ModCategory $category): void
    {
        $this->cache->flushTags(array_unique(array_merge(
            CacheTags::categories($category->getKey()),
            CacheTags::home(),
        )));

        $this->cache->flushGuestPages();
    }
}
