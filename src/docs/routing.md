# Routing

Routes are built on `symfony/routing` and can be defined two ways: plain
closures/array callables registered in a `routes/*.php` file, or a `#[Route]`
attribute on a controller method.

## Route files

`routes/api.php` and `routes/health.php` are loaded by
`App\Providers\RouteServiceProvider::boot()`. Each file receives the `Router`
instance as `$router`:

```php
// routes/health.php
use App\Controllers\HealthController;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->get('/health', [HealthController::class, 'index'], 'health');
```

Available verbs: `get()`, `post()`, `put()`, `patch()`, `delete()`, or the
generic `map(array $methods, ...)`. Each accepts:

```php
$router->post(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []);
```

To register every `#[Route]`-attributed method on a controller in one call,
use `controller()`:

```php
$router->controller(UserController::class);
```

## The `#[Route]` attribute

```php
use VtPhp\Routing\Attributes\Route;

#[Route(method: 'GET', path: '/users/{id}', name: 'users.show')]
public function show(int $id): UserResource
{
    return UserResource::make($this->service->find($id));
}
```

`Route` takes `method`, `path`, an optional `name`, and an optional
`middleware` array applied only to that route:

```php
#[Route(method: 'GET', path: '/me', name: 'auth.me', middleware: [Authenticate::class])]
public function me(): UserResource
{
    return UserResource::make(auth('web')->user());
}
```

Route parameters (`{id}`) are matched by `symfony/routing` and injected into
your action's arguments by name; they're also available via
`$request->route('id')`.

## Groups

`group()` lets you share a URI prefix, middleware, and route-name prefix
across many routes (including nested `controller()` calls):

```php
$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    $router->controller(UserController::class);
    $router->controller(AuthController::class);
});
```

```php
$router->group(['prefix' => '/admin', 'middleware' => [Authenticate::class], 'as' => 'admin.'], function (Router $router): void {
    $router->get('/dashboard', [DashboardController::class, 'index'], 'dashboard');
});
```

Group `middleware` and a route's own `middleware` (from `map()`'s 5th
argument, or `#[Route(middleware: [...])]`) are merged — group middleware
always runs first (outermost).

## Dispatching

`Router::dispatch(ServerRequestInterface $request)` matches the incoming URI
against the registered `RouteCollection`, copies matched path parameters onto
request attributes, and hands off to `ControllerDispatcher`, which builds a
per-route `Pipeline` (see [Middleware](middleware.md)) before invoking the
controller action.
