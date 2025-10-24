<?php

return [
    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => env('HORIZON_CONNECTION', env('REDIS_QUEUE_CONNECTION', 'queue')),

    'guard' => env('HORIZON_AUTH_GUARD', 'web'),

    'prefix' => env('HORIZON_PREFIX', env('REDIS_PREFIX')),

    'allowed_emails' => array_filter(array_map('trim', explode(',', (string) env('HORIZON_ALLOWED_EMAILS')))),

    'waits' => [
        'redis:default' => env('HORIZON_WAIT_TIME_DEFAULT', 60),
        'redis:mail' => env('HORIZON_WAIT_TIME_MAIL', 120),
        'redis:notifications' => env('HORIZON_WAIT_TIME_NOTIFICATIONS', 60),
    ],

    'trim' => [
        'recent' => env('HORIZON_TRIM_RECENT', 720),
        'failed' => env('HORIZON_TRIM_FAILED', 10080),
    ],

    'fast_termination' => env('HORIZON_FAST_TERMINATION', true),

    'memory_limit' => env('HORIZON_MEMORY_LIMIT', 512),

    'defaults' => [
        'supervisor-default' => [
            'connection' => env('HORIZON_CONNECTION', env('REDIS_QUEUE_CONNECTION', 'queue')),
            'queue' => ['default'],
            'balance' => env('HORIZON_BALANCE_STRATEGY', 'auto'),
            'minProcesses' => env('HORIZON_MIN_PROCESSES', 1),
            'maxProcesses' => env('HORIZON_MAX_PROCESSES', 10),
            'nice' => env('HORIZON_NICE', 0),
        ],

        'supervisor-ampere' => [
            'connection' => env('HORIZON_CONNECTION', env('REDIS_QUEUE_CONNECTION', 'queue')),
            'queue' => explode(',', env('HORIZON_QUEUE', 'default,mail,notifications,search,image-processing')),
            'balance' => env('HORIZON_BALANCE_STRATEGY', 'auto'),
            'minProcesses' => env('HORIZON_MIN_PROCESSES', 40),
            'maxProcesses' => env('HORIZON_MAX_PROCESSES', 96),
            'maxTime' => env('HORIZON_MAX_TIME', 0),
            'maxJobs' => env('HORIZON_MAX_JOBS', 1000),
            'retry' => env('HORIZON_RETRY_AFTER', 90),
            'nice' => env('HORIZON_NICE', 0),
        ],

        'supervisor-maintenance' => [
            'connection' => env('HORIZON_CONNECTION', env('REDIS_QUEUE_CONNECTION', 'queue')),
            'queue' => ['maintenance'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'retry' => 300,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => ['supervisor-ampere', 'supervisor-maintenance'],
        'staging' => ['supervisor-ampere'],
        'local' => ['supervisor-default'],
    ],

    'metrics' => [
        'trim_snapshots' => env('HORIZON_TRIM_SNAPSHOTS', 48),
        'trim_telemetry' => env('HORIZON_TRIM_TELEMETRY', 168),
    ],

    'notifications' => [
        'slack' => [
            'webhook_url' => env('HORIZON_SLACK_WEBHOOK'),
            'channel' => env('HORIZON_SLACK_CHANNEL'),
        ],
    ],
];
