<?php

namespace App\Providers;

use App\Models\ModCategory;
use App\Services\Cache\CacheKeyManager;
use App\Services\Cache\CacheService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CacheKeyManager::class, function ($app) {
            $prefix = $app['config']->get('performance.cache.prefix')
                ?? Str::slug((string) $app['config']->get('app.name', 'gta6mods'));

            return new CacheKeyManager($prefix);
        });

        $this->app->singleton(CacheService::class, function ($app) {
            return new CacheService(
                cache: $app->make('cache'),
                keys: $app->make(CacheKeyManager::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('siteBrand', Config::get('gta6.brand'));

        $navigation = Config::get('gta6.navigation', []);

        try {
            /** @var CacheService $cache */
            $cache = app(CacheService::class);

            $ttl = (int) Config::get('performance.cache.navigation_ttl', 1800);

            $navigation = $cache->remember(
                'navigation.categories',
                function () {
                    return ModCategory::query()
                        ->orderBy('name')
                        ->get(['name', 'slug', 'icon'])
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
                tags: ['mod-categories', 'navigation'],
                ttl: $ttl,
                store: Config::get('performance.cache.fragment_store')
            );
        } catch (QueryException $e) {
            // The database has not been migrated yet, fall back to config navigation.
        }

        if (empty($navigation)) {
            $navigation = Config::get('gta6.navigation', []);
        }

        View::share('siteNavigation', $navigation);
    }
}
