# Middleware

**Middleware** is code that runs before (and after) your controller, for
every request it's attached to. It's the place for checks and extras that
many routes share: "is the user logged in?", "has this caller sent too many
requests?", "add a header to every response".

## The quick version

Protect a route so only logged-in users can reach it:

```php
// routes/api.php
use App\Http\Controllers\AuthController;
use VtPhp\Middleware\Authenticate;

$router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);
```

A guest calling it never reaches `AuthController`. They get:

```http
GET /api/v1/me
```

```json
{"success": false, "error": {"code": "UNAUTHENTICATED", "message": "Unauthenticated."}}
```

with status `401`. Middleware goes in the **4th** argument, after the route's
name.

## How middleware works

Think of middleware as layers around your controller, like the layers of an
onion. A request travels inward through each layer to the controller, and the
response travels back out through the same layers in reverse:

```text
request  →  [ A  →  [ B  →  [ controller ]  →  B ]  →  A ]  →  response
```

Each layer can:

- **look at the request** and pass it on,
- **stop it early** by throwing an exception (like `Authenticate` does), or
- **change the response** on its way back out.

The first middleware in a list is the outermost layer: it sees the request
first and the response last.

## Writing your own

Generate one:

```powershell
php forge make:middleware RequireApiVersion
```

Then fill in `process()`. This one rejects requests that don't say which API
version they expect, and adds a header to every response it lets through:

```php
// app/Http/Middleware/RequireApiVersion.php
namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Exceptions\HttpException;

final class RequireApiVersion implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Before the controller: check the request.
        if ($request->getHeaderLine('X-Api-Version') === '') {
            throw new HttpException(400, 'Send an X-Api-Version header.', 'API_VERSION_MISSING');
        }

        // 2. Hand the request to the next layer (eventually, your controller).
        $response = $handler->handle($request);

        // 3. After the controller: adjust the response.
        return $response->withHeader('X-Api-Version', '1');
    }
}
```

The three steps are the whole pattern. Skip step 1 or 3 if you don't need
them, but always call `$handler->handle($request)` unless you're stopping the
request.

Middleware receives a standard PSR-7 request (PSR-7 is the common PHP
standard for request and response objects). For VtPhp's friendlier methods,
wrap it: `$req = \VtPhp\Http\Request::fromPsr($request);` then use
`$req->input('email')`, `$req->ip()` and so on.

If your middleware needs a service, ask for it in the constructor. VtPhp
builds middleware through its service container, so it's filled in for you
(see [Container](container.md)).

## Attaching middleware

There are four places to attach it, from narrowest to widest:

**One route**: the 4th argument.

```php
// routes/api.php
$router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);
```

**A resource**: the `middleware` option of `apiResource()`.

```php
// routes/api.php
$router->apiResource('posts', PostController::class, ['middleware' => [Authenticate::class]]);
```

**A group of routes**: the `middleware` key of `group()`.

```php
// routes/api.php
$router->group(['prefix' => '/api/v1', 'middleware' => [RequireApiVersion::class]], function (Router $router): void {
    // every route in here gets it
});
```

**Every request**: add it to the `$middleware` list in
`src/Foundation/Http/Kernel.php`. See the global list below for where to put it.

When a route is in a group, the group's middleware runs first (outermost),
then the route's own.

## Middleware that comes with VtPhp

### For your routes

| Middleware | What it does |
| --- | --- |
| `Authenticate` | Rejects guests with `401 UNAUTHENTICATED`. Plain `Authenticate::class` accepts the session cookie; `Authenticate::using('web', 'api')` also accepts API tokens. See [Authentication](authentication.md). |
| `RequireTokenAbility` | Requires the API token to have an ability, e.g. `RequireTokenAbility::using('notes:write')` (`403` otherwise). |
| `ThrottleRequests` | Rate limits. Use `ThrottleRequests::using('auth')` to pick a named limit from `config/rate_limiting.php`. See [Security](security.md#rate-limiting). |

### On every request (the global list)

These run for every request, in this order. You normally don't touch them:

```php
// src/Foundation/Http/Kernel.php
protected array $middleware = [
    TrustProxies::class,                // reads the real client IP and https behind a load balancer
    AddSecurityHeaders::class,          // adds protective headers to every response
    RequestIdMiddleware::class,         // gives every request an X-Request-ID
    HandleCors::class,                  // lets your allowed frontends call the API (off by default)
    AddQueuedCookiesToResponse::class,  // attaches cookies you queued with cookie()
    StartSession::class,                // loads the session and saves it afterwards
    HandleExceptions::class,            // turns exceptions into JSON error responses
    PreventRequestForgery::class,       // blocks cross-site forged writes (CSRF)
];
```

The order matters:

- Everything **above** `HandleExceptions` also wraps error responses, so a
  `404` or `422` still gets the request ID, CORS headers, security headers
  and the session cookie.
- Everything **below** it, like `PreventRequestForgery`, has its errors turned
  into JSON by it.
- `HandleCors` runs before `StartSession`, so browser "may I?" checks
  (preflights) are answered without creating a session.
- `AddQueuedCookiesToResponse` runs before `StartSession`, so on the way out
  it can attach the session cookie `StartSession` queued.

To add your own global middleware, put it **above** `HandleExceptions` if
its responses should look like everything else, or **below** it if it
throws exceptions that should become JSON errors.

## Going further

### Middleware with parameters

A middleware can accept settings written as `Class:value`, like
`ThrottleRequests::using('auth')` (which is just
`'VtPhp\Middleware\ThrottleRequests:auth'`). To support this, implement
`VtPhp\Middleware\ConfigurableMiddleware`:

```php
// app/Http/Middleware/RequireRole.php
namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Middleware\ConfigurableMiddleware;

final class RequireRole implements ConfigurableMiddleware
{
    private string $role = 'admin';

    public static function using(string $role): string
    {
        return self::class.':'.$role;
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->role = $parameters[0] ?? 'admin';

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (auth()->user()?->role !== $this->role) {
            throw new AuthorizationException();
        }

        return $handler->handle($request);
    }
}
```

```php
// routes/api.php
$router->delete('/posts/{post}', [PostController::class, 'destroy'], 'posts.destroy', [Authenticate::class, RequireRole::using('editor')]);
```

Several values are separated by commas (`Class:a,b`) and arrive as an array.
This example assumes your `users` table has a `role` column; VtPhp has no
built-in roles.

## Common mistakes

- **Middleware passed as the name.** `$router->get('/me', $action,
  [Authenticate::class])` puts the array in the 3rd (name) position. Pass a
  name first: `$router->get('/me', $action, 'auth.me', [Authenticate::class])`.
- **Forgetting `$handler->handle($request)`.** Without it, the controller
  never runs and nothing is returned.
- **`Middleware [...] does not accept parameters.`** You wrote `Class:value`
  for a middleware that doesn't implement `ConfigurableMiddleware`.
- **API tokens rejected with 401.** Plain `Authenticate::class` only accepts
  the session cookie. Use `Authenticate::using('web', 'api')` for both.
- **A global middleware's error comes back as a plain 500.** It's above
  `HandleExceptions`, so nothing turns its exceptions into JSON. Move it below.

## Next steps

- [Routing](routing.md): attaching middleware to routes and groups.
- [Authentication](authentication.md): what `Authenticate` checks.
- [Security](security.md): rate limits, CSRF and security headers.
- [How a request works](request-lifecycle.md): the full journey of a request.
