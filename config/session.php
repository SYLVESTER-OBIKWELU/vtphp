<?php

declare(strict_types=1);

return [
    // Supported: "database", "file", "array" (tests/CLI only, not persisted).
    // "database" needs the sessions table: run `php forge migrate`.
    'driver' => env('SESSION_DRIVER', 'database'),

    // Minutes before a session (and its cookie) expires.
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'cookie' => env('SESSION_COOKIE', 'vtphp_session'),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    'secure' => (bool) env('SESSION_SECURE_COOKIE', false),

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    // "file" driver: where session files are kept.
    'files' => storage_path('framework/sessions'),

    // "database" driver: the connection (null = default) and table.
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),

    // Chances that a request sweeps expired sessions: 2 in 100.
    'lottery' => [2, 100],
];
