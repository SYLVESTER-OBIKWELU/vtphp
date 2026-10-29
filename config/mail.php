<?php

declare(strict_types=1);

return [
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // "ssl" (implicit TLS, port 465), "tls" (require STARTTLS), or
            // empty (STARTTLS when the server offers it).
            'encryption' => env('MAIL_ENCRYPTION'),
            // Seconds to wait on the SMTP server before giving up.
            'timeout' => (float) env('MAIL_TIMEOUT', 10),
        ],

        'log' => [
            'transport' => 'log',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'VtPhp'),
    ],
];
