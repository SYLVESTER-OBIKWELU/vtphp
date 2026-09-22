<?php

declare(strict_types=1);

namespace VtPhp\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;
use VtPhp\Exceptions\MethodNotAllowedHttpException;
use VtPhp\Exceptions\NotFoundHttpException;

final class Router
{
    private RouteCollection $routes;

    /** @var array<int, array{prefix: string, middleware: array<int, string>, as: string}> */
    private array $groupStack = [];

    private int $routeCount = 0;

    public function __construct(private ControllerDispatcher $dispatcher)
    {
        $this->routes = new RouteCollection();
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function get(string $uri, array|string|callable $action, ?string $name = null): void
    {
        $this->map(['GET'], $uri, $action, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function post(string $uri, array|string|callable $action, ?string $name = null): void
    {
        $this->map(['POST'], $uri, $action, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function put(string $uri, array|string|callable $action, ?string $name = null): void
    {
        $this->map(['PUT'], $uri, $action, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function patch(string $uri, array|string|callable $action, ?string $name = null): void
    {
        $this->map(['PATCH'], $uri, $action, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function delete(string $uri, array|string|callable $action, ?string $name = null): void
    {
        $this->map(['DELETE'], $uri, $action, $name);
    }

    /**
     * @param array<int, string> $methods
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    public function map(array $methods, string $uri, array|string|callable $action, ?string $name = null): void
    {
        $group = $this->currentGroup();
        $uri = rtrim(($group['prefix'] ?? '').'/'.ltrim($uri, '/'), '/') ?: '/';
        $name = $name !== null ? ($group['as'] ?? '').$name : null;
        $routeName = $name ?? 'route_'.($this->routeCount++);

        $symfonyRoute = new SymfonyRoute(
            $uri,
            defaults: [
                '_action' => $action,
                '_middleware' => $group['middleware'] ?? [],
            ],
            methods: $methods,
        );

        $this->routes->add($routeName, $symfonyRoute);
    }

    /**
     * @param array{prefix?: string, middleware?: array<int, string>, as?: string} $attributes
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $parent = $this->currentGroup();

        $this->groupStack[] = [
            'prefix' => rtrim(($parent['prefix'] ?? '').'/'.ltrim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => [...($parent['middleware'] ?? []), ...($attributes['middleware'] ?? [])],
            'as' => ($parent['as'] ?? '').($attributes['as'] ?? ''),
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * @return array{prefix?: string, middleware?: array<int, string>, as?: string}
     */
    private function currentGroup(): array
    {
        return end($this->groupStack) ?: [];
    }

    /**
     * Register all #[Route] attributed methods on a controller class.
     *
     * @param class-string $class
     */
    public function controller(string $class): void
    {
        RouteAttributeScanner::scan($class, $this);
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();

        $context = new RequestContext(method: $request->getMethod());
        $context->setHost($uri->getHost() ?: 'localhost');
        $context->setScheme($uri->getScheme() ?: 'http');

        $matcher = new UrlMatcher($this->routes, $context);

        try {
            $parameters = $matcher->match($uri->getPath() ?: '/');
        } catch (ResourceNotFoundException) {
            throw new NotFoundHttpException("Route [{$uri->getPath()}] not found.");
        } catch (MethodNotAllowedException $e) {
            throw new MethodNotAllowedHttpException($e->getAllowedMethods());
        }

        foreach ($parameters as $key => $value) {
            if (!str_starts_with((string) $key, '_')) {
                $request = $request->withAttribute((string) $key, $value);
            }
        }

        return $this->dispatcher->dispatch(
            $request,
            $parameters['_action'],
            $parameters['_middleware'] ?? [],
        );
    }
}
