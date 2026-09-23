<?php

declare(strict_types=1);

namespace VtPhp\Cache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use VtPhp\Config\Repository;

/**
 * Resolves and memoizes PSR-16 cache stores from `config/cache.php`.
 *
 * Redis support is provided as an adapter, not a hard framework dependency:
 * `symfony/cache` is always installed, but actually using the `redis` store
 * requires the app to additionally install `ext-redis` or `predis/predis`.
 */
final class CacheManager
{
    private const MISS = "\0__vtphp_cache_miss__\0";

    /** @var array<string, CacheInterface> */
    private array $stores = [];

    public function __construct(private readonly Repository $config)
    {
    }

    public function store(?string $name = null): CacheInterface
    {
        $name ??= (string) $this->config->get('cache.default', 'array');

        return $this->stores[$name] ??= $this->resolve($name);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->store()->set($key, $value, null);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function forget(string $key): bool
    {
        return $this->store()->delete($key);
    }

    public function flush(): bool
    {
        return $this->store()->clear();
    }

    /**
     * @param \Closure(): mixed $callback
     */
    public function remember(string $key, null|int|\DateInterval $ttl, \Closure $callback): mixed
    {
        $value = $this->store()->get($key, self::MISS);

        if ($value !== self::MISS) {
            return $value;
        }

        $value = $callback();
        $this->store()->set($key, $value, $ttl);

        return $value;
    }

    private function resolve(string $name): CacheInterface
    {
        /** @var array<string, mixed> $storeConfig */
        $storeConfig = (array) $this->config->get("cache.stores.{$name}");
        $driver = (string) ($storeConfig['driver'] ?? 'array');
        $prefix = (string) $this->config->get('cache.prefix', '');

        $adapter = match ($driver) {
            'array' => new ArrayAdapter(),
            'file' => new FilesystemAdapter($prefix, 0, (string) ($storeConfig['path'] ?? storage_path('framework/cache/data'))),
            'redis' => new RedisAdapter($this->redisConnection($storeConfig), $prefix),
            default => throw new \InvalidArgumentException("Unsupported cache driver [{$driver}] for store [{$name}]."),
        };

        return new Psr16Cache($adapter);
    }

    /**
     * @param array<string, mixed> $storeConfig
     */
    private function redisConnection(array $storeConfig): mixed
    {
        $password = $storeConfig['password'] ?? null;
        $auth = is_string($password) && $password !== '' ? rawurlencode($password).'@' : '';

        $dsn = sprintf(
            'redis://%s%s:%d/%d',
            $auth,
            (string) ($storeConfig['host'] ?? '127.0.0.1'),
            (int) ($storeConfig['port'] ?? 6379),
            (int) ($storeConfig['database'] ?? 0),
        );

        return RedisAdapter::createConnection($dsn);
    }
}
