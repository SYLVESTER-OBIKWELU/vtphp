<?php

declare(strict_types=1);

namespace VtPhp\Routing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Container\Container;
use VtPhp\Exceptions\NotFoundHttpException;
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

        if (is_string($action) && str_contains($action, '@')) {
            $action = explode('@', $action, 2);
        }

        $routeParams = $this->bindModels($action, $this->extractRouteParams($request));

        return $this->container->call($action, $routeParams);
    }

    /**
     * Implicit route-model binding: a parameter typed as an Eloquent model
     * whose name matches a route segment (`{user}` → `User $user`) receives
     * the model instead of the raw id, or the request fails with a 404.
     *
     * @param array<int, string>|string|callable $action
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function bindModels(array|string|callable $action, array $params): array
    {
        $function = is_array($action)
            ? new \ReflectionMethod($action[0], $action[1])
            : new \ReflectionFunction(\Closure::fromCallable($action));

        foreach ($function->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $parameter->getName();
            // `BlogPost $blogPost` also binds to apiResource's `{blog_post}` segment.
            $segment = array_key_exists($name, $params) ? $name : Str::snake($name);

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || !array_key_exists($segment, $params)) {
                continue;
            }

            $class = $type->getName();

            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            $params[$name] = $this->resolveModel($class, $params[$segment]);
        }

        return $params;
    }

    /**
     * @param class-string<Model> $class
     */
    private function resolveModel(string $class, mixed $value): Model
    {
        $model = new $class();

        // An integer key can never match a non-numeric segment; skip the
        // query rather than let strict databases (e.g. PostgreSQL) throw.
        $isIntKey = in_array($model->getKeyType(), ['int', 'integer'], true) && $model->getRouteKeyName() === $model->getKeyName();
        $matchable = !$isIntKey || is_int($value) || (is_string($value) && ctype_digit($value));

        $resolved = $matchable ? $model->resolveRouteBinding($value) : null;

        if (!$resolved instanceof Model) {
            throw new NotFoundHttpException((new \ReflectionClass($class))->getShortName().' not found.');
        }

        return $resolved;
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
