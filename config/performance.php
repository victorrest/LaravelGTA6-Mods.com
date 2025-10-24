<?php

return [
    'cache' => [
        'prefix' => env('PERFORMANCE_CACHE_PREFIX'),
        'default_store' => env('PERFORMANCE_CACHE_STORE', extension_loaded('redis') ? env('CACHE_STORE', 'redis') : 'array'),
        'fragment_store' => env('PERFORMANCE_FRAGMENT_CACHE_STORE', extension_loaded('redis') ? 'view' : 'array'),
        'page_store' => env('PERFORMANCE_PAGE_CACHE_STORE', extension_loaded('redis') ? 'page' : 'array'),
        'navigation_ttl' => (int) env('PERFORMANCE_NAVIGATION_TTL', 1800),
        'page_ttl' => (int) env('PERFORMANCE_PAGE_CACHE_TTL', 600),
        'fragment_ttl' => (int) env('PERFORMANCE_FRAGMENT_CACHE_TTL', 300),
    ],

    'rate_limiting' => [
        'downloads' => [
            'max_attempts' => (int) env('RATE_LIMIT_DOWNLOADS', 120),
            'decay_seconds' => (int) env('RATE_LIMIT_DOWNLOADS_DECAY', 60),
        ],
        'search' => [
            'max_attempts' => (int) env('RATE_LIMIT_SEARCH', 60),
            'decay_seconds' => (int) env('RATE_LIMIT_SEARCH_DECAY', 60),
        ],
    ],

    'metrics' => [
        'channel' => env('PERFORMANCE_METRICS_CHANNEL', 'stack'),
        'telescope' => env('PERFORMANCE_METRICS_TELESCOPE', false),
    ],
];
