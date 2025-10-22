<?php

namespace App\Providers;

use App\Models\ModCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
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

        if (!Schema::hasTable('mod_categories')) {
            View::share('siteNavigation', Config::get('gta6.navigation'));

            return;
        }

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

        if (empty($navigation)) {
            $navigation = Config::get('gta6.navigation');
        }

        View::share('siteNavigation', $navigation);
    }
}
