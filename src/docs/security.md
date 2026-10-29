# Security

VtPhp protects your API against the most common attacks out of the box. This
page explains what each protection does, what your frontend needs to send,
and the few settings you must change before going live.

## The quick version

Most protections are already on. For production, check these settings in
your `.env` file (the file holding your app's settings):

```dotenv
# .env (production)
APP_ENV=production
APP_DEBUG=false
TRUSTED_PROXIES=10.0.0.0/8          # only if you run behind a load balancer
CACHE_DRIVER=database               # a store every server shares
CORS_ALLOWED_ORIGINS=https://app.example.com
```

And make your frontend send write requests as JSON:

```js
// your frontend code
fetch('/api/v1/logout', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
});
```

The rest of this page explains why.

## Security headers

Every response VtPhp sends, including error responses, carries headers that
tell browsers to be careful with it:

| Header                      | Value                                          | What it prevents                                    |
| --------------------------- | ---------------------------------------------- | --------------------------------------------------- |
| `X-Content-Type-Options`    | `nosniff`                                      | Browsers guessing a response is a script.           |
| `X-Frame-Options`           | `DENY`                                         | Other sites showing your API inside a frame.        |
| `Referrer-Policy`           | `no-referrer`                                  | Leaking your URLs to other sites.                   |
| `Content-Security-Policy`   | `default-src 'none'; frame-ancestors 'none'`   | Running any script or loading any file from a response. |
| `Cache-Control`             | `no-store`                                     | Browsers or proxies keeping copies of private data. |
| `Strict-Transport-Security` | `max-age=31536000` (HTTPS requests only)       | Browsers ever using plain HTTP for your site again. |

The last one, often called HSTS, is only sent when the request came in over
HTTPS.

You don't have to do anything. If one endpoint should be cacheable, set the
header yourself and VtPhp leaves it alone:

```php
// app/Http/Controllers/StatusController.php
return response()->json(['ok' => true])->header('Cache-Control', 'public, max-age=60');
```

To change the defaults, edit `security_headers` and `hsts` in
`config/http.php`. Set a header to `null` to stop sending it. You can also
use `HSTS_ENABLED`, `HSTS_MAX_AGE` and `HSTS_INCLUDE_SUBDOMAINS` in `.env`.

## Rate limiting

Rate limiting caps how many requests one person can make in a minute. It
stops attackers guessing passwords and stops your app being used to spam
people with emails.

Two limits are switched on for you in `routes/api.php`:

| Limit  | Applies to                                                    | Default             | Counted per                  |
| ------ | ------------------------------------------------------------- | ------------------- | ---------------------------- |
| `api`  | every `/api` route                                            | 60 per minute       | logged-in user, else IP address |
| `auth` | login, sign-up, password reset, verification email (each separately) | 5 per minute | IP address + submitted email |

When someone goes over a limit they get this:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 42
```

```json
{
  "success": false,
  "error": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Too many attempts. Please try again in 42 seconds.",
    "retry_after": 42
  }
}
```

Normal responses also carry `X-RateLimit-Limit` and `X-RateLimit-Remaining`,
so a client can see how many requests it has left.

Counting `auth` by IP address *and* email has two benefits. An attacker
somewhere else can't lock a real user out, and one attacker can't try
thousands of accounts without hitting the overall `api` limit.

Change the numbers in `.env`:

```dotenv
# .env
RATE_LIMIT_API=60
RATE_LIMIT_AUTH=5
RATE_LIMIT_STORE=        # empty = use CACHE_DRIVER
```

**Important:** the counts live in a cache store. Every server must share that
store, so use `database` or `redis`. The `array` store forgets everything
after each request, which effectively switches rate limiting off.

### Adding your own limit

Add an entry to `limiters` in `config/rate_limiting.php`:

```php
// config/rate_limiting.php
'limiters' => [
    // ...the existing api and auth entries...
    'uploads' => ['max_attempts' => 10, 'decay_seconds' => 3600, 'by' => 'user'],
],
```

Then attach it to a route:

```php
// routes/api.php
use VtPhp\Middleware\ThrottleRequests;

