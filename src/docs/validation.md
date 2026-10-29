# Requests & validation

## Form requests

Like Laravel, a form request is a class that holds the rules for one
endpoint. Generate one with `php forge make:request StorePostRequest`:

```php
namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // return false to reject with 403 FORBIDDEN
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'slug' => 'required|string|unique:posts,slug',
            'status' => 'sometimes|in:draft,published',
        ];
    }
}
```

Type-hint it in a controller action. By the time the action runs, the
request has been authorized and validated:

```php
public function store(StorePostRequest $request): PostResource
{
    return PostResource::make(Post::query()->create($request->validated()))->status(201);
}
```

- Failing validation throws a `ValidationException`, rendered as a
  `422 VALIDATION_ERROR` with an `errors` object keyed by field.
- `authorize()` returning `false` throws an `AuthorizationException` (`403 FORBIDDEN`).
- `validated()` returns **only the fields that have rules**. Unexpected
  input like `is_admin` or `id` is dropped, so passing it to `create()` or
  `update()` is safe. `validated('title')` reads one field, with an
  optional default.
- A form request is still a `Request`, so `input()`, `route()`, `user()` and
  the rest work too. Use `route()` to ignore the current record in a
  `unique` rule on update:

```php
public function rules(): array
{
    return [
        'email' => 'sometimes|email|unique:users,email,'.$this->route('user'),
    ];
}
```

## Inline validation

For small endpoints, validate directly on the request:

```php
public function search(Request $request): array
{
    $data = $request->validate([
        'q' => 'required|string|min:2',
        'page' => 'nullable|integer',
    ]);

    // $data holds only `q` and `page`.
}
```

## Validation rules

Rules are pipe-delimited strings (`'required|string|max:100'`) or arrays of strings.

| Rule                                     | Passes when                                                                                    |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `required`                               | The value is present and not `null` or `''`.                                                   |
| `sometimes`                              | Only validate the field if it is present (for partial updates).                                |
| `nullable`                               | `null` is allowed; other rules are skipped when the value is `null`.                           |
| `string`, `array`, `boolean`/`bool`      | The value has that type (`boolean` also accepts `0`, `1`, `'0'`, `'1'`).                       |
| `integer`/`int`, `numeric`               | An integer (or digit string) / any numeric value.                                             |
| `email`                                  | A valid email address.                                                                        |
| `min:N`, `max:N`                         | String length (in characters) or numeric value is at least / at most N.                       |
| `in:a,b,c`                               | The value is one of the listed options.                                                       |
| `date`                                   | A string that `strtotime()` can parse.                                                        |
| `confirmed`                              | Equals `{field}_confirmation`.                                                                |
| `unique:table,column,ignoreId,idColumn`  | No row in `table` has this value. `column` defaults to the field name; `ignoreId` skips one row. |
| `exists:table,column`                    | A row in `table` has this value.                                                              |

`unique` and `exists` query through the Eloquent connection.

## Using the validator directly

```php
use VtPhp\Validation\Validator;

$validator = Validator::make($data, ['email' => 'required|email']);

if ($validator->fails()) {
    $errors = $validator->errors(); // array<string, array<int, string>>
}

$clean = $validator->validated(); // throws ValidationException on failure
```

## `VtPhp\Http\Request`

Injected into any controller action that type-hints it:

| Method                                         | Description                                                    |
| ---------------------------------------------- | -------------------------------------------------------------- |
| `validate(array $rules)`                       | Validate `all()` and return only the validated fields.         |
| `user(?string $guard = null)`                  | The authenticated user, or `null`.                             |
| `method()`                                     | HTTP method.                                                   |
| `path()`                                       | URI path.                                                      |
| `query(string $key, mixed $default = null)`    | A single query-string value.                                   |
| `input(string $key, mixed $default = null)`    | A value from query + parsed body (the body wins).              |
| `all()`                                        | Query params merged with the parsed body.                      |
| `json()`                                       | Parsed JSON body as an array.                                  |
| `has(string $key)`                             | Whether a key exists in `all()`.                               |
| `header(string $key, ?string $default = null)` | A request header.                                              |
| `cookie(string $key, ?string $default = null)` | An incoming cookie value.                                      |
| `bearerToken()`                                | The `Bearer` token from the `Authorization` header, if any.    |
| `route(string $key, mixed $default = null)`    | A raw matched route parameter (the id, not the bound model).   |
| `psr()`                                        | The underlying PSR-7 `ServerRequestInterface`.                 |
