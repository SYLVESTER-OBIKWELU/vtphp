# Errors and exceptions

When something goes wrong in your API, for example a missing record or bad
input, you **throw an exception** and VtPhp turns it into a tidy JSON error
response. You never build error responses by hand, and every error your API
sends has the same shape.

An exception is PHP's way of saying "stop, something went wrong". You create
one with `throw new SomeException(...)`.

## The quick version

```php
// app/Http/Controllers/OrderController.php
use VtPhp\Exceptions\NotFoundHttpException;

public function show(string $code): array
{
    if ($code !== 'ABC123') {
        throw new NotFoundHttpException('Order not found.');
    }

    return ['code' => $code];
}
```

```http
GET /api/v1/orders/NOPE
```

```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Order not found.",
    "request_id": "9f2c4e1a..."
  }
}
```

The response has status `404`. You threw, and VtPhp did the rest.

The short way to do the same is `abort()`:

```php
abort(404, 'Order not found.');
abort_if($order->isLocked(), 423, 'This order is locked.');
abort_unless($user->is_admin, 403);
```

## The error format

Every error looks like this:

```json
{
  "success": false,
  "error": {
    "code": "SOME_CODE",
    "message": "A sentence a person can read.",
    "request_id": "9f2c4e1a..."
  }
}
```

- `code` is a fixed, machine-readable name. Your frontend can check it with
  `if (body.error.code === 'NOT_FOUND')`.
- `message` is for people. Don't base logic on its wording.
- `request_id` matches the `X-Request-ID` header. Ask users to quote it when
  they report a problem, then search your log for it.
- Some errors add extra fields, such as `errors` for validation,
  `allowed_methods` for a wrong method, or `retry_after` for rate limits.

### In a browser

If someone opens an API URL directly in a browser, they get a simple,
readable error page instead of raw JSON: the status, a title, the message
and the request ID. Your apps, `fetch`, `curl` and Postman still get JSON.
VtPhp tells them apart by the `Accept` header: only a request that asks for
`text/html` (and not JSON) gets the page.

### Every status code

You can send any error status, and VtPhp fills in a `code` and a plain
message when you don't give one:

| Status | `code` | Default message |
| --- | --- | --- |
| 400 | `BAD_REQUEST` | The request could not be understood. Check its format and try again. |
| 401 | `UNAUTHENTICATED` | You need to log in to do this. |
| 403 | `FORBIDDEN` | You are not allowed to do this. |
| 404 | `NOT_FOUND` | What you asked for could not be found. |
| 405 | `METHOD_NOT_ALLOWED` | This URL does not accept that HTTP method. Use GET or POST. |
| 409 | `CONFLICT` | This clashes with the current state of the data. Reload and try again. |
| 410 | `GONE` | This no longer exists. |
| 413 | `PAYLOAD_TOO_LARGE` | The request body is too large. |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Send the body in a supported format, such as JSON. |
| 422 | `VALIDATION_ERROR` | The first problem, e.g. "The email field is required. (and 1 more error)" |
| 423 | `LOCKED` | This is locked and cannot be changed right now. |
| 429 | `TOO_MANY_REQUESTS` | You are sending requests too quickly. Wait a moment and try again. |
| 500 | `INTERNAL_SERVER_ERROR` | Something went wrong on our side. Please try again later. |
| 502 | `BAD_GATEWAY` | A service we depend on sent an invalid response. Please try again later. |
| 503 | `SERVICE_UNAVAILABLE` | The service is temporarily unavailable. Please try again later. |
| 504 | `GATEWAY_TIMEOUT` | A service we depend on took too long to respond. Please try again later. |

Others (402, 406, 408, 411, 412, 414, 425, 428, 431, 451, 501) are covered
too. Any other status gets `CLIENT_ERROR` (4xx) or `SERVER_ERROR` (5xx).

## Errors you can throw

All of these live in the `VtPhp\Exceptions` namespace:

