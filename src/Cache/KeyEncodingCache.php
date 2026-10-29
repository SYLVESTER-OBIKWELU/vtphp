<?php

declare(strict_types=1);

namespace VtPhp\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Lets any string be a cache key. PSR-16 reserves `{}()/\@:`, so common
 * keys like "user:1" or "posts/latest" would throw; keys are rawurlencode()d
 * on the way in (reversibly, so distinct keys never collide) and decoded
 * when returned by getMultiple(). Plain keys such as "users_count" are
 * unchanged.
 */
final class KeyEncodingCache implements CacheInterface
{
    public function __construct(private readonly CacheInterface $store)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($this->encode($key), $default);
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->store->set($this->encode($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store->delete($this->encode($key));
    }

    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $encoded = [];

        foreach ($keys as $key) {
            $encoded[] = $this->encode($key);
        }

        $results = [];

        foreach ($this->store->getMultiple($encoded, $default) as $key => $value) {
            $results[rawurldecode((string) $key)] = $value;
        }

        return $results;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $encoded = [];

        foreach ($values as $key => $value) {
            $encoded[$this->encode((string) $key)] = $value;
        }

        return $this->store->setMultiple($encoded, $ttl);
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $encoded = [];

        foreach ($keys as $key) {
            $encoded[] = $this->encode($key);
        }

        return $this->store->deleteMultiple($encoded);
    }

    public function has(string $key): bool
    {
        return $this->store->has($this->encode($key));
    }

    private function encode(string $key): string
    {
        return rawurlencode($key);
    }
}
