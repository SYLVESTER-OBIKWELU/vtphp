<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Container\BindingResolutionException;
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

interface FakeContract
{
}

final class FakeImplementation implements FakeContract
{
}

final class NeedsContract
{
    public function __construct(public FakeContract $contract)
    {
    }
}

final class WithDefaults
{
    public function __construct(public string $name = 'default', public ?int $limit = null)
    {
    }
}

final class WithPrimitive
{
    public function __construct(public string $name)
    {
    }
}

final class OptionalContract
{
    public function __construct(public ?FakeContract $contract = null)
    {
    }
}

abstract class AbstractThing
{
}

final class NullableNoDefault
{
    public function __construct(public ?FakeContract $contract)
    {
    }
}

final class OptionalBroken
{
    public function __construct(public ?NeedsContract $service = null)
    {
    }
}

final class FakeController
{
    /**
     * @return array{0: int, 1: FakeDependency}
     */
    public function show(int $id, FakeDependency $dependency): array
    {
        return [$id, $dependency];
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

    public function test_a_plain_binding_resolves_a_new_instance_each_time(): void
    {
        $container = new Container();
        $container->bind(FakeDependency::class);

        $this->assertNotSame($container->make(FakeDependency::class), $container->make(FakeDependency::class));
    }

    public function test_it_autowires_constructor_dependencies(): void
    {
        $instance = (new Container())->make(FakeService::class);

        $this->assertInstanceOf(FakeService::class, $instance);
        $this->assertInstanceOf(FakeDependency::class, $instance->dependency);
    }

    public function test_it_binds_an_interface_to_a_concrete_class(): void
    {
        $container = new Container();
        $container->bind(FakeContract::class, FakeImplementation::class);

        $this->assertInstanceOf(FakeImplementation::class, $container->make(NeedsContract::class)->contract);
    }

    public function test_closure_bindings_receive_the_container_and_parameters(): void
    {
        $container = new Container();
        $container->bind('greeting', fn (Container $c, array $parameters): string => 'Hello '.($parameters['name'] ?? 'nobody'));

        $this->assertSame('Hello Ada', $container->make('greeting', ['name' => 'Ada']));
    }

    public function test_instances_and_aliases_resolve_to_the_same_object(): void
    {
        $container = new Container();
        $object = new \stdClass();
        $container->instance('settings', $object);
        $container->alias('prefs', 'settings');

        $this->assertSame($object, $container->make('settings'));
        $this->assertSame($object, $container->make('prefs'));
        $this->assertSame($object, $container->get('prefs'));
    }

    public function test_has_reports_bindings_instances_and_existing_classes(): void
    {
        $container = new Container();
        $container->bind('bound.service', fn () => 1);
        $container->instance('instance.service', 2);

        $this->assertTrue($container->has('bound.service'));
        $this->assertTrue($container->has('instance.service'));
        $this->assertTrue($container->has(FakeDependency::class));
        $this->assertFalse($container->has('no.such.service'));
    }

    public function test_it_throws_for_an_unbound_interface(): void
    {
        $this->expectException(BindingResolutionException::class);

        (new Container())->make(FakeContract::class);
    }

    public function test_an_unbound_interface_error_does_not_claim_the_interface_is_missing(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Target ['.FakeContract::class.'] is an interface with no binding.');

        (new Container())->make(FakeContract::class);
    }

    public function test_an_abstract_class_error_explains_it_needs_a_binding(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('is not instantiable (abstract class or private constructor) and has no binding');

        (new Container())->make(AbstractThing::class);
    }

    public function test_it_throws_for_an_unknown_class(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('does not exist');

        (new Container())->make('App\\DoesNotExist');
    }

    public function test_it_throws_for_an_unresolvable_primitive(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unresolvable dependency $name');

        (new Container())->make(WithPrimitive::class);
    }

    public function test_it_uses_default_values_and_explicit_primitives(): void
    {
        $container = new Container();

        $defaults = $container->make(WithDefaults::class);
        $this->assertSame('default', $defaults->name);
        $this->assertNull($defaults->limit);

        $this->assertSame('Ada', $container->make(WithPrimitive::class, ['name' => 'Ada'])->name);
    }

    public function test_it_resolves_named_parameters_when_calling_a_closure(): void
    {
        $result = (new Container())->call(fn (int $id, FakeDependency $dependency): array => [$id, $dependency], ['id' => 5]);

        $this->assertSame(5, $result[0]);
        $this->assertInstanceOf(FakeDependency::class, $result[1]);
    }

    public function test_it_calls_a_class_method_resolving_the_class_from_the_container(): void
    {
        $result = (new Container())->call([FakeController::class, 'show'], ['id' => 7]);

        $this->assertSame(7, $result[0]);
        $this->assertInstanceOf(FakeDependency::class, $result[1]);
    }

    public function test_after_resolving_callbacks_run_for_matching_types_only(): void
    {
        $container = new Container();
        $seen = [];
        $container->afterResolving(FakeContract::class, function (FakeContract $object, Container $c) use (&$seen): void {
            $seen[] = $object::class;
        });
        $container->bind(FakeContract::class, FakeImplementation::class);
        $container->singleton('shared', fn () => new FakeImplementation());

        $container->make(FakeContract::class);
        $container->make(FakeDependency::class);
        $container->make('shared');
        $container->make('shared');

        $this->assertSame([FakeImplementation::class, FakeImplementation::class], $seen, 'Singletons fire once, unrelated types never.');
    }

    public function test_an_optional_unbound_dependency_falls_back_to_its_default(): void
    {
        $this->assertNull((new Container())->make(OptionalContract::class)->contract);
        $this->assertNull((new Container())->make(NullableNoDefault::class)->contract, 'Nullable without a default resolves to null.');
    }

    public function test_an_optional_dependency_is_injected_when_bound(): void
    {
        $container = new Container();
        $container->bind(FakeContract::class, FakeImplementation::class);

        $this->assertInstanceOf(FakeImplementation::class, $container->make(OptionalContract::class)->contract);
    }

    public function test_a_broken_resolvable_optional_dependency_still_fails_loudly(): void
    {
        // BrokenService exists and is instantiable but needs an unbound
        // interface; that real misconfiguration must not be hidden behind null.
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage(FakeContract::class);

        (new Container())->make(OptionalBroken::class);
    }
}
