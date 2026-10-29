<?php

declare(strict_types=1);

namespace VtPhp\Routing;

use Illuminate\Support\Str;
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

    /** @var array<int, array{prefix: string, middleware: array<int, string>, as: string, tag: ?string}> */
    private array $groupStack = [];

    private int $routeCount = 0;

    public function __construct(private ControllerDispatcher $dispatcher)
    {
        $this->routes = new RouteCollection();
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function get(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $this->map(['GET'], $uri, $action, $name, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function post(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $this->map(['POST'], $uri, $action, $name, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function put(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $this->map(['PUT'], $uri, $action, $name, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function patch(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $this->map(['PATCH'], $uri, $action, $name, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function delete(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $this->map(['DELETE'], $uri, $action, $name, $middleware);
    }

    /**
     * @param array<int, string> $methods
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, class-string> $middleware
     */
    public function map(array $methods, string $uri, array|string|callable $action, ?string $name = null, array $middleware = []): void
    {
        $group = $this->currentGroup();
        $uri = rtrim(($group['prefix'] ?? '').'/'.ltrim($uri, '/'), '/') ?: '/';
        $name = $name !== null ? ($group['as'] ?? '').$name : null;
        $routeName = $name ?? 'route_'.($this->routeCount++);

        $symfonyRoute = new SymfonyRoute(
            $uri,
            defaults: [
                '_action' => $action,
                '_middleware' => [...($group['middleware'] ?? []), ...$middleware],
                // The API docs section (Swagger tag) this route is listed under.
                '_tag' => $group['tag'] ?? null,
            ],
            methods: $methods,
        );

        $this->routes->add($routeName, $symfonyRoute);
    }

    /**
     * Routes registered inside $callback share these attributes. `tag` names
     * the section they're listed under in the API docs (/api-docs); an inner
     * group's tag replaces its parent's.
     *
     * @param array{prefix?: string, middleware?: array<int, string>, as?: string, tag?: string} $attributes
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $parent = $this->currentGroup();

        $this->groupStack[] = [
            'prefix' => rtrim(($parent['prefix'] ?? '').'/'.ltrim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => [...($parent['middleware'] ?? []), ...($attributes['middleware'] ?? [])],
            'as' => ($parent['as'] ?? '').($attributes['as'] ?? ''),
            'tag' => $attributes['tag'] ?? $parent['tag'] ?? null,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * @return array{prefix?: string, middleware?: array<int, string>, as?: string, tag?: ?string}
     */
    private function currentGroup(): array
    {
        return end($this->groupStack) ?: [];
    }

    /**
     * Register Laravel-style API resource routes:
     *
     *     $router->apiResource('users', UserController::class);
     *
     *     GET       /users           users.index    index()
     *     POST      /users           users.store    store()
     *     GET       /users/{user}    users.show     show()
     *     PUT|PATCH /users/{user}    users.update   update()
     *     DELETE    /users/{user}    users.destroy  destroy()
     *
     * @param class-string $controller
     * @param array{only?: array<int, string>, except?: array<int, string>, middleware?: array<int, class-string>, parameter?: string, tag?: string} $options
     */
    public function apiResource(string $name, string $controller, array $options = []): void
    {
        if (isset($options['tag'])) {
            $tag = $options['tag'];
            unset($options['tag']);
            $this->group(['tag' => $tag], fn () => $this->apiResource($name, $controller, $options));

            return;
        }

        $base = '/'.trim($name, '/');
        $parameter = $options['parameter'] ?? Str::singular(str_replace('-', '_', basename($base)));
        $routeName = str_replace('/', '.', trim($name, '/'));

        $actions = [
            'index' => [['GET'], $base],
            'store' => [['POST'], $base],
            'show' => [['GET'], "{$base}/{{$parameter}}"],
            'update' => [['PUT', 'PATCH'], "{$base}/{{$parameter}}"],
            'destroy' => [['DELETE'], "{$base}/{{$parameter}}"],
        ];

        $only = $options['only'] ?? array_keys($actions);
        $except = $options['except'] ?? [];

        foreach ($actions as $action => [$methods, $uri]) {
            if (!in_array($action, $only, true) || in_array($action, $except, true)) {
                continue;
            }

            $this->map($methods, $uri, [$controller, $action], "{$routeName}.{$action}", $options['middleware'] ?? []);
        }
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
