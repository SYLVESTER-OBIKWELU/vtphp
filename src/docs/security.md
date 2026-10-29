# Security

What the framework does for you, and what to configure for production.

## Behind a load balancer: `TRUSTED_PROXIES`

Behind a proxy, every request appears to come from the proxy's IP over
plain HTTP. List your proxies so their `X-Forwarded-For` and
`X-Forwarded-Proto` headers are believed:

```dotenv
TRUSTED_PROXIES=10.0.0.0/8,192.168.1.10   # IPs or CIDR ranges, IPv4 or IPv6
```

`$request->ip()` then returns the real client, and `$request->isSecure()`
reflects HTTPS. Headers from anyone else are ignored, and
`X-Forwarded-For` is read from the right, so clients can't spoof their IP
by adding fake entries. Use `*` only if the app can't be reached except
through the proxy.

The session cookie is marked `Secure` automatically on HTTPS requests when
`SESSION_SECURE_COOKIE` is empty (the default). Set it to `true`/`false` to
force it either way.

## Security headers

Every response, including errors, gets these unless the controller already
set the header:

| Header | Default |
| --- | --- |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `no-referrer` |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` |
| `Cache-Control` | `no-store` |
| `Strict-Transport-Security` | `max-age=31536000` (HTTPS requests only) |

Change them in `config/http.php` (`security_headers`, `hsts`; set a header
to `null` to drop it) or with `HSTS_ENABLED`, `HSTS_MAX_AGE` and
`HSTS_INCLUDE_SUBDOMAINS`. To allow caching on one endpoint, set
`Cache-Control` in that response yourself.

## Rate limiting

Named limiters live in `config/rate_limiting.php` and are attached as route
middleware:

```php
use VtPhp\Middleware\ThrottleRequests;

$router->group(['prefix' => '/api/v1', 'middleware' => [ThrottleRequests::using('api')]], function (Router $router): void {
    $router->post('/login', [AuthController::class, 'login'], 'auth.login', [ThrottleRequests::using('auth')]);
});
```

| Limiter | Default | Keyed by |
| --- | --- | --- |
| `api` (all `/api` routes) | 60/min (`RATE_LIMIT_API`) | signed-in user, else IP |
| `auth` (login, sign-up, password reset, verification email) | 5/min per endpoint (`RATE_LIMIT_AUTH`) | IP + submitted email |

Over the limit, the response is `429 TOO_MANY_REQUESTS` with `Retry-After`.
Other responses carry `X-RateLimit-Limit` and `X-RateLimit-Remaining`.
Keying `auth` by IP *and* email means an attacker elsewhere can't lock a real
user out, and one IP can't hammer many accounts beyond the `api` limit.

Counts are kept in `RATE_LIMIT_STORE` (default: your `CACHE_DRIVER`). Use a
store every server shares, such as `database` or `redis`; `array` keeps
counts for one request only, which disables throttling. Add your own
limiter by adding an entry to `limiters`, with `by` set to `ip`, `user` or
`ip+email`, and optionally `per_route`.

## CSRF protection

With session-cookie authentication, a malicious site could make a logged-in
user's browser submit requests. `PreventRequestForgery` stops this: any
`POST`, `PUT`, `PATCH` or `DELETE` that carries the session cookie must be
JSON (`Content-Type: application/json`) or send an `X-Requested-With`
header. Otherwise it gets `403 CSRF_REJECTED`.

Your frontend therefore needs to send JSON (most HTTP clients do by default)
or add the header, even for body-less requests such as logout:

```js
fetch('/api/v1/logout', {
  method: 'POST',
  credentials: 'include',
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
});
```

Why this works: browsers can send a cross-site request with cookies and *no*
CORS preflight only as a form, `text/plain` or a body-less POST. JSON and
custom headers force a preflight, which [CORS](cors.md) refuses for origins
you haven't allowed. Requests without the session cookie are unaffected.
Exempt paths (e.g. `webhooks/*`) under `csrf.except` in `config/http.php`.

## Login and passwords

- **No account probing by timing:** failed logins take the same time whether
  or not the email exists. Unknown emails are checked against a dummy bcrypt
  hash of the same cost.
- **No account probing by mail errors:** password-reset and verification
  endpoints give the same reply even if sending the email fails (via
  `Mailer::trySend()`, which logs the failure).
- **Password length:** bcrypt ignores everything after 72 bytes, so sign-up
  and reset reject longer passwords with `max_bytes:72`, rather than
  accepting a password whose ending doesn't matter.
- Login rotates the session id; logout and password changes end sessions
  (see [Authentication](authentication.md)).

## Production safeguards

With `APP_ENV=production`, `migrate`, `migrate:rollback`, `db:seed` and
`key:generate` refuse to run without `--force`. Rotating `APP_KEY` breaks
every signed link already emailed. The sample `UserSeeder`, whose accounts
use the password `password`, never runs in production.

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, and a unique `APP_KEY`.
- `TRUSTED_PROXIES` set if you run behind a load balancer; serve over HTTPS.
- `CACHE_DRIVER`/`RATE_LIMIT_STORE` on a shared store (`database` or `redis`).
- `CORS_ALLOWED_ORIGINS` limited to your frontends.
- Real SMTP settings with `MAIL_ENCRYPTION` (`ssl` or `tls`).
