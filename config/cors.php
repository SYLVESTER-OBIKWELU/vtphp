<?php

declare(strict_types=1);

/*
 * Cross-Origin Resource Sharing (CORS) policy.
 *
 * Off by default. Set CORS_ENABLED=true to let browsers on the origins
 * below call this API. Origins can be listed here, in CORS_ALLOWED_ORIGINS
 * (comma-separated), or both; the two lists are merged.
 */

$fromEnv = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

return [
    'enabled' => (bool) env('CORS_ENABLED', false),

    // Request paths the policy applies to (no leading slash; * is a wildcard).
    'paths' => ['api/*'],

    // Exact origins (scheme + host + optional port, no path), a wildcard
    // subdomain like 'https://*.example.com', or '*' for any origin.
    'allowed_origins' => [
        // 'https://app.example.com',
        // 'http://localhost:3000',
        ...$fromEnv,
    ],

    // ['*'] allows whatever method/headers the browser asks for.
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],

    // Response headers browser JavaScript may read.
    'exposed_headers' => ['X-Request-ID'],

    // Seconds a browser may cache a preflight response (0 = don't cache).
    'max_age' => 0,

    // Allow cookies (the session) on cross-origin requests. With '*' origins,
    // the request's own origin is echoed back, since browsers reject '*'
    // together with credentials.
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
