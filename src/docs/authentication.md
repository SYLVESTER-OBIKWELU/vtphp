# Authentication

Authentication means knowing *who* is making a request. VtPhp logs users in
with a session: after a successful login, the browser gets a cookie, and the
framework recognises that cookie on every later request.

## The quick version

The app already has working login routes. Log in:

```http
POST /api/v1/login
Content-Type: application/json

{"email": "ada@example.com", "password": "password"}
```

```json
{"data": {"id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "email_verified": false, "created_at": "2026-10-29T12:00:00+00:00"}}
```

The response also sets a `vtphp_session` cookie. Send that cookie back and you
are logged in:

```http
GET /api/v1/me
Cookie: vtphp_session=...
```

```json
{"data": {"id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "email_verified": false, "created_at": "2026-10-29T12:00:00+00:00"}}
```

Without the cookie, `/me` answers `401` with the error code `UNAUTHENTICATED`.
(The sample users exist after `php forge db:seed`.)

## The built-in endpoints

| Method | Path | What it does |
| --- | --- | --- |
| `POST` | `/api/v1/login` | Checks email and password, starts a logged-in session. |
| `POST` | `/api/v1/logout` | Ends the session. |
| `GET` | `/api/v1/me` | Returns the logged-in user. |
| `POST` | `/api/v1/users` | Signs up a new user (no login needed). |

A wrong email or password returns `401` with
`{"message": "These credentials do not match our records."}`. The message is
the same either way, so nobody can use it to find out which emails are
registered.

## Using `auth()` in your code

The `auth()` helper gives you the current user and login controls:

```php
auth()->check();     // true if someone is logged in
auth()->guest();     // true if nobody is
auth()->user();      // the logged-in User, or null
auth()->id();        // their id, or null

auth()->attempt(['email' => $email, 'password' => $password]); // log in; true or false
auth()->login($user);   // log in a user you already have
auth()->logout();       // log out
```

In a controller, `$request->user()` returns the same thing as `auth()->user()`:

```php
// app/Http/Controllers/AuthController.php
use VtPhp\Http\Request;

public function me(Request $request): UserResource
{
    return UserResource::make($request->user());
}
```

`auth()` only works during a web request, because it reads the session that
each request starts. It isn't available in `php forge` commands.

## Protecting routes

To make a route require login, add the `Authenticate` middleware to it.
Middleware is code that runs before your controller and can stop the
request.

```php
// routes/api.php
use VtPhp\Middleware\Authenticate;

$router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);
```

Guests now get `401 UNAUTHENTICATED` and your controller never runs. You can
protect a whole resource or a whole group the same way:

```php
// routes/api.php
$router->apiResource('posts', PostController::class, ['middleware' => [Authenticate::class]]);

$router->group(['middleware' => [Authenticate::class]], function (Router $router): void {
    // every route in here needs a logged-in user
});
```

## Letting users change only their own data

Logging in proves *who* someone is. You still have to check *what* they may
do. The sample app keeps sign-up public, requires login for the rest of the
`users` routes, and only lets users edit or delete their own account. Anyone
else gets `403 FORBIDDEN`.

For updates, the check lives in the form request's `authorize()` method:

```php
// app/Http/Requests/UpdateUserRequest.php
public function authorize(): bool
{
    return (string) auth()->id() === (string) $this->route('user');
}
```

For deletes, it's a check in the controller:

```php
// app/Http/Controllers/UserController.php
if ((string) auth()->id() !== (string) $user->id) {
    throw new AuthorizationException('You may only delete your own account.');
}
```

VtPhp doesn't have policies or gates (Laravel's rule classes for this) yet,
so write checks like these where you need them.

## Calling the API from a browser app

Three things matter when your frontend runs in a browser:

1. **Send writes as JSON.** Any `POST`, `PUT`, `PATCH` or `DELETE` that
   carries the session cookie must have `Content-Type: application/json` or an
   `X-Requested-With` header. Otherwise it's rejected with `403 CSRF_REJECTED`.
   This stops other websites from submitting forms as your user. For a request
   with no body, such as logout, add the header:

   ```js
   // your frontend code
   fetch('/api/v1/logout', {
     method: 'POST',
     credentials: 'include',
     headers: { 'X-Requested-With': 'XMLHttpRequest' },
   });
   ```

