<?php

declare(strict_types=1);

return [
    'default' => [
        'guard' => 'web',
    ],

    // Supported drivers: "session".
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    // Supported drivers: "eloquent". Omit a provider's config entirely to
    // use a container-bound VtPhp\Auth\UserProviderInterface instead.
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => \App\Models\User::class,
        ],
    ],
];
