<?php

declare(strict_types=1);

$list = static fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value))));

return [
    /*
     * Load balancers / reverse proxies whose X-Forwarded-For and
     * X-Forwarded-Proto headers are believed: comma-separated IPs or CIDR
     * ranges, e.g. "10.0.0.0/8,192.168.1.10". Use "*" only when the app can't
     * be reached except through the proxy. Without this, behind a proxy every
     * client looks like the proxy's IP and every request looks like http.
     */
    'trusted_proxies' => $list((string) env('TRUSTED_PROXIES', '')),

    /*
     * Added to every response unless the response already sets the header.
     * Defaults suit a JSON API; loosen them if you also serve HTML pages.
     * Set a header to null to stop sending it.
     */
    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        // API responses often carry personal data; cache only what you opt into.
        'Cache-Control' => 'no-store',
    ],

    // Strict-Transport-Security, sent only on HTTPS requests.
    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
    ],
];
