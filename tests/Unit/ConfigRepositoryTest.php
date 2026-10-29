<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Config\Repository;

final class ConfigRepositoryTest extends TestCase
{
    public function test_it_reads_nested_values_with_dot_notation(): void
    {
        $repository = new Repository(['app' => ['name' => 'VtPhp']]);

        $this->assertSame('VtPhp', $repository->get('app.name'));
        $this->assertSame('default', $repository->get('app.missing', 'default'));
        $this->assertSame('default', $repository->get('app.name.too.deep', 'default'));
    }

    public function test_it_writes_nested_values_with_dot_notation(): void
    {
        $repository = new Repository();
        $repository->set('app.name', 'Example');

        $this->assertSame('Example', $repository->get('app.name'));
        $this->assertTrue($repository->has('app.name'));
    }

    public function test_a_literal_top_level_key_containing_dots_wins(): void
    {
        $repository = new Repository(['a.b' => 'literal', 'a' => ['b' => 'nested']]);

        $this->assertSame('literal', $repository->get('a.b'));
    }

    public function test_set_replaces_a_scalar_with_a_nested_array(): void
    {
        $repository = new Repository(['cache' => 'array']);
        $repository->set('cache.default', 'file');

        $this->assertSame(['default' => 'file'], $repository->get('cache'));
    }

    public function test_has_distinguishes_missing_keys_from_null_values(): void
    {
        $repository = new Repository(['session' => ['domain' => null]]);

        $this->assertTrue($repository->has('session.domain'));
        $this->assertFalse($repository->has('session.missing'));
    }

    public function test_all_returns_every_item(): void
    {
        $items = ['app' => ['name' => 'VtPhp'], 'mail' => ['default' => 'log']];

        $this->assertSame($items, (new Repository($items))->all());
    }
}