$router->post('/uploads', [UploadController::class, 'store'], 'uploads.store', [ThrottleRequests::using('uploads')]);
```

`by` can be `ip`, `user` (the logged-in user, or the IP for guests) or
`ip+email`. Add `'per_route' => true` to count each endpoint separately.

## Behind a load balancer: `TRUSTED_PROXIES`

A load balancer or reverse proxy is a server that sits in front of your app
and passes requests on to it. Without extra setup, every request seems to
come from the proxy's IP address over plain HTTP. That breaks rate limiting
(everyone shares one counter) and secure cookies.

List your proxies so VtPhp believes the headers they add:

```dotenv
# .env
TRUSTED_PROXIES=10.0.0.0/8,192.168.1.10
```

You can list single IP addresses or ranges such as `10.0.0.0/8`. Both IPv4
and IPv6 work. After that:

- `$request->ip()` returns the real visitor's address.
- `$request->isSecure()` is `true` when the visitor used HTTPS.
- The session cookie is marked `Secure` automatically on HTTPS requests,
  unless you force it with `SESSION_SECURE_COOKIE=true` or `false`.

Headers from servers that aren't on your list are ignored, so visitors can't
fake their IP address. Use `TRUSTED_PROXIES=*` only if your app can't be
reached except through the proxy.

## CSRF protection

CSRF (cross-site request forgery) is an attack where a malicious website
makes a logged-in user's browser send a request to your API, for example to
log them out or change their details. The browser attaches the user's
session cookie automatically, so the request looks genuine.

VtPhp blocks this. A `POST`, `PUT`, `PATCH` or `DELETE` request that carries
the session cookie must either:

- send a JSON body with `Content-Type: application/json`, or
- include the header `X-Requested-With`.

Otherwise it's refused:

```json
{
  "success": false,
  "error": {
    "code": "CSRF_REJECTED",
    "message": "Cross-site request rejected. Send a JSON body (Content-Type: application/json) or an X-Requested-With header."
  }
}
```

This works because browsers only let other websites send a few simple
request types without asking permission first. JSON and custom headers make
the browser ask first, and [CORS](cors.md) refuses sites you haven't
allowed.

What this means for you:

- Most HTTP clients send JSON already. For requests without a body, such as
  logout, add `X-Requested-With: XMLHttpRequest`.
- Requests without the session cookie aren't checked. Server-to-server calls
  and first-time logins are unaffected.
- To skip the check for a path, such as an incoming webhook, add it to
  `csrf.except` in `config/http.php`, for example `'webhooks/*'`.

## Login and password protections

These work automatically:

- **No account guessing by timing.** A failed login takes the same time
  whether or not the email exists, so attackers can't use response times to
  find real accounts.
- **No account guessing through email errors.** If sending a password-reset
  or verification email fails, the endpoint still gives the same reply. The
  failure is written to your log instead. This uses `Mailer::trySend()`.
- **Password length limit.** The password scrambler (bcrypt) ignores
  everything after 72 bytes, so sign-up and password reset reject longer
  passwords using the `max_bytes:72` rule. Accented letters and emoji count
  as more than one byte.
- **Sessions.** Logging in gives the user a fresh session ID, and changing a
  password logs out every other device. See
  [Authentication](authentication.md).

## Production safeguards

When `APP_ENV=production`, commands that change or destroy data refuse to
run unless you add `--force`:

```bash
php forge migrate --force
```

This covers `migrate`, `migrate:rollback`, `db:seed` and `key:generate`.
Changing `APP_KEY` breaks every signed link you've already emailed, so be
careful with `key:generate`. The sample `UserSeeder`, whose accounts use the
password `password`, never runs in production, even with `--force`.

## Common mistakes

- **Rate limiting doesn't seem to work.** Your cache is probably `array`. Set
  `CACHE_DRIVER=database` (and run `php forge migrate`) or set
  `RATE_LIMIT_STORE`.
- **Everyone gets `429` at once behind a load balancer.** Set
  `TRUSTED_PROXIES` so each visitor has their own counter.
- **`403 CSRF_REJECTED` from your frontend.** Send JSON, or add
  `X-Requested-With: XMLHttpRequest`.
- **Leaving `APP_DEBUG=true` in production.** Error responses would then show
  internal details such as file paths. See [Errors](exceptions.md).

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false` and a unique `APP_KEY`.
- HTTPS, and `TRUSTED_PROXIES` if you use a load balancer.
- `CACHE_DRIVER` and `RATE_LIMIT_STORE` on a shared store (`database` or
  `redis`).
- `CORS_ALLOWED_ORIGINS` limited to your own frontends.
- Real email settings with `MAIL_ENCRYPTION` set to `ssl` or `tls`.

## Next steps

- [Deploying](deploying.md): the full guide to going live.
- [CORS](cors.md): letting your frontend call the API.
- [Authentication](authentication.md): logins, sessions and protected routes.
