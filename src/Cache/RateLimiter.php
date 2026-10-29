<?php

declare(strict_types=1);

namespace VtPhp\Cache;

use Psr\SimpleCache\CacheInterface;
use VtPhp\Config\Repository;

/**
 * Counts attempts per key, like Laravel's RateLimiter: a key's window
 * starts at its first attempt and lasts `decaySeconds`, after which the
 * count starts over. (Windows aligned to the clock would let a burst
 * straddling a boundary get twice the limit in a few seconds.)
 *
 * Counts live in the cache store named by `rate_limiting.store` (default:
 * the default cache store). Use a store every app server shares (database,
 * redis) so limits hold across servers. Counting is read-then-write, so a
 * burst of perfectly simultaneous requests can slip a few past the limit;
 * that is fine for abuse protection, not for exact quotas.
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
        $now = time();
        $state = $this->state($key, $now);

        if ($state === null) {
            $state = ['count' => 0, 'reset_at' => $now + max(1, $decaySeconds)];
        }

        ++$state['count'];
        $this->store()->set($this->cacheKey($key), $state, max(1, $state['reset_at'] - $now));

        return [
            'allowed' => $state['count'] <= $maxAttempts,
            'remaining' => max(0, $maxAttempts - $state['count']),
            'retry_after' => max(1, $state['reset_at'] - $now),
        ];
    }

    public function attempts(string $key): int
    {
        return $this->state($key, time())['count'] ?? 0;
    }

    public function clear(string $key): void
    {
        $this->store()->delete($this->cacheKey($key));
    }

    /**
     * @return array{count: int, reset_at: int}|null The live window, or null if none/expired.
     */
    private function state(string $key, int $now): ?array
    {
        $state = $this->store()->get($this->cacheKey($key));

        if (!is_array($state) || !isset($state['count'], $state['reset_at']) || (int) $state['reset_at'] <= $now) {
            return null;
        }

        return ['count' => (int) $state['count'], 'reset_at' => (int) $state['reset_at']];
    }

    private function cacheKey(string $key): string
    {
        return 'rate:'.sha1($key);
    }

    private function store(): CacheInterface
    {
        $store = $this->config->get('rate_limiting.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }
}
