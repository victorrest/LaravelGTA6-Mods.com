<?php

return [
    'server' => env('OCTANE_SERVER', 'swoole'),

    'https' => env('OCTANE_HTTPS', false),

    'listen_ip' => env('OCTANE_LISTEN_IP', '127.0.0.1'),

    'listen_port' => env('OCTANE_LISTEN_PORT', 8000),

    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

    'memory_limit' => env('OCTANE_MEMORY_LIMIT', 512),

    'watch' => env('OCTANE_WATCH', false),

    'watch_directories' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'routes',
        'resources/views',
    ],

    'watch_timer' => env('OCTANE_WATCH_TIMER', 1000),

    'warm' => env('OCTANE_WARM', false),

    'max_execution_time' => env('OCTANE_MAX_EXECUTION_TIME', 30),

    'swoole' => [
        'options' => [
            'package_max_length' => env('OCTANE_SWOOLE_PACKAGE_MAX_LENGTH', 10 * 1024 * 1024),
            'buffer_output_size' => env('OCTANE_SWOOLE_BUFFER_OUTPUT_SIZE', 10 * 1024 * 1024),
            'max_coroutine' => env('OCTANE_SWOOLE_MAX_COROUTINE', 100000),
            'worker_num' => env('OCTANE_MAX_WORKERS', 72),
            'task_worker_num' => env('OCTANE_TASK_WORKERS', 8),
            'task_enable_coroutine' => true,
            'task_max_request' => env('OCTANE_TASK_MAX_REQUEST', 1000),
            'reload_async' => true,
            'enable_preemptive_scheduler' => true,
            'max_wait_time' => env('OCTANE_SWOOLE_MAX_WAIT_TIME', 3),
        ],
    ],

    'roadrunner' => [
        'max_execution_time' => env('OCTANE_MAX_EXECUTION_TIME', 30),
        'rpc' => env('OCTANE_ROADRUNNER_RPC', 'tcp://127.0.0.1:6001'),
        'workers' => [
            'relay' => env('OCTANE_ROADRUNNER_RELAY', 'pipes'),
            'command' => env('OCTANE_ROADRUNNER_WORKER_COMMAND'),
            'count' => env('OCTANE_MAX_WORKERS', 72),
            'max_jobs' => env('OCTANE_MAX_REQUESTS', 500),
        ],
    ],

    'cache' => [
        'stores' => [
            'octane' => [
                'driver' => env('OCTANE_CACHE_DRIVER', 'redis'),
                'connection' => env('OCTANE_CACHE_CONNECTION', env('REDIS_CACHE_CONNECTION', 'cache')),
            ],
        ],
    ],

    'tables' => [
        'authorization:sessions' => [
            'size' => 10240,
            'columns' => [
                ['name' => 'user_id', 'type' => 'string', 'size' => 64],
                ['name' => 'expires_at', 'type' => 'int'],
            ],
        ],
    ],

    'workers' => [
        'state' => env('OCTANE_WORKER_STATE_DRIVER', 'in_memory'),
    ],

    'maintenance' => [
        'store' => env('OCTANE_MAINTENANCE_STORE', env('APP_MAINTENANCE_STORE', 'database')),
    ],
];
