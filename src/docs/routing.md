# Routing

Routes are built on `symfony/routing` and defined in `routes/*.php` files,
Laravel-style. `routes/api.php` and `routes/health.php` are loaded by
`App\Providers\RouteServiceProvider::boot()`, and each receives the `Router`
as `$router`:

```php
// routes/api.php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use VtPhp\Middleware\Authenticate;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    $router->apiResource('users', UserController::class);

    $router->post('/login', [AuthController::class, 'login'], 'auth.login');
    $router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);
});
```

## Single routes

Available verbs: `get()`, `post()`, `put()`, `patch()`, `delete()`, or the
generic `map(array $methods, ...)`:

```php
$router->post(string $uri, array|string|callable $action, ?string $name = null, array $middleware = []);
```

An action can be `[Controller::class, 'method']`, `'Controller@method'`, or a closure.

## Resource routes

`apiResource()` registers the five standard actions:

```php
$router->apiResource('posts', PostController::class);
```

| Method      | URI             | Name            | Action      |
| ----------- | --------------- | --------------- | ----------- |
| `GET`       | `/posts`        | `posts.index`   | `index()`   |
| `POST`      | `/posts`        | `posts.store`   | `store()`   |
| `GET`       | `/posts/{post}` | `posts.show`    | `show()`    |
| `PUT\|PATCH` | `/posts/{post}` | `posts.update`  | `update()`  |
| `DELETE`    | `/posts/{post}` | `posts.destroy` | `destroy()` |

The parameter is the singular, snake_case resource name: `blog-posts` gives
`{blog_post}`. Options:

```php
$router->apiResource('photos', PhotoController::class, [
    'only' => ['index', 'show'],          // or 'except' => ['destroy']
    'middleware' => [Authenticate::class],
    'parameter' => 'photo_id',            // rename {photo}
]);
```

## Route-model binding

Type-hint an Eloquent model whose parameter name matches a route segment,
and the action receives the model instead of the id:

```php
// GET /posts/{post}
public function show(Post $post): PostResource
{
    return PostResource::make($post);
}
```

- If no row matches, the request ends with `404 NOT_FOUND` and the message
  `Post not found.`, before your action runs.
- Segments that can't match an integer key (`/posts/abc`) are a 404 too.
  The database is never queried with them.
- A camelCase parameter also matches its snake_case segment, so
  `BlogPost $blogPost` binds to `{blog_post}`.
- Binding uses the model's `getRouteKeyName()` (the primary key by default),
  so override that on the model to bind by slug.
- `User::query()->findOrFail($id)` elsewhere in your code produces the same 404.

Other parameters are injected by name as raw values (digit-only values as
`int`) and are also available via `$request->route('name')`.

## Groups

`group()` shares a URI prefix, middleware and route-name prefix across routes:

```php
$router->group(['prefix' => '/admin', 'middleware' => [Authenticate::class], 'as' => 'admin.'], function (Router $router): void {
    $router->get('/dashboard', [DashboardController::class, 'index'], 'dashboard'); // admin.dashboard
    $router->apiResource('users', AdminUserController::class);                      // admin.users.*
});
```

Group middleware and a route's own middleware are merged. Group middleware
always runs first (outermost).

## Attribute routes

As an alternative to route files, a controller method can carry a `#[Route]`
attribute. Register every attributed method with `controller()`:

```php
use VtPhp\Routing\Attributes\Route;

final class StatusController
{
    #[Route(method: 'GET', path: '/status', name: 'status', middleware: [Authenticate::class])]
    public function show(): array
    {
        return ['ok' => true];
    }
}

// routes/api.php
$router->controller(StatusController::class);
```

## Dispatching

`Router::dispatch()` matches the URI against the registered routes, copies
the matched parameters onto request attributes, and hands off to
`ControllerDispatcher`. The dispatcher runs the route's middleware
[pipeline](middleware.md), binds models, and invokes the action. Run
`php forge route:list` to see every registered route.
