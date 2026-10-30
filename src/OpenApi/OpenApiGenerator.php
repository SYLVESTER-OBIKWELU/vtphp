<?php

declare(strict_types=1);

namespace VtPhp\OpenApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nyholm\Psr7\ServerRequest;
use Symfony\Component\Routing\Route;
use VtPhp\Config\Repository;
use VtPhp\Exceptions\HttpStatus;
use VtPhp\Http\FormRequest;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\Can;
use VtPhp\Middleware\RequireTokenAbility;
use VtPhp\Middleware\ThrottleRequests;
use VtPhp\OpenApi\Attributes\ApiDoc;
use VtPhp\Resources\JsonResource;
use VtPhp\Resources\ResourceCollection;
use VtPhp\Routing\Router;

/**
 * Builds an OpenAPI 3.1 document from the registered routes, with no
 * annotations needed:
 *
 * - sections (tags) from $router->group(['tag' => 'Users'], ...), or the
 *   controller's name;
 * - summary and description from the method's doc comment;
 * - path parameters from the URI and the method's parameter types;
 * - the request body (or query string, for GET) from the form request's rules();
 * - responses from the return type and the method body (resources,
 *   pagination, `response()->json([...], 201)`, `noContent()`);
 * - 401/403/404/422/429 and the login methods from the middleware.
 */
final class OpenApiGenerator
{
    private const RESOURCE_SUMMARIES = [
        'index' => 'List %s',
        'store' => 'Create a %s',
        'show' => 'Show a %s',
        'update' => 'Update a %s',
        'destroy' => 'Delete a %s',
    ];

    /** @var array<string, mixed> */
    private array $schemas = [];

    /** @var array<string, true> */
    private array $usedSecurity = [];

    private SourceInspector $source;

    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {
        $this->source = new SourceInspector();
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $this->schemas = $this->baseSchemas();
        $this->usedSecurity = [];
        $paths = [];
        $tags = [];

        foreach ($this->router->routes() as $name => $route) {
            if ($this->excluded($route)) {
                continue;
            }

            $methods = $route->getMethods() ?: ['GET'];

            foreach ($methods as $method) {
                $operation = $this->operation((string) $name, $route, strtoupper($method), count($methods) > 1);

                if ($operation === null) {
                    continue;
                }

                foreach ($operation['tags'] as $tag) {
                    $tags[$tag] ??= true;
                }

                $paths[$route->getPath()][strtolower($method)] = $operation;
            }
        }

        $descriptions = (array) $this->config->get('swagger.tags', []);
        $info = array_filter([
            'title' => (string) $this->config->get('swagger.info.title', 'API'),
            'version' => (string) $this->config->get('swagger.info.version', '1.0.0'),
            'description' => (string) $this->config->get('swagger.info.description', ''),
        ], static fn (string $v): bool => $v !== '');

        return array_filter([
            'openapi' => '3.1.0',
            'info' => $info,
            'servers' => array_values((array) $this->config->get('swagger.servers', [])),
            'tags' => array_map(
                static fn (string $tag): array => array_filter(['name' => $tag, 'description' => $descriptions[$tag] ?? null]),
                array_map('strval', array_keys($tags)),
            ),
            'paths' => $paths === [] ? new \stdClass() : $paths,
            'components' => array_filter([
                'schemas' => $this->schemas,
                'responses' => $this->errorResponses(),
                'securitySchemes' => $this->securitySchemes(),
            ]),
        ], static fn (mixed $v): bool => $v !== []);
    }

