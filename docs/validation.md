# Requests & validation

There's no heavyweight "Form Request" base class — instead, each request has
a small, explicit DTO under `app/Requests/` with a static `fromRequest()`
constructor that validates and returns a readonly object:

```php
namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class CreateUserRequest
{
    private function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        return new self($data['name'], $data['email'], $data['password']);
    }
}
```

Call it from the controller action:

```php
public function store(Request $request): UserResource
{
    $data = CreateUserRequest::fromRequest($request);
    // ...
}
```

`Validator::make($data, $rules)->validate()` throws a
`VtPhp\Exceptions\ValidationException` (422, `VALIDATION_ERROR`) when
validation fails, which the global `ExceptionHandler` turns into a structured
JSON error response automatically — no try/catch needed in the controller.

## `VtPhp\Http\Request`

Injected automatically into any controller action or request DTO method that
type-hints it:

| Method | Description |
| --- | --- |
| `method()` | HTTP method. |
| `path()` | URI path. |
| `query(string $key, mixed $default = null)` | A single query-string value. |
| `input(string $key, mixed $default = null)` | A value from query + parsed body. |
| `all()` | Query params merged with parsed body. |
| `json()` | Parsed JSON body as an array. |
| `has(string $key)` | Whether a key exists in `all()`. |
| `header(string $key, ?string $default = null)` | A request header. |
| `cookie(string $key, ?string $default = null)` | An incoming cookie value. |
| `bearerToken()` | The `Bearer` token from the `Authorization` header, if any. |
| `route(string $key, mixed $default = null)` | A matched route parameter. |
| `psr()` | The underlying PSR-7 `ServerRequestInterface`. |

## Validation rules

Rules are pipe-delimited strings (or arrays of strings), e.g.
`'password' => ['required', 'string', 'min:8', 'confirmed']`. Supported
rules: `required`, `nullable`, `string`, `integer`/`int`, `numeric`,
`boolean`/`bool`, `array`, `email`, `max:N`, `min:N`, `in:a,b,c`, `date`,
`confirmed` (checks `{field}_confirmation`).

You can also use the validator directly without a request DTO:

```php
use VtPhp\Validation\Validator;

$validator = Validator::make($data, ['email' => ['required', 'email']]);

if ($validator->fails()) {
    $errors = $validator->errors(); // array<string, array<int, string>>
}
```
