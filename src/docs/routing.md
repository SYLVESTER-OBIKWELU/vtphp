# Routing

A **route** connects a web address (a URL) to the code that answers it. When
someone visits `/api/v1/users`, the router looks up which route matches and
runs the code you attached to it.

## The quick version

Routes live in the `routes/` folder. Here's a complete route that answers
`GET /hello` with a little JSON:

```php
// routes/api.php
use VtPhp\Routing\Router;

/** @var Router $router */

$router->get('/hello', fn () => ['message' => 'Hello, world!']);
```

Start the server with `php forge serve`, then visit it:

```http
GET /hello
```

```json
{"message": "Hello, world!"}
```

The function returned an array, and the framework turned it into JSON for
you. That's a complete route.

## Where routes are defined

Two files hold your routes:

- `routes/api.php`: your API. The sample app keeps everything under `/api/v1`.
- `routes/health.php`: a `/health` check that load balancers can call.

`App\Providers\RouteServiceProvider` loads both files when the app starts.
Inside them, `$router` is already available. You don't create it yourself.

> Coming from Laravel? VtPhp has no `Route::get(...)` shortcut yet. Use the
> `$router` variable instead.

## HTTP methods

Every request has a **method** that says what the caller wants to do.
`GET` reads, `POST` creates, `PUT`/`PATCH` update and `DELETE` removes. There's
a router function for each:

```php
// routes/api.php
use App\Http\Controllers\PostController;

$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts', [PostController::class, 'store']);
$router->put('/posts/{post}', [PostController::class, 'update']);
$router->patch('/posts/{post}', [PostController::class, 'update']);
$router->delete('/posts/{post}', [PostController::class, 'destroy']);
```

Each one takes the same four arguments, in this order:

```php
$router->post(string $uri, $action, ?string $name = null, array $middleware = []);
```

1. **`$uri`**: the path, such as `/posts`.
2. **`$action`**: what to run. Usually `[ControllerClass::class, 'method']`
   (see [Controllers](controllers.md)). A closure (`fn () => ...`) or the
   string `'ControllerClass@method'` also work.
3. **`$name`** (optional): a name such as `'posts.index'`. It shows up in
   `php forge route:list`.
4. **`$middleware`** (optional): extra checks to run first, like
   `[Authenticate::class]`. See [Middleware](middleware.md).

Need several methods on one path? Use `map()`:

```php
$router->map(['GET', 'POST'], '/search', [SearchController::class, 'handle']);
```

## Route parameters

Put a name in curly braces to capture part of the URL. The value is passed to
your function by **matching its name**:

```php
// routes/api.php
$router->get('/posts/{slug}', fn (string $slug) => ['slug' => $slug]);
```

```http
GET /posts/hello-world
```

```json
{"slug": "hello-world"}
```

The value is converted to the type you declare:

- `int $id` or `float $price` receive a number. If the URL part isn't a number
  (`/orders/abc`), the caller gets a `404 NOT_FOUND` instead of an error.
- `string $code`, or no type at all, receives the text exactly as typed, so
  `/codes/007` gives you `"007"` (the leading zeros are kept).

You can also read a parameter from the request with `$request->route('slug')`.

## Resource routes: five routes in one line

Most things in an API need the same five actions: list, create, show, update
and delete. `apiResource()` registers all of them:

```php
// routes/api.php
$router->apiResource('posts', PostController::class);
```

That one line creates:

| Method      | URI             | Name            | Controller method |
| ----------- | --------------- | --------------- | ----------------- |
| `GET`       | `/posts`        | `posts.index`   | `index()`         |
| `POST`      | `/posts`        | `posts.store`   | `store()`         |
| `GET`       | `/posts/{post}` | `posts.show`    | `show()`          |
| `PUT\|PATCH` | `/posts/{post}` | `posts.update`  | `update()`        |
| `DELETE`    | `/posts/{post}` | `posts.destroy` | `destroy()`       |

Notice that update accepts both `PUT` and `PATCH`. The parameter name is the
singular of the resource name in snake_case, so `blog-posts` gives
`{blog_post}`.

`php forge make:model Post -a` writes a matching controller for you and
prints the exact `apiResource()` line to paste in.

## Route-model binding: get the record, not the id

If a parameter is typed as an Eloquent model **and its name matches the URL
part**, VtPhp looks up the record for you:

