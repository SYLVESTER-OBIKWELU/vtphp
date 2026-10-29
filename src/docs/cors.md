# CORS

Browsers block JavaScript on one origin (say `http://localhost:3000`) from
reading responses from another (this API) unless the API allows it with
CORS headers. The policy lives in `config/cors.php` and is applied by the
global `VtPhp\Middleware\HandleCors` middleware.

## Turning it on

CORS is **off by default**. Enable it and list the origins allowed to call
the API in `.env`:

```dotenv
CORS_ENABLED=true
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
```

Or list them in the policy file. Both lists are merged:

```php
// config/cors.php
'allowed_origins' => [
    'https://app.example.com',
    'https://*.example.com',   // any subdomain (not example.com itself)
    ...$fromEnv,               // CORS_ALLOWED_ORIGINS
],
```

An origin is a scheme, host and optional port, with no path:
`https://app.example.com`, not `https://app.example.com/login`. Matching is
case-insensitive, and a trailing slash is ignored. `'*'` allows every
origin; use it only for fully public APIs.

## The policy

| Key                    | Default            | Meaning                                                                    |
| ---------------------- | ------------------ | -------------------------------------------------------------------------- |
| `enabled`              | `CORS_ENABLED`     | Master switch.                                                             |
| `paths`                | `['api/*']`        | Request paths the policy covers (`*` is a wildcard). `/health` isn't covered. |
| `allowed_origins`      | `[]` + env list    | Who may call the API (see above).                                          |
| `allowed_methods`      | `['*']`            | Methods allowed in preflights; `*` allows whatever the browser asks for.   |
| `allowed_headers`      | `['*']`            | Request headers allowed in preflights; `*` allows whatever the browser asks for. |
| `exposed_headers`      | `['X-Request-ID']` | Response headers browser JavaScript may read.                              |
| `max_age`              | `0`                | Seconds browsers may cache a preflight (`0` = don't send the header).      |
| `supports_credentials` | `CORS_SUPPORTS_CREDENTIALS` | Allow cookies on cross-origin requests (see below).               |

## How requests are handled

- **Preflight requests** (`OPTIONS` with `Access-Control-Request-Method`) are
  answered immediately with `204` and the allowed methods and headers. They
  never reach the router, start a session, or run a controller.
- **Other requests** run normally and get `Access-Control-Allow-Origin` and
  related headers. Error responses (401, 404, 422, 500) get them too, so your
  frontend can read the JSON error body.
- **Origins not in the list** get no `Access-Control-Allow-Origin` header,
  so the browser hides the response. CORS is a browser safeguard, not
  authentication. Non-browser clients ignore it, so keep protecting routes
  with `Authenticate`.

## Cookies and the session

To log in from a frontend on another origin using the session cookie, set
`CORS_SUPPORTS_CREDENTIALS=true` and send requests with credentials
(`fetch(url, { credentials: 'include' })`). Browsers also only send cookies
cross-site when the cookie allows it:

- **Same site** (e.g. `app.example.com` → `api.example.com`): the default
  `SESSION_SAME_SITE=lax` works.
- **Different sites** (e.g. `localhost:3000` → `api.example.com`): set
  `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true`, which requires HTTPS.

When credentials are on and `allowed_origins` contains `'*'`, the request's
own origin is echoed back, because browsers reject `*` together with
credentials.
