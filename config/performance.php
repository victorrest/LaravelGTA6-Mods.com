<?php

return [
    'full_page_cache' => [
        'enabled' => env('FULL_PAGE_CACHE_ENABLED', true),
        'ttl' => env('FULL_PAGE_CACHE_TTL', 300),
        'store' => env('FULL_PAGE_CACHE_STORE', env('CACHE_STORE', 'redis')),
        'vary_on_locale' => env('FULL_PAGE_CACHE_VARY_LOCALE', true),
    ],

    'fragments' => [
        'default_ttl' => env('FRAGMENT_CACHE_TTL', 900),
        'navigation_ttl' => env('FRAGMENT_CACHE_NAVIGATION_TTL', 1800),
        'home_ttl' => env('FRAGMENT_CACHE_HOME_TTL', 600),
    ],

    'queries' => [
        'default_ttl' => env('QUERY_CACHE_TTL', 300),
    ],

    'query_log' => [
        'enabled' => env('PERFORMANCE_QUERY_LOG', false),
        'threshold' => env('PERFORMANCE_QUERY_THRESHOLD_MS', 50),
        'channel' => env('PERFORMANCE_QUERY_LOG_CHANNEL', 'performance'),
    ],
];
