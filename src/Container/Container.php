<?php

declare(strict_types=1);

namespace VtPhp\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    /** @var array<string, string> */
    protected array $aliases = [];

    /** @var array<string, list<Closure>> */
    protected array $afterResolving = [];

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => $shared,
        ];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Run a callback whenever a freshly built object is an instance of the
     * given class or interface (e.g. to validate FormRequests on injection).
     */
    public function afterResolving(string $type, Closure $callback): void
    {
        $this->afterResolving[$type][] = $callback;
    }

    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        foreach ($this->afterResolving as $type => $callbacks) {
            if ($object instanceof $type) {
                foreach ($callbacks as $callback) {
                    $callback($object, $this);
                }
            }
        }

        if ($binding['shared'] ?? false) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function build(string $concrete, array $parameters = []): mixed
    {
        if (interface_exists($concrete)) {
            throw new BindingResolutionException("Target [{$concrete}] is an interface with no binding. Bind a concrete class to it in a service provider.");
        }

        if (!class_exists($concrete)) {
            throw new BindingResolutionException("Target class [{$concrete}] does not exist.");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target [{$concrete}] is not instantiable (abstract class or private constructor) and has no binding.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveParameters($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * @param array<int, \ReflectionParameter> $reflectionParameters
     * @param array<string, mixed> $primitives
     * @return array<int, mixed>
     */
    protected function resolveParameters(array $reflectionParameters, array $primitives = []): array
    {
        $results = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $primitives)) {
                $results[] = $primitives[$name];

                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // An optional dependency (`?Foo $foo = null`) whose type can't
                // be resolved at all falls back to its default. Failures deeper
                // in a resolvable type's own dependencies still surface.
                if (!$this->canResolve($type->getName()) && ($parameter->isDefaultValueAvailable() || $type->allowsNull())) {
                    $results[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

                    continue;
                }

                $results[] = $this->make($type->getName());

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $results[] = $parameter->getDefaultValue();

                continue;
            }

            if ($type?->allowsNull()) {
                $results[] = null;

                continue;
            }

            $declaringClass = $parameter->getDeclaringClass()?->getName() ?? 'unknown';

            throw new BindingResolutionException(
                "Unresolvable dependency \${$name} in class {$declaringClass}."
            );
        }

        return $results;
    }

    /**
     * Whether the type is bound, or is a concrete class the container can build.
     */
    private function canResolve(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->bindings[$abstract]) || isset($this->instances[$abstract])) {
            return true;
        }

        return class_exists($abstract) && (new ReflectionClass($abstract))->isInstantiable();
    }

    /**
     * Call a callable, resolving any typed parameters via the container and
     * filling in named parameters (e.g. route parameters) by name.
     *
     * @param array<int, mixed>|callable $callback
     * @param array<string, mixed> $parameters
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$objectOrClass, $method] = $callback;
            $object = is_string($objectOrClass) ? $this->make($objectOrClass) : $objectOrClass;
            $reflection = new ReflectionMethod($object, $method);
            $dependencies = $this->resolveParameters($reflection->getParameters(), $parameters);

            return $reflection->invokeArgs($object, $dependencies);
        }

        $reflection = new ReflectionFunction(Closure::fromCallable($callback));
        $dependencies = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $reflection->invokeArgs($dependencies);
    }
}
