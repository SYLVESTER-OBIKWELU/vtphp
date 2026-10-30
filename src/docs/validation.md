# Requests & validation

**Validation** means checking what a caller sent before you use it: is the
email really an email, is the password long enough, is that username free?
VtPhp checks input against **rules** you write, and answers bad input with a
clear error, so your code only ever sees clean data.

## The quick version

Create a **form request**, a class that holds the rules for one endpoint:

```powershell
php forge make:request StorePostRequest
```

Fill in the rules:

```php
// app/Http/Requests/StorePostRequest.php
namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
        ];
    }
}
```

Ask for it in your controller:

```php
// app/Http/Controllers/PostController.php
public function store(StorePostRequest $request): PostResource
{
    return PostResource::make(Post::query()->create($request->validated()))->status(201);
}
```

That's it. If the input breaks a rule, your method never runs. The caller gets
this instead:

```http
POST /api/v1/posts
Content-Type: application/json

{"title": ""}
```

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The title field is required.",
    "errors": {"title": ["The title field is required."]}
  }
}
```

The status is `422`. `errors` lists every problem, grouped by field, so a
frontend can show each message next to the right input box.

## How rules are written

Each field gets a list of rules separated by `|`. They're checked left to
right, and every failure is reported:

```php
'email' => 'required|email|unique:users,email',
```

You can also use an array if you prefer: `['required', 'email']`.

A field the caller doesn't send at all is only checked by `required`. So
`'nickname' => 'string|max:30'` makes `nickname` optional: leave it out and
the request passes; send it and it must be text of at most 30 characters.

## The rules you can use

| Rule | Passes when |
| --- | --- |
| `required` | The field is present and not empty (`null` or `''` fail). |
| `nullable` | `null` is allowed; when the value is `null`, the other rules are skipped. |
| `sometimes` | Only check the field if it was sent. Optional fields already behave this way; `sometimes` makes the intent obvious in "update" requests. |
| `string` | It's text. |
| `integer` (or `int`) | It's a whole number, or text like `"42"` or `"-5"`. |
| `numeric` | It's any number, like `12.5` or `"12.5"`. |
| `boolean` (or `bool`) | It's `true`, `false`, `0`, `1`, `"0"` or `"1"`. |
| `array` | It's a list or object. |
| `email` | It's a valid email address. |
| `date` | It's text PHP can read as a date, like `"2026-10-29"`. |
| `min:N` / `max:N` | Its size is at least / at most N (see below). |
| `max_bytes:N` | Its text is at most N bytes long (see below). |
| `in:a,b,c` | It's exactly one of the listed values. |
| `confirmed` | It equals the field with `_confirmation` added, e.g. `password_confirmation`. |
| `unique:table,column` | No row in that table already has this value. |
| `exists:table,column` | A row in that table has this value. |

### How `min` and `max` measure

- With `integer` or `numeric` on the same field, they compare the **number**:
  `"25"` passes `integer|min:18`.
- Otherwise text is measured in **characters**: `max:100` means 100 letters.
- Lists are measured by how many **items** they hold.

### `max_bytes`: for passwords

Passwords are stored with bcrypt, which ignores everything after the 72nd
byte. An accented letter or emoji takes several bytes, so `max:72` isn't
enough. The sample app uses `max_bytes:72` so long passwords are rejected
instead of silently cut short:

```php
'password' => 'required|string|min:8|max_bytes:72|confirmed',
```

### `unique` and `exists`

They check your database:

```php
'email' => 'unique:users,email',        // nobody has this email yet
'author_id' => 'exists:users,id',       // that user exists
```

The column defaults to the field's name, so `'email' => 'unique:users'` works
too. When **updating** a record, ignore the record itself, or saving it with
its own current email would fail:

```php
// app/Http/Requests/UpdateUserRequest.php
public function rules(): array
{
    $userId = (string) $this->route('user');

    return [
        'name' => 'sometimes|string|max:100',
        'email' => "sometimes|email|unique:users,email,{$userId}",
    ];
}
```

## Only clean data gets through

`$request->validated()` returns **only the fields that have rules**. If a
caller sneaks in `"is_admin": true` or `"id": 500`, it's dropped. That's why
passing `validated()` straight to `create()` or `update()` is safe.

```php
$request->validated();              // ['title' => '...', 'body' => '...']
$request->validated('title');       // just one field
$request->validated('status', 'draft'); // one field, with a default
```

A form request is still a normal request, so `input()`, `query()`,
`route()` and `user()` work on it too.

## Going further

### Who may do this? `authorize()`

Return `false` from `authorize()` to refuse the request with `403 FORBIDDEN`
before any rules run. The sample app lets users edit only themselves:

```php
// app/Http/Requests/UpdateUserRequest.php
public function authorize(): bool
{
    return (string) auth()->id() === (string) $this->route('user');
}
```

### Cleaning input first: `prepareForValidation()`

To tidy input before checking it, override `prepareForValidation()` and call
`$this->merge()`. The sample app's `NormalizesEmail` trait lowercases emails
this way, so `Ada@Example.com` and `ada@example.com` count as the same:

```php
// app/Http/Requests/Concerns/NormalizesEmail.php
protected function prepareForValidation(): void
{
    $email = $this->input('email');

    if (is_string($email)) {
        $this->merge(['email' => mb_strtolower(trim($email))]);
    }
}
```

### Quick checks without a class

For a small endpoint, validate right in the controller:

```php
// app/Http/Controllers/SearchController.php
use VtPhp\Http\Request;

