<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Container\Container;

final class FakeDependency
{
}

final class FakeService
{
    public function __construct(public FakeDependency $dependency)
    {
    }
}

final class ContainerTest extends TestCase
{
    public function test_it_resolves_a_singleton_to_the_same_instance(): void
    {
        $container = new Container();
        $container->singleton(\stdClass::class);

        $this->assertSame($container->make(\stdClass::class), $container->make(\stdClass::class));
    }

    public function test_it_autowires_constructor_dependencies(): void
    {
        $container = new Container();
        $instance = $container->make(FakeService::class);

        $this->assertInstanceOf(FakeService::class, $instance);
        $this->assertInstanceOf(FakeDependency::class, $instance->dependency);
    }

    public function test_it_resolves_named_parameters_when_calling(): void
    {
        $container = new Container();

        $result = $container->call(function (int $id, FakeDependency $dependency) {
            return [$id, $dependency];
        }, ['id' => 5]);

        $this->assertSame(5, $result[0]);
        $this->assertInstanceOf(FakeDependency::class, $result[1]);
    }
}