    private function excluded(Route $route): bool
    {
        $action = $route->getDefault('_action');

        if (is_array($action) && $action[0] === SwaggerController::class) {
            return true;
        }

        foreach ((array) $this->config->get('swagger.exclude', []) as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($route->getPath(), $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function operation(string $name, Route $route, string $method, bool $sharedRoute): ?array
    {
        $function = $this->reflectAction($route->getDefault('_action'));
        $doc = $this->apiDoc($function);

        if ($doc?->hidden) {
            return null;
        }

        $comment = SourceInspector::docComment($function?->getDocComment() ?? false);
        $body = $function !== null ? $this->source->body($function) : '';
        $class = $function instanceof \ReflectionMethod ? $function->getDeclaringClass() : null;
        /** @var list<string> $middleware */
        $middleware = array_values(array_filter((array) $route->getDefault('_middleware'), 'is_string'));

        $operation = [
            'tags' => $doc->tags ?? [$this->tag($route, $class)],
            'summary' => $doc->summary ?? $comment['summary'] ?? $this->defaultSummary($name, $function),
            'description' => $doc->description ?? $comment['description'],
            'operationId' => $sharedRoute ? "{$name}.".strtolower($method) : $name,
            'parameters' => $this->pathParameters($route, $function),
        ];

        $formRequest = $function !== null ? $this->formRequest($function) : null;
        $rules = $formRequest !== null ? $this->rules($formRequest) : null;

        if ($rules !== null) {
            $schema = RulesSchema::fromRules($rules);

            if (in_array($method, ['GET', 'HEAD', 'DELETE'], true)) {
                foreach ($schema['properties'] as $field => $fieldSchema) {
                    $operation['parameters'][] = [
                        'name' => $field,
                        'in' => 'query',
                        'required' => in_array($field, $schema['required'] ?? [], true),
                        'schema' => $fieldSchema,
                    ];
                }
            } else {
                $ref = $this->addSchema((new \ReflectionClass($formRequest))->getShortName(), $schema);
                $operation['requestBody'] = [
                    'required' => ($schema['required'] ?? []) !== [],
                    'content' => ['application/json' => ['schema' => $ref]],
                ];
            }
        }

        $paginates = (bool) preg_match('/->\s*(paginate|simplePaginate)\s*\(/', $body);
        $cursor = (bool) preg_match('/->\s*cursorPaginate\s*\(/', $body);

        if ($paginates) {
            $operation['parameters'][] = ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]];
        }

        if ($cursor) {
            $operation['parameters'][] = ['name' => 'cursor', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'The next_cursor or prev_cursor from the previous page.'];
        }

        if (preg_match('/->\s*perPage\s*\(\s*(\d+)?\s*(?:,\s*(\d+))?/', $body, $perPage) === 1) {
            $operation['parameters'][] = ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => (int) (($perPage[2] ?? '') !== '' ? $perPage[2] : 100),
                'default' => (int) (($perPage[1] ?? '') !== '' ? $perPage[1] : 15),
            ]];
        }

        $operation['responses'] = $this->responses($function, $class, $body, $paginates || $cursor, $method, $name);
        $this->addErrorResponses($operation, $middleware, $formRequest, $rules !== null, $body);

        foreach ($doc->responses ?? [] as $status => $description) {
            $operation['responses'][(string) $status] ??= ['description' => $description];
        }

        $security = $this->security($middleware);

        if ($security !== []) {
            $operation['security'] = $security;
        }

        if ($doc?->deprecated) {
            $operation['deprecated'] = true;
        }

        ksort($operation['responses']);

