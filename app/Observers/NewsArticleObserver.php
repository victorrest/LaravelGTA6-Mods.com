<?php

namespace App\Observers;

use App\Models\NewsArticle;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;

class NewsArticleObserver
{
    public function __construct(protected CacheService $cache)
    {
    }

    public function saved(NewsArticle $article): void
    {
        $this->flushCaches($article);
    }

    public function deleted(NewsArticle $article): void
    {
        $this->flushCaches($article);
    }

    protected function flushCaches(NewsArticle $article): void
    {
        $this->cache->flushTags(array_unique(array_merge(
            CacheTags::news($article->getKey()),
            CacheTags::home(),
        )));

        $this->cache->flushGuestPages();
    }
}
