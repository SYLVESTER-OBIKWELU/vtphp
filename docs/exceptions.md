# Exceptions & error handling

Every uncaught exception thrown during a request is caught by
`VtPhp\Foundation\Http\Kernel::handle()` and rendered by
`VtPhp\Exceptions\ExceptionHandler` into a consistent JSON envelope:

```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Route [/nope] not found."
  }
}
```

When `APP_DEBUG=true`, the response also includes `error.exception` (the
thrown class) and `error.trace`.

## Built-in HTTP exceptions

All extend `VtPhp\Exceptions\HttpException(int $status, string $message, string $errorCode, array $context = [])`:

| Exception                       | Status | Error code                                                                  |
| ------------------------------- | ------ | --------------------------------------------------------------------------- |
| `NotFoundHttpException`         | 404    | `NOT_FOUND`                                                                 |
| `MethodNotAllowedHttpException` | 405    | `METHOD_NOT_ALLOWED`                                                        |
| `ConflictHttpException`         | 409    | `CONFLICT`                                                                  |
| `AuthenticationException`       | 401    | `UNAUTHENTICATED`                                                           |
| `AuthorizationException`        | 403    | `FORBIDDEN`                                                                 |
| `TooManyRequestsHttpException`  | 429    | `TOO_MANY_REQUESTS`                                                         |
| `ValidationException`           | 422    | `VALIDATION_ERROR` (adds an `errors` map — see [Validation](validation.md)) |

Throw any of these from a controller, service, or middleware — you don't
need to catch them yourself:

```php
throw new \VtPhp\Exceptions\NotFoundHttpException("Product [{$id}] not found.");
```

## Custom exceptions

Extend `HttpException` for a new typed error:

```php
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

Anything that isn't an `HttpException` (or `ValidationException`) is
rendered as a generic `500 INTERNAL_SERVER_ERROR` and logged via
`Psr\Log\LoggerInterface` (with the exception class and the request's
`X-Request-ID` for correlation) — the client only ever sees "Internal server
error." unless `APP_DEBUG=true`.
