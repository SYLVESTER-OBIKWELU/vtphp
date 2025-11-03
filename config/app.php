<?php

return [
    'name' => env('APP_NAME', 'PHP Framework'),
    'env' => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    | This allows you to register third-party SDKs and packages easily.
    |
    */

    'providers' => [
        // App\Providers\AppServiceProvider::class,
        // App\Providers\RouteServiceProvider::class,
        // App\Providers\EventServiceProvider::class,
    ],
];

