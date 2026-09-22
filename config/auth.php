<?php

declare(strict_types=1);

return [
    'default' => [
        'guard' => 'api',
    ],

    'guards' => [
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'repository' => \App\Repositories\UserRepositoryInterface::class,
        ],
    ],

    'tokens' => [
        // Minutes.
        'expiration' => (int) env('AUTH_TOKEN_TTL', 60 * 24 * 30),
    ],
];
