<?php

declare(strict_types=1);

return [
    // Supported: "file", "array".
    'driver' => env('SESSION_DRIVER', 'file'),

    // Minutes before a session (and its cookie) expires.
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'cookie' => env('SESSION_COOKIE', 'vtphp_session'),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    'secure' => (bool) env('SESSION_SECURE_COOKIE', false),

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'files' => storage_path('framework/sessions'),
];