public function search(Request $request): array
{
    $data = $request->validate([
        'q' => 'required|string|min:2',
        'page' => 'nullable|integer|min:1',
    ]);

    return ['query' => $data['q']];
}
```

It throws the same `422` error on failure. Outside a request, use the
validator directly:

```php
use VtPhp\Validation\Validator;

$validator = Validator::make($data, ['email' => 'required|email']);

if ($validator->fails()) {
    $errors = $validator->errors(); // ['email' => ['The email field must be a valid email address.']]
}
```

### Reading the request

These methods are available on `VtPhp\Http\Request` and on every form request:

| Method | Gives you |
| --- | --- |
| `input('name', $default)` | A value from the JSON body or the query string (the body wins). |
| `all()` | Every input value. |
| `query('page')` | A value from the query string only, e.g. `?page=2`. |
| `has('name')` | Whether the field was sent. |
| `json()` | The decoded JSON body. |
| `route('id')` | A raw value from the URL, e.g. `{id}`. |
| `header('Accept')` | A request header. |
| `cookie('name')` | An incoming cookie. |
| `bearerToken()` | The token from an `Authorization: Bearer ...` header, if any. |
| `user()` | The logged-in user, or `null`. |
| `ip()` | The caller's IP address (see [Security](security.md)). |
| `isSecure()` | Whether the request came over HTTPS. |
| `method()`, `path()` | The HTTP method and the path. |
| `merge([...])` | Adds or overrides input values. |
| `psr()` | The underlying PSR-7 request object, for advanced use. |

## Common mistakes

- **An optional field fails with "must be a string" when sent as `null`.**
  Leaving a field out is fine, but `"nickname": null` is still checked. Add
  `nullable` to allow `null`.
- **`InvalidArgumentException: Unknown validation rule [requird]`.** VtPhp
  refuses rules it doesn't know, so typos can't silently switch validation
  off. Check the spelling against the table above.
- **Expecting Laravel rules that don't exist here**, like `regex`, `url` or
  `uuid`. They'll throw the same "unknown rule" error. Custom error messages
  (`messages()`) aren't supported yet either.
- **Saving with `$request->all()`.** Use `$request->validated()`; `all()`
  includes fields you never checked.
- **Your own email fails `unique` on update.** Add the record's id as the
  third part: `unique:users,email,{$id}`.

## Next steps

- [Controllers](controllers.md): where form requests are used.
- [Resources](resources.md): shape what you send back.
- [Security](security.md): rate limits and CSRF protection on these endpoints.
- [Your first API](your-first-api.md): validation in a full example.
