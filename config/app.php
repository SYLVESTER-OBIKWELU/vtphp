<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'VtPhp'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    // The client app (SPA/mobile deep link) that hosts pages like
    // /reset-password; emailed links point here, not at the API.
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY'),

    // Providers registered here are booted on every request/command in the
    // order listed. Framework-internal providers are always registered first
    // via VtPhp\Foundation\CoreServiceProvider and do not need to be listed.
    'providers' => [
        \App\Providers\AppServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
    ],
];
