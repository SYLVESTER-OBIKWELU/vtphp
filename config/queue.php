<?php

declare(strict_types=1);

return [
    // "sync" runs jobs immediately (no worker needed); "database" stores
    // them in the jobs table for `php forge queue:work`.
    'default' => env('QUEUE_CONNECTION', env('QUEUE_DRIVER', 'sync')),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            // Seconds before a job whose worker died is handed out again.
            // Keep it longer than your slowest job takes.
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
        ],
    ],

    'failed' => [
        'table' => 'failed_jobs',
    ],
];
