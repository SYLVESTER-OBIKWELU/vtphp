<?php

declare(strict_types=1);

namespace VtPhp\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Container\Container;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Middleware\Pipeline;
use VtPhp\Resources\JsonResource;
use VtPhp\Resources\ResourceCollection;

final class ControllerDispatcher
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     * @param array<int, string> $middleware
     */
    public function dispatch(ServerRequestInterface $request, array|string|callable $action, array $middleware = []): ResponseInterface
    {
        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(fn (ServerRequestInterface $request) => $this->toResponse($this->invoke($request, $action)));
    }

    /**
     * @param array{0: class-string, 1: string}|string|callable $action
     */
    private function invoke(ServerRequestInterface $request, array|string|callable $action): mixed
    {
        // Bind the current request into the container so it can be injected
        // by type-hint into controller constructors/methods.
        $this->container->instance(ServerRequestInterface::class, $request);
        $this->container->instance(Request::class, Request::fromPsr($request));

        $routeParams = $this->extractRouteParams($request);

        if (is_array($action)) {
            return $this->container->call($action, $routeParams);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);

            return $this->container->call([$class, $method], $routeParams);
        }

        return $this->container->call($action, $routeParams);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRouteParams(ServerRequestInterface $request): array
    {
        $params = [];

        foreach ($request->getAttributes() as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            // Coerce numeric route segments so `int $id`-typed parameters resolve cleanly.
            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }

            $params[$key] = $value;
        }

        return $params;
    }

    private function toResponse(mixed $result): ResponseInterface
    {
        return match (true) {
            $result instanceof ResponseInterface => $result,
            $result instanceof JsonResource => $result->toResponse(),
            $result instanceof ResourceCollection => $result->toResponse(),
            $result === null => new JsonResponse(null, 204),
            is_array($result), is_string($result) => new JsonResponse($result),
            default => new JsonResponse($result),
        };
    }
}
