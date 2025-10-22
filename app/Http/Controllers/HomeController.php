<?php

namespace App\Http\Controllers;

use App\Models\ForumThread;
use App\Models\Mod;
use App\Models\NewsArticle;
use Illuminate\Contracts\Cache\Factory as Cache;

class HomeController extends Controller
{
    public function __construct(private readonly Cache $cache)
    {
    }

    public function __invoke()
    {
        $homeData = $this->cache->remember('home:landing', now()->addMinutes(30), function () {
            return [
                'featuredMods' => Mod::query()
                    ->published()
                    ->featured()
                    ->with(['author', 'categories'])
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get(),
                'popularMods' => Mod::query()
                    ->published()
                    ->with(['author', 'categories'])
                    ->orderByDesc('downloads')
                    ->orderByDesc('likes')
                    ->limit(8)
                    ->get(),
                'latestMods' => Mod::query()
                    ->published()
                    ->with(['author', 'categories'])
                    ->orderByDesc('published_at')
                    ->limit(8)
                    ->get(),
                'latestNews' => NewsArticle::query()
                    ->with('author')
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get(),
                'topThreads' => ForumThread::query()
                    ->with('author')
                    ->latestActivity()
                    ->limit(5)
                    ->get(),
            ];
        });

        return view('home', [
            'featuredMods' => $homeData['featuredMods'],
            'popularMods' => $homeData['popularMods'],
            'latestMods' => $homeData['latestMods'],
            'latestNews' => $homeData['latestNews'],
            'topThreads' => $homeData['topThreads'],
            'isHome' => true,
        ]);
    }
}
