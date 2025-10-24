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

        $featuredModsPayload = $homeData['featuredMods']->map(function (Mod $mod) {
            return [
                'title' => $mod->title,
                'author' => $mod->author?->name,
                'link' => route('mods.show', $mod),
                'image' => $mod->hero_image_url,
            ];
        })->values()->all();

        $popularModsPayload = $homeData['popularMods']->map(function (Mod $mod) {
            return [
                'title' => $mod->title,
                'author' => $mod->author?->name,
                'link' => route('mods.show', $mod),
                'image' => $mod->hero_image_url,
                'rating' => $mod->ratings_count > 0 ? number_format((float) $mod->rating, 1) : null,
                'rating_count' => $mod->ratings_count,
                'likes' => $mod->likes,
                'downloads' => $mod->downloads,
            ];
        })->values()->all();

        $latestModsPayload = $homeData['latestMods']->map(function (Mod $mod) {
            return [
                'title' => $mod->title,
                'author' => $mod->author?->name,
                'link' => route('mods.show', $mod),
                'image' => $mod->hero_image_url,
                'rating' => $mod->ratings_count > 0 ? number_format((float) $mod->rating, 1) : null,
                'rating_count' => $mod->ratings_count,
                'likes' => $mod->likes,
                'downloads' => $mod->downloads,
            ];
        })->values()->all();

        $latestNewsPayload = $homeData['latestNews']->map(function (NewsArticle $article) {
            $placeholderImage = 'https://placehold.co/400x225/111827/f9fafb?text=GTA6+News';

            return [
                'title' => $article->title,
                'link' => route('news.show', $article),
                'image' => $placeholderImage,
                'category' => 'News',
                'date' => optional($article->published_at)->format('M d, Y'),
                'summary' => $article->excerpt,
            ];
        })->values()->all();

        return view('home', [
            'featuredMods' => $homeData['featuredMods'],
            'popularMods' => $homeData['popularMods'],
            'latestMods' => $homeData['latestMods'],
            'latestNews' => $homeData['latestNews'],
            'topThreads' => $homeData['topThreads'],
            'isHome' => true,
            'homeFeedPayload' => [
                'featuredMods' => $featuredModsPayload,
                'popularMods' => $popularModsPayload,
                'latestMods' => $latestModsPayload,
                'latestNews' => $latestNewsPayload,
            ],
        ]);
    }
}
