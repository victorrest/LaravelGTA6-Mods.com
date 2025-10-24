<?php

namespace App\Observers;

use App\Models\ForumThread;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;

class ForumThreadObserver
{
    public function __construct(protected CacheService $cache)
    {
    }

    public function saved(ForumThread $thread): void
    {
        $this->flushCaches($thread);
    }

    public function deleted(ForumThread $thread): void
    {
        $this->flushCaches($thread);
    }

    protected function flushCaches(ForumThread $thread): void
    {
        $this->cache->flushTags(array_unique(array_merge(
            CacheTags::forumThreads($thread->getKey()),
            CacheTags::home(),
        )));

        $this->cache->flushGuestPages();
    }
}
