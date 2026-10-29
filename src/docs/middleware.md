# Middleware

Middleware are plain PSR-15 classes implementing
`Psr\Http\Server\MiddlewareInterface`:

```php
namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class EnsureTokenIsValid implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ... inspect/modify $request before the next stage ...

        $response = $handler->handle($request);

        // ... inspect/modify $response after the next stage ...

        return $response;
    }
}
```

Generate the boilerplate in `app/Http/Middleware/` with:

```powershell
php forge make:middleware EnsureTokenIsValid
```

## Registering middleware

- **Globally**, for every request: add the class to `$middleware` in
  `src/Foundation/Http/Kernel.php`, above `HandleExceptions`, which must stay
  last so every global middleware also wraps error responses.
- **Per group**: pass `'middleware' => [...]` to `$router->group([...], ...)`.
- **Per resource**: pass `['middleware' => [...]]` as `apiResource()`'s options.
- **Per route**: pass the 4th `array $middleware` argument to
  `$router->get()/post()/...`, or use the `middleware` parameter on the
  `#[Route]` attribute:

  ```php
  $router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);
  ```

Container dependencies with default values are autowired, so a middleware
like `Authenticate` can take a constructor parameter (e.g.
`string $guard = 'web'`) without any special registration.

## Execution order (the "onion")

`VtPhp\Middleware\Pipeline` wraps the middleware list from outside in: the
**first** element in the array is the **outermost** layer (it sees the
request first and the response last). This matters when middleware both
inspects the incoming request and mutates the outgoing response — for
example, the built-in stack is ordered:

```php
protected array $middleware = [
    RequestIdMiddleware::class,
    HandleCors::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    HandleExceptions::class,
];
```

- `HandleCors` comes before `StartSession`, so CORS preflights are answered
  without creating a session, and every response below it, including errors,
  gets CORS headers.
- `AddQueuedCookiesToResponse` is listed _before_ `StartSession` so that, on
  the way back out, it can see the session cookie that `StartSession` queued.
- `HandleExceptions` is last, so every middleware above it also wraps error
  responses.

## Built-in middleware

| Class                                         | Purpose                                                                      |
| --------------------------------------------- | ---------------------------------------------------------------------------- |
| `VtPhp\Middleware\RequestIdMiddleware`        | Assigns/propagates an `X-Request-ID` header.                                 |
| `VtPhp\Middleware\HandleCors`                 | Applies the [CORS policy](cors.md) from `config/cors.php` (off by default).  |
| `VtPhp\Middleware\HandleExceptions`           | Renders exceptions as JSON error responses inside the global stack.          |
| `VtPhp\Middleware\AddQueuedCookiesToResponse` | Attaches cookies queued via `cookie()` as `Set-Cookie` headers.              |
| `VtPhp\Middleware\StartSession`               | Starts/resumes the session and saves it after the response is produced.      |
| `VtPhp\Middleware\Authenticate`               | Rejects the request with a 401 if the given guard has no authenticated user. |
