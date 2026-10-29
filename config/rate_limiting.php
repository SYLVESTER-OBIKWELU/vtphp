<?php

declare(strict_types=1);

return [
    /*
     * Cache store that counts attempts (null = cache.default). It must be
     * shared by every app server (database or redis); "array" keeps counts
     * only for one request, which disables throttling.
     */
    'store' => env('RATE_LIMIT_STORE'),

    /*
     * Named limiters, applied to routes with ThrottleRequests::using('name').
     * by: "ip", "user" (signed-in user, else IP) or "ip+email".
     * per_route: count each endpoint separately instead of sharing one bucket.
     */
    'limiters' => [
        // Every API route, one shared budget per user (or guest IP).
        'api' => ['max_attempts' => (int) env('RATE_LIMIT_API', 60), 'decay_seconds' => 60, 'by' => 'user'],

        // Login, sign-up, password reset and verification emails, each on its
        // own: slows password guessing and stops the app spamming inboxes.
        'auth' => ['max_attempts' => (int) env('RATE_LIMIT_AUTH', 5), 'decay_seconds' => 60, 'by' => 'ip+email', 'per_route' => true],
    ],
];
