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
    "message": "Order not found."
  }
}
```

The response has status `404`. You threw, and VtPhp did the rest.

## The error format

Every error looks like this:

```json
{
  "success": false,
  "error": {
    "code": "SOME_CODE",
    "message": "A sentence a person can read."
  }
}
```

- `code` is a fixed, machine-readable name. Your frontend can check it with
  `if (body.error.code === 'NOT_FOUND')`.
- `message` is for people. Don't base logic on its wording.
- Some errors add extra fields, such as `errors` for validation or
  `retry_after` for rate limits.

## Errors you can throw

All of these live in the `VtPhp\Exceptions` namespace:

| Exception                       | Status | `code`                | Default message                  |
| ------------------------------- | ------ | --------------------- | -------------------------------- |
| `NotFoundHttpException`         | 404    | `NOT_FOUND`           | `Not found.`                     |
| `AuthenticationException`       | 401    | `UNAUTHENTICATED`     | `Unauthenticated.`               |
| `AuthorizationException`        | 403    | `FORBIDDEN`           | `This action is unauthorized.`   |
| `ConflictHttpException`         | 409    | `CONFLICT`            | `Conflict.`                      |
| `ValidationException`           | 422    | `VALIDATION_ERROR`    | `The given data was invalid.`    |
| `TooManyRequestsHttpException`  | 429    | `TOO_MANY_REQUESTS`   | `Too many requests.`             |
| `MethodNotAllowedHttpException` | 405    | `METHOD_NOT_ALLOWED`  | `Method not allowed.`            |

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
    "message": "The given data was invalid.",
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
| No route matches the URL                            | 404    | `NOT_FOUND`          |
| The URL exists but not for that method (e.g. `PUT`) | 405    | `METHOD_NOT_ALLOWED` (lists `allowed_methods`) |
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
   `Internal server error.`, hiding the real details from the caller;
2. writes the real error to your log (`storage/logs/app.log` by default),
   with the request's `X-Request-ID` so you can find it.

Every response carries an `X-Request-ID` header. When a user reports a
problem, ask for that ID and search your log for it.

Errors in the 400 range are not logged, because they're the caller's
mistakes, not yours.

## Debug mode

While developing, set `APP_DEBUG=true` in `.env`. Error responses then also
include the exception's class name (`error.exception`) and a stack trace
(`error.trace`), showing where the error happened.

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

## Next steps

- [Validation](validation.md): input checks that produce `422` errors for you.
- [Controllers](controllers.md): where you'll throw most exceptions.
- [How a request works](request-lifecycle.md): where error handling fits in.