        return array_filter($operation, static fn (mixed $v): bool => $v !== null && $v !== []);
    }

    private function reflectAction(mixed $action): ?\ReflectionFunctionAbstract
    {
        if (is_string($action) && str_contains($action, '@')) {
            $action = explode('@', $action, 2);
        }

        try {
            return match (true) {
                is_array($action) && count($action) === 2 => new \ReflectionMethod($action[0], (string) $action[1]),
                $action instanceof \Closure => new \ReflectionFunction($action),
                is_string($action) && method_exists($action, '__invoke') => new \ReflectionMethod($action, '__invoke'),
                default => null,
            };
        } catch (\ReflectionException) {
            return null;
        }
    }

    private function apiDoc(?\ReflectionFunctionAbstract $function): ?ApiDoc
    {
        $attributes = $function?->getAttributes(ApiDoc::class) ?? [];

        if ($attributes === [] && $function instanceof \ReflectionMethod) {
            $attributes = $function->getDeclaringClass()->getAttributes(ApiDoc::class);
        }

        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param \ReflectionClass<object>|null $class
     */
    private function tag(Route $route, ?\ReflectionClass $class): string
    {
        $tag = $route->getDefault('_tag');

        if (is_string($tag) && $tag !== '') {
            return $tag;
        }

        if ($class !== null) {
            $base = preg_replace('/Controller$/', '', $class->getShortName()) ?: $class->getShortName();

            return Str::headline(Str::pluralStudly($base));
        }

        return 'Default';
    }

    private function defaultSummary(string $routeName, ?\ReflectionFunctionAbstract $function): string
    {
        $parts = explode('.', $routeName);
        $action = (string) end($parts);

        if (count($parts) >= 2 && isset(self::RESOURCE_SUMMARIES[$action])) {
            $resource = str_replace('_', ' ', Str::snake($parts[count($parts) - 2]));

            return sprintf(self::RESOURCE_SUMMARIES[$action], $action === 'index' ? $resource : Str::singular($resource));
        }

        return Str::ucfirst(strtolower(Str::headline($function?->getName() ?? $action)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pathParameters(Route $route, ?\ReflectionFunctionAbstract $function): array
    {
        preg_match_all('/\{(\w+)\}/', $route->getPath(), $matches);
        $parameters = [];

        foreach ($matches[1] as $segment) {
            $schema = ['type' => 'string'];
            $description = null;

            foreach ($function?->getParameters() ?? [] as $parameter) {
                if ($parameter->getName() !== $segment && Str::snake($parameter->getName()) !== $segment) {
                    continue;
                }

                $type = $parameter->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

                if ($typeName === 'int') {
                    $schema = ['type' => 'integer'];
                } elseif ($typeName === 'float') {
                    $schema = ['type' => 'number'];
                } elseif ($typeName !== null && is_subclass_of($typeName, Model::class)) {
                    $model = (new \ReflectionClass($typeName))->newInstanceWithoutConstructor();
                    $schema = in_array($model->getKeyType(), ['int', 'integer'], true) && $model->getRouteKeyName() === $model->getKeyName()
                        ? ['type' => 'integer']
                        : ['type' => 'string'];
                    $description = 'The '.Str::headline((new \ReflectionClass($typeName))->getShortName()).' '.$model->getRouteKeyName().'.';
                }
            }

            $parameters[] = array_filter([
                'name' => $segment,
                'in' => 'path',
                'required' => true,
                'description' => $description,
                'schema' => $schema,
            ], static fn (mixed $v): bool => $v !== null);
        }

        return $parameters;
    }

    /**
     * @return class-string<FormRequest>|null
     */
    private function formRequest(\ReflectionFunctionAbstract $function): ?string
    {
        foreach ($function->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                return $type->getName();
            }
        }

        return null;
    }

    /**
     * The rules of a form request, read from an empty request (so rules
     * that use route values still build).
     *
     * @param class-string<FormRequest> $class
     * @return array<string, array<int, mixed>|string>|null
     */
    private function rules(string $class): ?array
    {
        try {
            // Built directly, not through the container, so it isn't validated.
            return (new $class(new ServerRequest('GET', '/')))->rules();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param \ReflectionClass<object>|null $class
     * @return array<string, array<string, mixed>>
     */
    private function responses(?\ReflectionFunctionAbstract $function, ?\ReflectionClass $class, string $body, bool $paginated, string $method, string $routeName): array
    {
        $responses = [];
        $returnTypes = $this->returnTypes($function);

        foreach ($class !== null ? $this->source->statements($body) : [] as $statement) {
            if (preg_match('/->\s*noContent\s*\(/', $statement)) {
                $responses['204'] = ['description' => 'No content.'];

                continue;
            }

            if (preg_match('/([\\\\\w]+Resource)::(make|collection)\s*\(/', $statement, $match)) {
                $resource = $this->source->resolveClass($match[1], $class);

                if ($resource !== null && is_subclass_of($resource, JsonResource::class)) {
                    $status = preg_match('/->\s*status\s*\(\s*(\d{3})\s*\)/', $statement, $s) ? $s[1] : ($method === 'POST' && str_ends_with($routeName, '.store') ? '201' : '200');
                    $item = $this->resourceSchema($resource);
                    $responses[$status] = $this->jsonResponse(
                        $match[2] === 'collection' ? $this->collectionSchema($item, $paginated) : ['type' => 'object', 'properties' => ['data' => $item], 'required' => ['data']],
                        $status === '201' ? 'Created.' : 'Success.',
                    );
                }

                continue;
            }

            if (preg_match('/->\s*json\s*\(/', $statement, $json, PREG_OFFSET_CAPTURE)) {
                $arguments = substr($statement, (int) $json[0][1] + strlen($json[0][0]));
                $status = preg_match('/,\s*(\d{3})\s*(?:,[^()]*)?\)\s*$/', $statement, $s) ? $s[1] : '200';
                $schema = $this->source->literalSchema(ltrim($arguments)) ?? ['type' => 'object'];
                $responses[$status] ??= $this->jsonResponse($schema, $this->statusText((int) $status));
            }
        }

        if (!array_filter(array_keys($responses), static fn (int|string $s): bool => (int) $s < 300)) {
            $fallback = $this->responseFromReturnType($returnTypes, $function, $paginated);

            if ($fallback !== null) {
                [$status, $response] = $fallback;
                $responses[$status] = $response;
            }
        }

        return $responses !== [] ? $responses : ['200' => ['description' => 'Success.']];
    }

    /**
     * @return list<string>
     */
    private function returnTypes(?\ReflectionFunctionAbstract $function): array
    {
        $type = $function?->getReturnType();

        return match (true) {
            $type instanceof \ReflectionNamedType => [$type->getName()],
            $type instanceof \ReflectionUnionType => array_map(
                static fn (\ReflectionType $t): string => $t instanceof \ReflectionNamedType ? $t->getName() : 'mixed',
                $type->getTypes(),
            ),
            default => [],
        };
    }

    /**
     * @param list<string> $types
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function responseFromReturnType(array $types, ?\ReflectionFunctionAbstract $function, bool $paginated): ?array
    {
        foreach ($types as $type) {
            if (in_array($type, ['void', 'null', 'never'], true)) {
                return ['204', ['description' => 'No content.']];
            }

            if (is_subclass_of($type, JsonResource::class)) {
                return ['200', $this->jsonResponse(['type' => 'object', 'properties' => ['data' => $this->resourceSchema($type)]], 'Success.')];
            }

            if ($type === ResourceCollection::class) {
                return ['200', $this->jsonResponse($this->collectionSchema(['type' => 'object'], $paginated), 'Success.')];
            }

            if ($type === 'array') {
                return ['200', $this->jsonResponse($this->arrayShape((string) $function?->getDocComment()), 'Success.')];
            }

            if ($type === JsonResponse::class) {
                return ['200', $this->jsonResponse(['type' => 'object'], 'Success.')];
            }
        }

        return null;
    }

    /**
     * `@return array{status: string, version: string}` as a schema.
     *
     * @return array<string, mixed>
     */
    private function arrayShape(string $comment): array
    {
        if (!preg_match('/@return\s+array\{([^}]*)\}/', $comment, $match)) {
            return ['type' => 'object'];
        }

        $properties = [];

        foreach (explode(',', $match[1]) as $pair) {
            if (preg_match('/^\s*[\'"]?(\w+)[\'"]?(\?)?\s*:\s*([\w\\\\|]+)/', $pair, $p)) {
                $properties[$p[1]] = $this->phpTypeSchema($p[3]);
            }
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @return array<string, mixed>
     */
    private function phpTypeSchema(string $type): array
    {
        return match (strtolower(explode('|', $type)[0])) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean', 'true', 'false' => ['type' => 'boolean'],
            'array', 'list' => ['type' => 'array', 'items' => new \stdClass()],
            default => ['type' => 'string'],
        };
    }

    /**
     * A resource's fields, found by running toArray() on an empty model
     * (the class named in its `@property X $resource` comment) and typing
     * each value from the model's casts and the field's name.
     *
     * @param class-string<JsonResource> $resource
     * @return array<string, mixed>
     */
    private function resourceSchema(string $resource): array
    {
        $reflection = new \ReflectionClass($resource);
        $name = preg_replace('/Resource$/', '', $reflection->getShortName()) ?: $reflection->getShortName();

        if (isset($this->schemas[$name])) {
            return ['$ref' => "#/components/schemas/{$name}"];
        }

        $model = null;

        if (preg_match('/@property(?:-read)?\s+([\\\\\w]+)\s+\$resource/', (string) $reflection->getDocComment(), $match)) {
            $modelClass = $this->source->resolveClass($match[1], $reflection);
            $model = $modelClass !== null && is_subclass_of($modelClass, Model::class) ? new $modelClass() : null;
        }

        $schema = ['type' => 'object'];

        try {
            $fields = $resource::make($model ?? [])->toArray();
            $schema['properties'] = [];

            foreach ($fields as $field => $value) {
                $schema['properties'][(string) $field] = $this->valueSchema((string) $field, $value, $model);
            }
        } catch (\Throwable) {
            // A resource that needs real data to build; leave it open.
        }

        return $this->addSchema($name, $schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function valueSchema(string $field, mixed $value, ?Model $model): array
    {
        $cast = $model?->getCasts()[$field] ?? null;

        return match (true) {
            is_bool($value) => ['type' => 'boolean'],
            is_int($value) => ['type' => 'integer'],
            is_float($value) => ['type' => 'number'],
            is_array($value) && array_is_list($value) => ['type' => 'array', 'items' => new \stdClass()],
            is_array($value) => ['type' => 'object'],
            is_string($cast) && in_array($cast, ['int', 'integer'], true) => ['type' => 'integer'],
            is_string($cast) && in_array($cast, ['bool', 'boolean'], true) => ['type' => 'boolean'],
            is_string($cast) && in_array($cast, ['float', 'double', 'decimal'], true) || is_string($cast) && str_starts_with($cast, 'decimal') => ['type' => 'number'],
            is_string($cast) && in_array($cast, ['array', 'json', 'collection'], true) => ['type' => 'object'],
            is_string($cast) && (str_contains($cast, 'date') || str_contains($cast, 'timestamp')) => ['type' => ['string', 'null'], 'format' => 'date-time'],
            $field === 'id' || str_ends_with($field, '_id') || str_ends_with($field, '_count') => ['type' => 'integer'],
            str_ends_with($field, '_at') => ['type' => ['string', 'null'], 'format' => 'date-time'],
            str_starts_with($field, 'is_') || str_starts_with($field, 'has_') => ['type' => 'boolean'],
            $field === 'email' || str_ends_with($field, '_email') => ['type' => 'string', 'format' => 'email'],
            $field === 'url' || str_ends_with($field, '_url') => ['type' => 'string', 'format' => 'uri'],
            default => ['type' => 'string'],
        };
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function collectionSchema(array $item, bool $paginated): array
    {
        $schema = [
            'type' => 'object',
            'properties' => ['data' => ['type' => 'array', 'items' => $item]],
            'required' => ['data'],
        ];

        if ($paginated) {
            $schema['properties']['meta'] = ['$ref' => '#/components/schemas/PaginationMeta'];
            $schema['properties']['links'] = ['$ref' => '#/components/schemas/PaginationLinks'];
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function jsonResponse(array $schema, string $description): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array{'$ref': string}
     */
    private function addSchema(string $name, array $schema): array
    {
        $this->schemas[$name] ??= $schema;

        return ['$ref' => "#/components/schemas/{$name}"];
    }

    /**
     * @param array<string, mixed> $operation
     * @param list<string> $middleware
     * @param class-string<FormRequest>|null $formRequest
     */
    private function addErrorResponses(array &$operation, array $middleware, ?string $formRequest, bool $hasRules, string $body): void
    {
        $add = static function (string $status, string $component) use (&$operation): void {
            $operation['responses'][$status] ??= ['$ref' => "#/components/responses/{$component}"];
        };

        $classes = array_map(static fn (string $m): string => explode(':', $m, 2)[0], $middleware);

        if (in_array(Authenticate::class, $classes, true)) {
            $add('401', 'Unauthenticated');
        }

        $authorizes = in_array(Can::class, $classes, true)
            || in_array(RequireTokenAbility::class, $classes, true)
            || preg_match('/(\$this->authorize|gate\(\)\s*->\s*authorize)\s*\(/', $body) === 1
            || ($formRequest !== null && (new \ReflectionMethod($formRequest, 'authorize'))->getDeclaringClass()->getName() !== FormRequest::class);

        if ($authorizes) {
            $add('403', 'Forbidden');
        }

        foreach ($operation['parameters'] as $parameter) {
            if (($parameter['in'] ?? '') === 'path') {
                $add('404', 'NotFound');

                break;
            }
        }

        if ($hasRules) {
            $add('422', 'ValidationFailed');
        }

        if (in_array(ThrottleRequests::class, $classes, true)) {
            $add('429', 'TooManyRequests');
        }
    }

    /**
     * @param list<string> $middleware
     * @return list<array<string, list<string>>>
     */
    private function security(array $middleware): array
    {
        foreach ($middleware as $entry) {
            [$class, $parameters] = array_pad(explode(':', $entry, 2), 2, '');

            if ($class !== Authenticate::class) {
                continue;
            }

            $guards = $parameters !== '' ? explode(',', $parameters) : ['web'];
            $security = [];

            foreach ($guards as $guard) {
                $scheme = match ((string) $this->config->get("auth.guards.{$guard}.driver")) {
                    'token' => 'bearerAuth',
                    'session' => 'cookieAuth',
                    default => null,
                };

                if ($scheme !== null) {
                    $this->usedSecurity[$scheme] = true;
                    $security[] = [$scheme => []];
                }
            }

            return $security;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function securitySchemes(): array
    {
        $schemes = [];

        if (isset($this->usedSecurity['bearerAuth'])) {
            $schemes['bearerAuth'] = [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'An API token from POST /tokens, sent as "Authorization: Bearer {token}".',
            ];
        }

        if (isset($this->usedSecurity['cookieAuth'])) {
            $schemes['cookieAuth'] = [
                'type' => 'apiKey',
                'in' => 'cookie',
                'name' => (string) $this->config->get('session.cookie', 'vtphp_session'),
                'description' => 'The session cookie set by logging in. In this page, log in with "Try it out" and the browser sends it for you.',
            ];
        }

        return $schemes;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSchemas(): array
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'NOT_FOUND'],
                            'message' => ['type' => 'string'],
                            'request_id' => ['type' => 'string', 'description' => 'Quote this when reporting a problem.'],
                        ],
                        'required' => ['code', 'message'],
                    ],
                ],
                'required' => ['success', 'error'],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'VALIDATION_ERROR'],
                            'message' => ['type' => 'string', 'example' => 'The email field must be a valid email address.'],
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'example' => ['email' => ['The email field must be a valid email address.']],
                            ],
                        ],
                    ],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'per_page' => ['type' => 'integer', 'example' => 15],
                    'total' => ['type' => 'integer', 'example' => 42],
                    'last_page' => ['type' => 'integer', 'example' => 3],
                    'from' => ['type' => ['integer', 'null'], 'example' => 1],
                    'to' => ['type' => ['integer', 'null'], 'example' => 15],
                ],
            ],
            'PaginationLinks' => [
                'type' => 'object',
                'properties' => [
                    'first' => ['type' => ['string', 'null']],
                    'last' => ['type' => ['string', 'null']],
                    'prev' => ['type' => ['string', 'null']],
                    'next' => ['type' => ['string', 'null']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponses(): array
    {
        $error = fn (string $description, int $status): array => $this->jsonResponse([
            'allOf' => [['$ref' => '#/components/schemas/Error']],
            'example' => ['success' => false, 'error' => ['code' => HttpStatus::code($status), 'message' => HttpStatus::message($status)]],
        ], $description);

        return [
            'Unauthenticated' => $error('Not logged in.', 401),
            'Forbidden' => $error('Logged in, but not allowed to do this.', 403),
            'NotFound' => $error('Not found.', 404),
            'ValidationFailed' => $this->jsonResponse(['$ref' => '#/components/schemas/ValidationError'], 'The input is invalid.'),
            'TooManyRequests' => [
                'description' => 'Too many requests. Wait for the number of seconds in the Retry-After header.',
                'headers' => ['Retry-After' => ['schema' => ['type' => 'integer']]],
                'content' => ['application/json' => ['schema' => [
                    'allOf' => [['$ref' => '#/components/schemas/Error']],
                    'example' => ['success' => false, 'error' => ['code' => HttpStatus::code(429), 'message' => HttpStatus::message(429), 'retry_after' => 42]],
                ]]],
            ],
        ];
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            200 => 'Success.',
            201 => 'Created.',
            202 => 'Accepted.',
            400 => 'Bad request.',
            401 => 'Not logged in, or wrong credentials.',
            403 => 'Not allowed.',
            404 => 'Not found.',
            409 => 'Conflict.',
            422 => 'The input is invalid.',
            default => "HTTP {$status}.",
        };
    }
}
