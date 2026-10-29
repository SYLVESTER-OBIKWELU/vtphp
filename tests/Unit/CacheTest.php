<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Cache\CacheManager;
use VtPhp\Config\Repository;
use VtPhp\Tests\Support\TempDirectory;

final class CacheTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-cache');
    }

    protected function tearDown(): void
    {
        TempDirectory::delete($this->tmp);
    }

    private function cache(string $default = 'array'): CacheManager
    {
        return new CacheManager(new Repository(['cache' => [
            'default' => $default,
            'prefix' => 'test',
            'stores' => [
                'array' => ['driver' => 'array'],
                'file' => ['driver' => 'file', 'path' => $this->tmp],
                'bogus' => ['driver' => 'memcached'],
            ],
        ]]));
    }

    public function test_put_get_has_and_forget(): void
    {
        $cache = $this->cache();

        $this->assertNull($cache->get('missing'));
        $this->assertSame('fallback', $cache->get('missing', 'fallback'));
        $this->assertFalse($cache->has('greeting'));

        $this->assertTrue($cache->put('greeting', 'hello', 60));
        $this->assertTrue($cache->has('greeting'));
        $this->assertSame('hello', $cache->get('greeting'));

        $this->assertTrue($cache->forget('greeting'));
        $this->assertFalse($cache->has('greeting'));
    }

    public function test_structured_values_round_trip(): void
    {
        $cache = $this->cache();
        $value = ['id' => 1, 'tags' => ['a', 'b'], 'active' => true, 'ratio' => 0.5];
        $cache->put('profile', $value);

        $this->assertSame($value, $cache->get('profile'));
    }

    public function test_a_zero_ttl_expires_immediately(): void
    {
        $cache = $this->cache();
        $cache->put('fleeting', 'x', 0);

        $this->assertFalse($cache->has('fleeting'));
    }

    public function test_remember_computes_only_once(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $compute = function () use (&$calls): string {
            ++$calls;

            return 'computed';
        };

        $this->assertSame('computed', $cache->remember('expensive', 60, $compute));
        $this->assertSame('computed', $cache->remember('expensive', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function test_remember_caches_falsy_results(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $compute = function () use (&$calls): bool {
            ++$calls;

            return false;
        };

        $cache->remember('flag', 60, $compute);
        $this->assertFalse($cache->remember('flag', 60, $compute));
        $this->assertSame(1, $calls, 'A cached false must not be recomputed.');
    }

    public function test_forever_and_flush(): void
    {
        $cache = $this->cache();
        $cache->forever('a', 1);
        $cache->forever('b', 2);

        $this->assertTrue($cache->flush());
        $this->assertFalse($cache->has('a'));
        $this->assertFalse($cache->has('b'));
    }

    public function test_the_file_store_persists_across_requests(): void
    {
        $this->cache()->store('file')->set('persisted', ['ok' => true], 60);

        $this->assertSame(['ok' => true], $this->cache()->store('file')->get('persisted'));
    }

    public function test_stores_are_memoized_and_isolated(): void
    {
        $cache = $this->cache();

        $this->assertSame($cache->store('array'), $cache->store('array'));
        $this->assertSame($cache->store(), $cache->store('array'));

        $cache->store('array')->set('only-in-array', 1);
        $this->assertFalse($cache->store('file')->has('only-in-array'));
    }

    public function test_an_unsupported_driver_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->cache()->store('bogus');
    }

    public function test_keys_may_contain_psr16_reserved_characters(): void
    {
        foreach (['array', 'file'] as $store) {
            $cache = $this->cache($store);

            foreach (['user:1', 'posts/latest', 'a@b', '{tag}', '(x)', 'back\\slash', 'with space'] as $key) {
                $cache->put($key, "value of {$key}");
                $this->assertTrue($cache->has($key), "{$store}: {$key}");
                $this->assertSame("value of {$key}", $cache->get($key), "{$store}: {$key}");
                $this->assertSame("value of {$key}", $cache->remember($key, 60, fn () => 'recomputed'), "{$store}: {$key}");
            }

            $this->assertTrue($cache->forget('user:1'));
            $this->assertFalse($cache->has('user:1'));
        }
    }

    public function test_encoded_keys_never_collide_and_multi_operations_round_trip(): void
    {
        $store = $this->cache()->store();
        $store->set('a:b', 'colon');
        $store->set('a%3Ab', 'literal percent');

        $this->assertSame('colon', $store->get('a:b'));
        $this->assertSame('literal percent', $store->get('a%3Ab'));

        $this->assertTrue($store->setMultiple(['user:1' => 'Ada', 'user:2' => 'Grace']));
        $this->assertSame(['user:1' => 'Ada', 'user:2' => 'Grace', 'user:3' => 'none'], iterator_to_array((function () use ($store) {
            yield from $store->getMultiple(['user:1', 'user:2', 'user:3'], 'none');
        })()));
        $this->assertTrue($store->deleteMultiple(['user:1', 'user:2']));
        $this->assertFalse($store->has('user:1'));
    }
}
