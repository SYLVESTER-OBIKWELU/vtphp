<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'mysql' => [
            'driver' => 'pdo_mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'dbname' => env('DB_DATABASE', 'vtphp'),
            'user' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pdo_pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 5432),
            'dbname' => env('DB_DATABASE', 'vtphp'),
            'user' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
        ],

        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'path' => env('DB_DATABASE', database_path('database.sqlite')),
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'path' => database_path('migrations'),
    ],
];
