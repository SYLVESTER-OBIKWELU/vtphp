<?php

declare(strict_types=1);

return [
    // The store used by cache(). "database" needs the cache table: run
    // `php forge migrate`.
    'default' => env('CACHE_DRIVER', 'database'),
    'prefix' => env('CACHE_PREFIX', 'vtphp_cache'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('CACHE_CONNECTION'), // null = the default connection
            'table' => env('CACHE_TABLE', 'cache'),
        ],

        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_CACHE_DB', 1),
        ],
    ],
];
