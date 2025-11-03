<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app/public',
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => env('FTP_PORT', 21),
            'root' => env('FTP_ROOT'),
            'passive' => true,
            'ssl' => true,
            'timeout' => 30,
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        __DIR__ . '/../public_html/storage' => __DIR__ . '/../storage/app/public',
    ],

];
