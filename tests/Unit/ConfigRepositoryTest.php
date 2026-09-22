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
    }

    public function test_it_writes_nested_values_with_dot_notation(): void
    {
        $repository = new Repository();
        $repository->set('app.name', 'Example');

        $this->assertSame('Example', $repository->get('app.name'));
        $this->assertTrue($repository->has('app.name'));
    }
}
