<?php

namespace App\Providers;

use App\Models\ForumThread;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModComment;
use App\Models\NewsArticle;
use App\Observers\ForumThreadObserver;
use App\Observers\ModCategoryObserver;
use App\Observers\ModCommentObserver;
use App\Observers\ModObserver;
use App\Observers\NewsArticleObserver;
use App\Services\Cache\CacheService;
use App\Services\Cache\CacheTags;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('siteBrand', Config::get('gta6.brand'));

        $navigation = Config::get('gta6.navigation', []);

        /** @var CacheService $cache */
        $cache = app(CacheService::class);

        try {
            $navigation = $cache->remember(
                'navigation:categories',
                function () {
                    return ModCategory::query()
                        ->orderBy('name')
                        ->get(['id', 'name', 'slug', 'icon'])
                        ->map(function (ModCategory $category) {
                        $icon = $category->icon ?: 'fa-star';
                        $icon = trim(str_replace('fa-solid', '', $icon));
                        $icon = trim(preg_replace('/\s+/', ' ', $icon));

                        return [
                            'slug' => $category->slug,
                            'label' => $category->name,
                            'icon' => $icon,
                        ];
                        })->toArray();
                },
                ttl: config('performance.fragments.navigation_ttl'),
                tags: CacheTags::categories(),
            );
        } catch (QueryException $e) {
            // The database has not been migrated yet, fall back to config navigation.
        }

        if (empty($navigation)) {
            $navigation = Config::get('gta6.navigation', []);
        }

        View::share('siteNavigation', $navigation);

        Mod::observe(ModObserver::class);
        ModCategory::observe(ModCategoryObserver::class);
        ModComment::observe(ModCommentObserver::class);
        NewsArticle::observe(NewsArticleObserver::class);
        ForumThread::observe(ForumThreadObserver::class);

        RateLimiter::for('downloads', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ? 'downloads:user:'.$request->user()->getAuthIdentifier()
                : 'downloads:ip:'.$request->ip();

            return Limit::perMinutes(5, 10)
                ->by($key)
                ->response(fn () => back()->with('downloadError', 'Please wait before requesting another download.'));
        });

        RateLimiter::for('mods-browse', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ? 'mods:user:'.$request->user()->getAuthIdentifier()
                : 'mods:ip:'.$request->ip();

            $limit = $request->query->count() > 0
                ? Limit::perMinute(60)
                : Limit::perMinute(120);

            return $limit->by($key);
        });

        if (config('performance.query_log.enabled')) {
            $threshold = (float) config('performance.query_log.threshold', 50);
            $channel = config('performance.query_log.channel', 'performance');

            DB::listen(function ($event) use ($threshold, $channel) {
                if ($event->time < $threshold) {
                    return;
                }

                Log::channel($channel)->info('slow-query', [
                    'sql' => $event->sql,
                    'bindings' => $event->bindings,
                    'time_ms' => $event->time,
                    'connection' => $event->connectionName,
                ]);
            });
        }
    }
}
