<?php

namespace App\Providers;

use App\Models\ModCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

        try {
            $navigation = Cache::remember('navigation:categories', now()->addMinutes(30), function () {
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
            });
        } catch (QueryException $e) {
            // The database has not been migrated yet, fall back to config navigation.
        }

        if (empty($navigation)) {
            $navigation = Config::get('gta6.navigation', []);
        }

        View::share('siteNavigation', $navigation);
    }
}
