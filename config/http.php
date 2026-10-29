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
];
