# Authentication

`config/auth.php` defines named **guards** (how users are authenticated) and
**providers** (how users are looked up), like Laravel:

```php
'default' => ['guard' => 'web'],

'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
],

'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
],
```

Use the `auth()` helper (default guard) or `auth('name')`:

```php
auth()->attempt(['email' => $email, 'password' => $password]); // bool; logs in on success
auth()->user();    // ?object, the authenticated user or null
auth()->id();      // int|string|null
auth()->check();   // bool
auth()->guest();   // bool
auth()->login($user);
auth()->logout();
```

In a controller, `$request->user()` returns the same user as `auth()->user()`.

## The `web` (session) guard

`VtPhp\Auth\SessionGuard` stores the authenticated user's id in the request's
session (see [Sessions, cookies & cache](sessions-cookies-cache.md)) and
looks the user up again on each request. Logging in and logging out both
rotate the session id, which prevents session fixation.

The session also stores a SHA-256 digest of the user's password hash (never
the hash itself). If the password changes, for example through a reset,
every other session's digest stops matching and those sessions are logged
out on their next request. Sessions created before this check existed adopt
the current digest instead of being logged out.

The sample `app/Http/Controllers/AuthController.php` exposes:

| Method | URI              | Description                                                         |
| ------ | ---------------- | ------------------------------------------------------------------- |
| `POST` | `/api/v1/login`  | `attempt()`s the credentials and sets the session cookie on success. |
| `POST` | `/api/v1/logout` | Invalidates the session.                                            |
| `GET`  | `/api/v1/me`     | Returns the authenticated user (protected by `Authenticate`).       |

## Protecting routes

The `Authenticate` middleware rejects guests with `401 UNAUTHENTICATED`.
Attach it to a route, a resource, or a whole group:

```php
use VtPhp\Middleware\Authenticate;

$router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);

$router->apiResource('users', UserController::class, ['middleware' => [Authenticate::class]]);

$router->group(['middleware' => [Authenticate::class]], function (Router $router): void {
    // every route in here requires a logged-in user
});
```

The sample `routes/api.php` keeps sign-up (`POST /users`) public and puts the
rest of the `users` resource behind `Authenticate`. Authorization ("may *this*
user do it?") is a separate check. `UpdateUserRequest::authorize()` and
`UserController::destroy()` only let users change their own account, and
anything else gets `403 FORBIDDEN`:

```php
public function authorize(): bool
{
    return (string) auth()->id() === (string) $this->route('user');
}
```

## User providers

The `eloquent` provider (`VtPhp\Auth\EloquentUserProvider`) works with any
Eloquent model. Point `model` at it. `attempt()` matches every credential
except `password` against the model's columns, then checks the password with
`password_verify()` against the model's `password` attribute.

For fully custom lookups, implement `VtPhp\Auth\UserProviderInterface`, bind
it in a service provider, and leave the provider's config out of
`auth.providers`. The framework then uses your bound implementation:

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(UserProviderInterface::class, LdapUserProvider::class);
```

Token (bearer) authentication isn't available yet. `$request->bearerToken()`
reads the header if you want to build a guard of your own.
