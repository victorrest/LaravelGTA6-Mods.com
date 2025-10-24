<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => env('HORIZON_REDIS_CONNECTION', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:critical' => env('HORIZON_WAIT_CRITICAL', 10),
        'redis:default' => env('HORIZON_WAIT_DEFAULT', 60),
        'redis:media' => env('HORIZON_WAIT_MEDIA', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 120,
        'pending' => 120,
        'completed' => 120,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 20160,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 72,
            'queue' => 168,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => true,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => env('HORIZON_MEMORY_LIMIT', 256),

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'throughput' => [
            'connection' => 'redis',
            'queue' => ['critical', 'default'],
            'balance' => env('HORIZON_BALANCE_STRATEGY', 'auto'),
            'autoScalingStrategy' => 'time',
            'minProcesses' => env('HORIZON_THROUGHPUT_MIN', 16),
            'maxProcesses' => env('HORIZON_THROUGHPUT_MAX', 80),
            'balanceMaxShift' => 5,
            'balanceCooldown' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 90,
            'nice' => 0,
        ],
        'notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications', 'mail'],
            'balance' => 'auto',
            'minProcesses' => env('HORIZON_NOTIFICATIONS_MIN', 4),
            'maxProcesses' => env('HORIZON_NOTIFICATIONS_MAX', 16),
            'balanceMaxShift' => 2,
            'balanceCooldown' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 192,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'media' => [
            'connection' => 'redis',
            'queue' => ['media', 'images'],
            'balance' => 'auto',
            'minProcesses' => env('HORIZON_MEDIA_MIN', 6),
            'maxProcesses' => env('HORIZON_MEDIA_MAX', 24),
            'balanceMaxShift' => 2,
            'balanceCooldown' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 2,
            'timeout' => 300,
            'nice' => 0,
        ],
        'low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'auto',
            'minProcesses' => env('HORIZON_LOW_MIN', 2),
            'maxProcesses' => env('HORIZON_LOW_MAX', 12),
            'balanceMaxShift' => 1,
            'balanceCooldown' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'throughput' => [
                'minProcesses' => 40,
                'maxProcesses' => 80,
                'balanceMaxShift' => 8,
                'balanceCooldown' => 2,
            ],
            'notifications' => [
                'minProcesses' => 6,
                'maxProcesses' => 20,
            ],
            'media' => [
                'minProcesses' => 8,
                'maxProcesses' => 24,
                'timeout' => 420,
            ],
            'low' => [
                'minProcesses' => 3,
                'maxProcesses' => 16,
            ],
        ],

        'staging' => [
            'throughput' => [
                'minProcesses' => 8,
                'maxProcesses' => 24,
            ],
            'notifications' => [
                'minProcesses' => 2,
                'maxProcesses' => 8,
            ],
            'media' => [
                'minProcesses' => 2,
                'maxProcesses' => 8,
            ],
            'low' => [
                'minProcesses' => 1,
                'maxProcesses' => 4,
            ],
        ],

        'local' => [
            'throughput' => [
                'minProcesses' => 1,
                'maxProcesses' => 4,
            ],
            'notifications' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'media' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'low' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
        ],
    ],
];
