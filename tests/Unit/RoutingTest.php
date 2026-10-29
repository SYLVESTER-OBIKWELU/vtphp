<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Container\Container;
use VtPhp\Exceptions\MethodNotAllowedHttpException;
use VtPhp\Exceptions\NotFoundHttpException;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Resources\JsonResource;
use VtPhp\Routing\Attributes\Route;
use VtPhp\Routing\ControllerDispatcher;
use VtPhp\Routing\Router;

final class RouteFixtureController
{
    /**
     * @return array{id: int}
     */
    #[Route(method: 'GET', path: '/things/{id}', name: 'things.show')]
    public function show(int $id): array
    {
        return ['id' => $id];
    }

    #[Route(method: 'POST', path: '/things', name: 'things.store', middleware: [AddHeaderMiddleware::class])]
    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['name' => $request->input('name')], 201);
    }

    /**
     * @return array{legacy: bool}
     */
    public function legacy(): array
    {
        return ['legacy' => true];
    }
}

final class AddHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Route-Middleware', 'yes');
    }
}

final class OrderOuterMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    public static array $log = [];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        self::$log[] = 'group';

        return $handler->handle($request);
    }
}

final class OrderInnerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        OrderOuterMiddleware::$log[] = 'route';

        return $handler->handle($request);
    }
}

final class ThingResource extends JsonResource
{
    public function toArray(): array
    {
        return ['name' => $this->resource['name']];
    }
}

