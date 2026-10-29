<?php

declare(strict_types=1);

namespace VtPhp\Cache;

use Psr\SimpleCache\CacheInterface;
use VtPhp\Config\Repository;

/**
 * Counts attempts per key in fixed time windows, in the cache store named by
 * `rate_limiting.store` (default: the default cache store). Use a store that
 * every app server shares (database, redis) so limits hold across servers.
 *
 * Counting is read-then-write, so a burst of perfectly simultaneous requests
 * can slip a few past the limit; that is acceptable for abuse protection, not
 * for exact quotas.
 */
final class RateLimiter
{
    public function __construct(
        private readonly CacheManager $cache,
        private readonly Repository $config,
    ) {
    }

    /**
     * Record one attempt and report whether it is within the limit.
     *
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $decaySeconds = max(1, $decaySeconds);
        $now = time();
        $retryAfter = $decaySeconds - ($now % $decaySeconds);
        $windowKey = $this->windowKey($key, $decaySeconds, $now);

        $attempts = (int) $this->store()->get($windowKey, 0) + 1;
        $this->store()->set($windowKey, $attempts, $retryAfter + 1);

        return [
            'allowed' => $attempts <= $maxAttempts,
            'remaining' => max(0, $maxAttempts - $attempts),
            'retry_after' => $retryAfter,
        ];
    }

    public function attempts(string $key, int $decaySeconds): int
    {
        return (int) $this->store()->get($this->windowKey($key, max(1, $decaySeconds), time()), 0);
    }

    public function clear(string $key, int $decaySeconds): void
    {
        $this->store()->delete($this->windowKey($key, max(1, $decaySeconds), time()));
    }

    private function windowKey(string $key, int $decaySeconds, int $now): string
    {
        return 'rate:'.sha1($key).':'.intdiv($now, $decaySeconds);
    }

    private function store(): CacheInterface
    {
        $store = $this->config->get('rate_limiting.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }
}
