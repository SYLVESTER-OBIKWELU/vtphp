<?php

declare(strict_types=1);

namespace VtPhp\Routing;

final class RouteAttributeScanner
{
    /**
     * @param class-string $class
     */
    public static function scan(string $class, Router $router): void
    {
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Attributes\Route::class) as $attribute) {
                $route = $attribute->newInstance();

                $router->map([$route->method], $route->path, [$class, $method->getName()], $route->name);
            }
        }
    }
}
