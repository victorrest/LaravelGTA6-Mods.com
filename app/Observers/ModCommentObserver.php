<?php

namespace App\Observers;

use App\Models\ModComment;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;

class ModCommentObserver
{
    public function __construct(protected CacheService $cache)
    {
    }

    public function saved(ModComment $comment): void
    {
        $this->flushCommentCaches($comment);
    }

    public function deleted(ModComment $comment): void
    {
        $this->flushCommentCaches($comment);
    }

    protected function flushCommentCaches(ModComment $comment): void
    {
        $this->cache->flushTags(array_unique(array_merge(
            CacheTags::comments($comment->getKey()),
            CacheTags::mods($comment->mod_id),
        )));

        $this->cache->flushGuestPages();
    }
}
