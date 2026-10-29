# Authentication

`config/auth.php` defines named **guards**, each with a `driver` and a
`provider`:

```php
'guards' => [
    'api' => ['driver' => 'token', 'provider' => 'users'],
    'web' => ['driver' => 'session', 'provider' => 'users'],
],
```

Resolve a guard with the `auth()` helper:

```php
auth('web')->attempt(['email' => $email, 'password' => $password]); // bool
auth('web')->user();    // ?object — the authenticated user, or null
auth('web')->id();      // int|string|null
auth('web')->check();   // bool
auth('web')->guest();   // bool
auth('web')->login($user);
auth('web')->logout();
```

## The `web` (session) guard

`VtPhp\Auth\SessionGuard` stores the authenticated user's id in the current
request's `Session` (see [Sessions, cookies & cache](sessions-cookies-cache.md))
and re-resolves the user via the bound `UserProviderInterface` on each
request. Logging in/out rotates the session id (`regenerate()`/`invalidate()`)
to guard against session fixation.

The sample `app/Controllers/AuthController.php` exposes:

| Method | URI              | Description                                                          |
| ------ | ---------------- | -------------------------------------------------------------------- |
| `POST` | `/api/v1/login`  | `attempt()` credentials, sets the session cookie on success.         |
| `POST` | `/api/v1/logout` | Invalidates the session.                                             |
| `GET`  | `/api/v1/me`     | Returns the authenticated user (protected by `Authenticate::class`). |

## Protecting routes

Attach the `Authenticate` middleware per-route (it throws a 401
`AuthenticationException` for guests):

```php
#[Route(method: 'GET', path: '/me', name: 'auth.me', middleware: [Authenticate::class])]
public function me(): UserResource
{
    return UserResource::make(auth('web')->user());
}
```

`Authenticate`'s constructor takes an optional `string $guard = 'web'`, so it
works out of the box for the session guard; construct your own middleware
subclass (or extend the pattern) if you need to guard a route with a
different guard name.

## Adding a user provider for a new model

The framework layer (`src/`) never references your concrete `App\` model
classes — `VtPhp\Auth\UserProviderInterface` is a generic contract that your
app implements and binds:

```php
// src/Auth/UserProviderInterface.php (already provided by the framework)
interface UserProviderInterface
{
    public function retrieveById(int|string $id): ?object;
    public function retrieveByCredentials(array $credentials): ?object;
    public function validateCredentials(object $user, array $credentials): bool;
}
```

```php
// app/Auth/EloquentUserProvider.php
final class EloquentUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function retrieveById(int|string $id): ?object
    {
        return $this->users->find((int) $id);
    }

    public function retrieveByCredentials(array $credentials): ?object
    {
        return $this->users->findByEmail((string) ($credentials['email'] ?? ''));
    }

    public function validateCredentials(object $user, array $credentials): bool
    {
        /** @var \App\Models\User $user */
        return password_verify((string) ($credentials['password'] ?? ''), (string) $user->password);
    }
}
```

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(UserProviderInterface::class, EloquentUserProvider::class);
```

## The `api` (token) guard

`config/auth.php`'s `api` guard (`driver: token`) is the default guard used
by the sample CRUD endpoints — see `config/auth.php`'s `tokens.expiration`
and `AUTH_TOKEN_TTL` for its TTL. Bearer tokens are read via
`$request->bearerToken()`.