2. **Send the cookie.** Use `credentials: 'include'` with `fetch`.
3. **On a different domain?** Switch on CORS. See [CORS](cors.md).

## What VtPhp does to keep logins safe

You don't need to do anything for these. They're on by default.

- **Passwords are never stored as text.** They're hashed with bcrypt when
  saved (see [Models](models.md#mutators-changing-a-value-as-its-saved)).
- **Passwords are limited to 72 bytes.** bcrypt ignores anything longer, so
  sign-up and password reset reject longer passwords (the `max_bytes:72`
  validation rule) instead of quietly cutting them short.
- **Login attempts are rate limited.** After 5 tries in a minute for the same
  email from the same address, login answers `429 TOO_MANY_REQUESTS` with a
  `Retry-After` header saying how many seconds to wait. Sign-up, password reset
  and verification emails have the same limit. See [Security](security.md#rate-limiting).
- **Failed logins take the same time** whether or not the email exists, so
  timing can't reveal registered accounts.
- **The session id changes** on login and logout, which blocks an attack
  called session fixation.
- **Changing a password logs out every other session.** The session stores a
  fingerprint of the password. After a password reset, old sessions (including
  any an attacker stole) stop working on their next request.
- **Emails are case-insensitive.** `ADA@example.com` logs in as `ada@example.com`.

## Password reset and email verification

These flows are built in:

| Method | Path | What it does |
| --- | --- | --- |
| `POST` | `/api/v1/password/forgot` | Emails a reset link (`email`). |
| `POST` | `/api/v1/password/reset` | Sets a new password (`email`, `token`, `password`, `password_confirmation`). |
| `POST` | `/api/v1/email/verification-notification` | Emails a verification link (`email`). |
| `GET` | `/api/v1/email/verify/{user}/{hash}` | The link inside that email. |

- Both "send me an email" endpoints give the same answer whether or not the
  address is registered.
- Reset links point at your frontend: `FRONTEND_URL/reset-password?token=...&email=...`.
  That page should ask for the new password and send it to
  `/api/v1/password/reset`. Reset tokens expire after 60 minutes and work once.
- Verification links are signed with your `APP_KEY` and expire after 60
  minutes, so they can't be forged or reused later. Changing your email marks
  the account as unverified again.

Nothing in VtPhp blocks unverified users yet. If you need that, check
`$request->user()->hasVerifiedEmail()` in your controller.

See [Mail](mail.md) for how these emails are sent.

## Going further

### How users are looked up

`config/auth.php` decides how logins work:

```php
// config/auth.php
'default' => ['guard' => 'web'],

'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
],

'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
],
```

- A **guard** decides *how* a user is recognised. `session` is the only
  driver available.
- A **provider** decides *where* users come from. `eloquent` reads them
  through a model. To log in a different model, point `model` at it. It needs
  a `password` column holding a bcrypt hash.

`attempt()` finds the user by every field you pass except `password`, then
checks the password.

### A completely custom user lookup

To load users from somewhere else (for example, another service), implement
`VtPhp\Auth\UserProviderInterface`, bind it in a service provider, and remove
that provider's entry from `providers` in `config/auth.php`:

```php
// app/Providers/AppServiceProvider.php
use VtPhp\Auth\UserProviderInterface;

public function register(): void
{
    $this->app->singleton(UserProviderInterface::class, MyUserProvider::class);
}
```

## Common mistakes

- **`/me` keeps returning 401.** The cookie isn't being sent. In a browser,
  use `credentials: 'include'`. Across domains, also check [CORS](cors.md).
- **`403 CSRF_REJECTED` on logout or another write.** Send JSON, or add
  `X-Requested-With: XMLHttpRequest`.
- **`429 TOO_MANY_REQUESTS` while testing.** You hit the login limit. Wait
  for the number of seconds in `Retry-After`.
- **"No application key is set" when sending verification emails.** Run
  `php forge key:generate`.
- **Looking for API tokens** (bearer tokens for mobile apps or other
  servers)? They aren't available yet. Logins work through the session cookie
  only.

## Next steps

- [Security](security.md): rate limits, CSRF and other protections.
- [Sessions, cookies & cache](sessions-cookies-cache.md): where logins are stored.
- [Validation](validation.md): form requests and `authorize()`.