```php
// app/Http/Controllers/PostController.php
public function show(Post $post): PostResource
{
    return PostResource::make($post); // $post is already loaded from the database
}
```

- If no record has that id, the caller gets `404 NOT_FOUND` with the message
  `Post not found.`, and your method never runs.
- `/posts/abc` is also a 404. The database is never asked about it.
- A camelCase parameter matches its snake_case URL part, so
  `BlogPost $blogPost` binds to `{blog_post}`.
- Calling `Post::query()->findOrFail($id)` yourself produces the same 404.

## Groups: share a prefix or middleware

`group()` applies the same URL prefix, middleware, name prefix or docs
section (`tag`) to many routes at once. This is how the sample app puts everything under `/api/v1`:

```php
// routes/api.php
use VtPhp\Middleware\Authenticate;

$router->group(['prefix' => '/admin', 'middleware' => [Authenticate::class], 'as' => 'admin.'], function (Router $router): void {
    $router->get('/dashboard', [DashboardController::class, 'index'], 'dashboard'); // GET /admin/dashboard, named admin.dashboard
    $router->apiResource('users', AdminUserController::class);                      // /admin/users..., named admin.users.*
});
```

Groups can be nested; prefixes, middleware and names stack up. Include the
dot yourself in `'as' => 'admin.'`, because names are joined exactly as written.

`'tag' => 'Notes'` lists the group's routes under a "Notes" heading in the
[API docs](api-docs.md). An inner group's tag replaces the outer one:

```php
$router->group(['tag' => 'Notes'], function (Router $router): void {
    $router->get('/notes', [NoteController::class, 'index'], 'notes.index');
});
```

## Seeing your routes

```powershell
php forge route:list
```

This prints every registered route with its methods, URI, name and controller
method. Run it whenever a URL doesn't do what you expect.

## Going further

### apiResource options

```php
$router->apiResource('photos', PhotoController::class, [
    'only' => ['index', 'show'],          // or 'except' => ['destroy']
    'middleware' => [Authenticate::class],
    'parameter' => 'photo_id',            // use {photo_id} instead of {photo}
    'tag' => 'Photos',                    // the section in the API docs
]);
```

The sample app uses `except` to keep sign-up public while protecting the rest:

```php
// routes/api.php
$router->post('/users', [UserController::class, 'store'], 'users.store', [ThrottleRequests::using('auth')]);
$router->apiResource('users', UserController::class, ['except' => ['store'], 'middleware' => [Authenticate::class]]);
```

### Rate limits on routes

Route middleware can take a parameter. Rate limits use this to pick a named
limit from `config/rate_limiting.php`:

```php
use VtPhp\Middleware\ThrottleRequests;

$router->post('/login', [AuthController::class, 'login'], 'auth.login', [ThrottleRequests::using('auth')]);
```

See [Security](security.md#rate-limiting) for the built-in limits.

### Attribute routes

Instead of a route file, you can put a `#[Route]` attribute on a controller
method and register the whole controller:

```php
// app/Http/Controllers/StatusController.php
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

Route files are the usual choice; attributes are there if you prefer them.

## Common mistakes

- **`404` with `No endpoint matches GET /x.`** The path or method doesn't match
  any route. Check `php forge route:list`, including the `/api/v1` prefix.
  With `APP_DEBUG=true`, the error's `hint` suggests the closest URL.
- **`405 METHOD_NOT_ALLOWED`.** The path exists but not for that method,
  e.g. `POST` to a route that only accepts `GET`. The error lists the
  `allowed_methods`.
- **Middleware in the wrong place.** Middleware is the **4th** argument.
  `$router->get('/me', $action, [Authenticate::class])` passes it as the
  *name*. Write `$router->get('/me', $action, 'auth.me', [Authenticate::class])`.
- **A model parameter that's always empty.** `show(Post $item)` on a
  `{post}` route doesn't match, so you get a brand-new empty `Post`. Name the
  parameter after the URL part: `Post $post`.
- **Trailing slashes.** When you *register* a route, a trailing slash is
  dropped (`'/posts/'` becomes `/posts`). But a *request* to `/posts/` does
  not match `/posts` and returns a 404, so call your API without the
  trailing slash.

## Next steps

- [Controllers](controllers.md): write the code your routes call.
- [Validation](validation.md): check what callers send.
- [Middleware](middleware.md): run checks before your code.
- [Your first API](your-first-api.md): build a full resource from scratch.