final class RoutingTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new ControllerDispatcher(new Container()));
        OrderOuterMiddleware::$log = [];
    }

    private function dispatch(string $method, string $path, ?string $json = null): ResponseInterface
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, 'http://localhost'.$path);

        if ($json !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')->withBody($factory->createStream($json));
        }

        return $this->router->dispatch($request);
    }

    private function json(ResponseInterface $response): mixed
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function test_a_closure_route_returning_an_array_becomes_a_json_response(): void
    {
        $this->router->get('/ping', fn (): array => ['pong' => true]);

        $response = $this->dispatch('GET', '/ping');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['pong' => true], $this->json($response));
    }

    public function test_numeric_route_parameters_are_injected_as_ints(): void
    {
        $this->router->get('/users/{id}', fn (int $id): array => ['id' => $id, 'type' => get_debug_type($id)]);

        $this->assertSame(['id' => 42, 'type' => 'int'], $this->json($this->dispatch('GET', '/users/42')));
    }

    public function test_string_route_parameters_are_injected_by_name(): void
    {
        $this->router->get('/posts/{slug}', fn (string $slug): array => ['slug' => $slug]);

        $this->assertSame(['slug' => 'hello-world'], $this->json($this->dispatch('GET', '/posts/hello-world')));
    }

    public function test_each_verb_helper_registers_its_method(): void
    {
        $this->router->get('/r', fn () => ['m' => 'get']);
        $this->router->post('/r', fn () => ['m' => 'post']);
        $this->router->put('/r', fn () => ['m' => 'put']);
        $this->router->patch('/r', fn () => ['m' => 'patch']);
        $this->router->delete('/r', fn () => ['m' => 'delete']);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertSame(['m' => strtolower($method)], $this->json($this->dispatch($method, '/r')));
        }
    }

    public function test_an_unknown_path_throws_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->dispatch('GET', '/missing');
    }

    public function test_a_wrong_method_throws_method_not_allowed_with_the_allowed_methods(): void
    {
        $this->router->post('/only-post', fn () => []);

        try {
            $this->dispatch('GET', '/only-post');
            $this->fail('Expected MethodNotAllowedHttpException.');
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertSame(['allowed_methods' => ['POST']], $e->getContext());
        }
    }

    public function test_groups_apply_prefix_name_and_middleware(): void
    {
        $this->router->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => [OrderOuterMiddleware::class]], function (Router $router): void {
            $router->get('/users', fn () => [], 'users', [OrderInnerMiddleware::class]);

            $router->group(['prefix' => 'reports', 'as' => 'reports.'], function (Router $router): void {
                $router->get('daily', fn () => [], 'daily');
            });
        });

        $users = $this->router->routes()->get('admin.users');
        $this->assertNotNull($users);
        $this->assertSame('/admin/users', $users->getPath());
        $this->assertSame([OrderOuterMiddleware::class, OrderInnerMiddleware::class], $users->getDefault('_middleware'));

        $daily = $this->router->routes()->get('admin.reports.daily');
        $this->assertNotNull($daily);
        $this->assertSame('/admin/reports/daily', $daily->getPath());
        $this->assertSame([OrderOuterMiddleware::class], $daily->getDefault('_middleware'));
    }

    public function test_group_middleware_runs_before_route_middleware(): void
    {
        $this->router->group(['middleware' => [OrderOuterMiddleware::class]], function (Router $router): void {
            $router->get('/ordered', fn () => [], null, [OrderInnerMiddleware::class]);
        });

        $this->dispatch('GET', '/ordered');

        $this->assertSame(['group', 'route'], OrderOuterMiddleware::$log);
    }

    public function test_paths_are_normalised(): void
    {
        $this->router->get('/', fn () => ['root' => true]);
        $this->router->get('/trailing/', fn () => ['trailing' => true]);

        $this->assertSame(['root' => true], $this->json($this->dispatch('GET', '/')));
        $this->assertSame(['trailing' => true], $this->json($this->dispatch('GET', '/trailing')));
    }

    public function test_unnamed_routes_get_unique_generated_names(): void
    {
        $this->router->get('/a', fn () => []);
        $this->router->get('/b', fn () => []);

        $names = array_keys($this->router->routes()->all());

        $this->assertCount(2, array_unique($names));
        $this->assertStringStartsWith('route_', $names[0]);
    }

    public function test_attribute_routes_are_registered_from_a_controller(): void
    {
        $this->router->controller(RouteFixtureController::class);

        $this->assertNotNull($this->router->routes()->get('things.show'));
        $this->assertNotNull($this->router->routes()->get('things.store'));
        $this->assertCount(2, $this->router->routes(), 'Methods without #[Route] must not be registered.');

        $this->assertSame(['id' => 5], $this->json($this->dispatch('GET', '/things/5')));

        $created = $this->dispatch('POST', '/things', '{"name":"Widget"}');
        $this->assertSame(201, $created->getStatusCode());
        $this->assertSame(['name' => 'Widget'], $this->json($created));
        $this->assertSame('yes', $created->getHeaderLine('X-Route-Middleware'));
    }

    public function test_class_at_method_string_actions_are_supported(): void
    {
        $this->router->get('/legacy', RouteFixtureController::class.'@legacy');

        $this->assertSame(['legacy' => true], $this->json($this->dispatch('GET', '/legacy')));
    }

    public function test_return_values_are_converted_to_responses(): void
    {
        $this->router->get('/null', fn () => null);
        $this->router->get('/string', fn () => 'hello');
        $this->router->get('/response', fn () => new JsonResponse(['teapot' => true], 418));
        $this->router->get('/resource', fn () => ThingResource::make(['name' => 'Widget'])->status(202));
        $this->router->get('/collection', fn () => ThingResource::collection([['name' => 'A'], ['name' => 'B']]));

        $null = $this->dispatch('GET', '/null');
        $this->assertSame(204, $null->getStatusCode());
        $this->assertSame('', (string) $null->getBody());
        $this->assertFalse($null->hasHeader('Content-Type'));

        $this->assertSame('hello', $this->json($this->dispatch('GET', '/string')));
        $this->assertSame(418, $this->dispatch('GET', '/response')->getStatusCode());

        $resource = $this->dispatch('GET', '/resource');
        $this->assertSame(202, $resource->getStatusCode());
        $this->assertSame(['data' => ['name' => 'Widget']], $this->json($resource));

        $this->assertSame(['data' => [['name' => 'A'], ['name' => 'B']]], $this->json($this->dispatch('GET', '/collection')));
    }

    public function test_api_resource_registers_the_five_laravel_routes(): void
    {
        $this->router->group(['prefix' => '/api/v1', 'as' => 'v1.'], function (Router $router): void {
            $router->apiResource('users', RouteFixtureController::class, ['middleware' => [AddHeaderMiddleware::class]]);
        });

        $expected = [
            'v1.users.index' => [['GET'], '/api/v1/users', 'index'],
            'v1.users.store' => [['POST'], '/api/v1/users', 'store'],
            'v1.users.show' => [['GET'], '/api/v1/users/{user}', 'show'],
            'v1.users.update' => [['PUT', 'PATCH'], '/api/v1/users/{user}', 'update'],
            'v1.users.destroy' => [['DELETE'], '/api/v1/users/{user}', 'destroy'],
        ];

        $this->assertSame(array_keys($expected), array_keys($this->router->routes()->all()));

        foreach ($expected as $name => [$methods, $path, $action]) {
            $route = $this->router->routes()->get($name);
            $this->assertSame($methods, $route?->getMethods(), $name);
            $this->assertSame($path, $route?->getPath(), $name);
            $this->assertSame([RouteFixtureController::class, $action], $route?->getDefault('_action'), $name);
            $this->assertSame([AddHeaderMiddleware::class], $route?->getDefault('_middleware'), $name);
        }
    }

    public function test_api_resource_supports_only_except_and_parameter_naming(): void
    {
        $this->router->apiResource('photos', RouteFixtureController::class, ['only' => ['index', 'show']]);
        $this->router->apiResource('blog-posts', RouteFixtureController::class, ['except' => ['destroy']]);
        $this->router->apiResource('people', RouteFixtureController::class, ['only' => ['show'], 'parameter' => 'person_id']);

        $names = array_keys($this->router->routes()->all());

        $this->assertSame(['photos.index', 'photos.show', 'blog-posts.index', 'blog-posts.store', 'blog-posts.show', 'blog-posts.update', 'people.show'], $names);
        $this->assertSame('/blog-posts/{blog_post}', $this->router->routes()->get('blog-posts.show')?->getPath(), 'Parameters are singular snake_case.');
        $this->assertSame('/people/{person_id}', $this->router->routes()->get('people.show')?->getPath());
    }

    #[Group('defect')]
    public function test_string_route_parameters_keep_leading_zeros(): void
    {
        // Digit-only segments are cast to int before injection, so "007" arrives as 7
        // (or a TypeError under strict types) even when the handler asks for a string.
        $this->router->get('/codes/{code}', fn (string $code): array => ['code' => $code]);

        $this->assertSame(['code' => '007'], $this->json($this->dispatch('GET', '/codes/007')));
    }
}