| Exception                       | Status | `code`                |
| ------------------------------- | ------ | --------------------- |
| `NotFoundHttpException`         | 404    | `NOT_FOUND`           |
| `AuthenticationException`       | 401    | `UNAUTHENTICATED`     |
| `AuthorizationException`        | 403    | `FORBIDDEN`           |
| `ConflictHttpException`         | 409    | `CONFLICT`            |
| `ValidationException`           | 422    | `VALIDATION_ERROR`    |
| `TooManyRequestsHttpException`  | 429    | `TOO_MANY_REQUESTS`   |
| `MethodNotAllowedHttpException` | 405    | `METHOD_NOT_ALLOWED`  |
| `HttpException`                 | any    | from [Every status code](#every-status-code) |

Their default messages are the ones in [Every status code](#every-status-code).
For any other status, use `abort(503)` or `throw new HttpException(503)`.

Pass your own message as the first argument:

```php
throw new AuthorizationException('You may only delete your own account.');
```

401 means "you're not logged in". 403 means "you're logged in, but this isn't
allowed for you".

### Validation errors

A `ValidationException` lists what's wrong with each field. You'll rarely
throw it yourself, because [form requests](validation.md) do it for you. When
you need to, pass an array of messages per field:

```php
use VtPhp\Exceptions\ValidationException;

throw new ValidationException(['token' => ['This password reset token is invalid.']]);
```

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "This password reset token is invalid.",
    "errors": {
      "token": ["This password reset token is invalid."]
    }
  }
}
```

## Errors VtPhp sends for you

You'll also see these without throwing anything:

| Situation                                           | Status | `code`               |
| --------------------------------------------------- | ------ | -------------------- |
| No route matches the URL                            | 404    | `NOT_FOUND` (`No endpoint matches GET /x.`) |
| The URL exists but not for that method (e.g. `PUT`) | 405    | `METHOD_NOT_ALLOWED` (lists `allowed_methods`, sends an `Allow` header) |
| A route model like `User $user` finds no record     | 404    | `NOT_FOUND` (`User not found.`) |
| `User::query()->findOrFail($id)` finds nothing      | 404    | `NOT_FOUND` (`User not found.`) |
| A guest calls a route protected by `Authenticate`   | 401    | `UNAUTHENTICATED`    |
| Too many requests                                   | 429    | `TOO_MANY_REQUESTS`  |
| A cross-site write is blocked                       | 403    | `CSRF_REJECTED`      |
| Any other unexpected problem                        | 500    | `INTERNAL_SERVER_ERROR` |

## Unexpected errors (500)

If your code throws anything else, for example a database failure or a bug,
VtPhp:

1. answers with status 500, code `INTERNAL_SERVER_ERROR` and the message
   `Something went wrong on our side. Please try again later.`, hiding the
   real details from the caller;
2. writes the real error to your log (`storage/logs/app.log` by default),
   with the request's `X-Request-ID` so you can find it.

Every response carries an `X-Request-ID` header. When a user reports a
problem, ask for that ID and search your log for it.

Errors in the 400 range are not logged, because they're the caller's
mistakes, not yours.

A 5xx you send on purpose, such as `abort(503, 'Down for maintenance.')`,
keeps its message, because you wrote it for the caller. It's still logged.

## Debug mode

While developing, set `APP_DEBUG=true` in `.env`. Errors then also carry an
`error.debug` object, and a 500 shows its real message:

```json
"debug": {
  "exception": "RuntimeException",
  "location": "app/Http/Controllers/OrderController.php:42",
  "trace": ["#0 src/Container/Container.php:225 ..."]
}
```

- `location` is where the exception was thrown, relative to your project.
- `trace` (the chain of calls that led there) is included for 500 errors
  only. For a 404 or 422 it would only show the framework's own code.

When no route matches the URL, debug mode also adds a `hint`:

| You asked for | `hint` |
| --- | --- |
| `/api-doc` while the API docs are off | The API docs are switched off. Set SWAGGER_ENABLED=true in .env, then open /api-docs. |
| `/api/v1/user` | Did you mean GET /api/v1/users? |
| anything else | Run `php forge route:list` to see every URL this app answers. |

The browser error page shows the same details.

**Always set `APP_DEBUG=false` in production.** Traces reveal file paths and
code details that attackers can use.

## Going further

### Your own error types

For an error you throw in several places, make a class for it:

```php
// app/Exceptions/OutOfStockException.php
namespace App\Exceptions;

use VtPhp\Exceptions\HttpException;

final class OutOfStockException extends HttpException
{
    public function __construct(string $sku)
    {
        parent::__construct(409, "Product [{$sku}] is out of stock.", 'OUT_OF_STOCK');
    }
}
```

`HttpException` takes, in order: the status code, the message, the `code`,
an array of extra fields to add to `error`, an optional previous exception,
and an array of extra response headers. For example, this adds
`"sku": "..."` to the error and a `Retry-After` header:

```php
parent::__construct(409, 'Out of stock.', 'OUT_OF_STOCK', ['sku' => $sku], null, ['Retry-After' => '3600']);
```

### Where errors are handled

The `HandleExceptions` middleware (code that wraps every request) catches
exceptions and asks `VtPhp\Exceptions\ExceptionHandler` to build the JSON
response. Because it sits inside the other global middleware, error
responses still get the request ID, CORS headers, security headers and the
session cookie, just like successful ones. If one of the outer middleware
fails itself, the HTTP kernel catches that as a last resort. See
[How a request works](request-lifecycle.md).

## Common mistakes

- **Returning an error array instead of throwing.** `return ['error' => ...]`
  sends status 200. Throw an exception instead.
- **Catching every exception.** A `try { ... } catch (\Throwable $e) {}` hides
  real problems. Let unexpected errors reach the handler so they're logged.
- **Debug mode left on.** With `APP_DEBUG=true` in production, error
  responses expose internal details.
- **Basing frontend logic on `message`.** Messages can change. Check `code`.
- **Reading `error.exception` or `error.trace`.** They're inside
  `error.debug` now, which only exists when `APP_DEBUG=true`.

## Next steps

- [Validation](validation.md): input checks that produce `422` errors for you.
- [Controllers](controllers.md): where you'll throw most exceptions.
- [How a request works](request-lifecycle.md): where error handling fits in.
