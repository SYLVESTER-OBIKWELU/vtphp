# CORS: letting browser apps call your API

CORS (Cross-Origin Resource Sharing) is a browser rule that decides which
websites may call your API from JavaScript. If your frontend lives on a
different address than your API, for example a React app on
`http://localhost:3000` talking to an API on `http://localhost:8000`, you
need to switch CORS on and tell VtPhp which addresses to trust.

## The quick version

1. Open your `.env` file (the file holding your app's settings).
2. Switch CORS on and list your frontend's address:

   ```dotenv
   # .env
   CORS_ENABLED=true
   CORS_ALLOWED_ORIGINS=http://localhost:3000
   ```

3. That's it. Browser code on `http://localhost:3000` can now read your API's
   responses.

CORS is **off by default**. While it's off, nothing changes: your API still
answers every request, but browsers hide the answers from pages on other
addresses.

## What is an "origin"?

An origin is the first part of a web address: the scheme (`http` or
`https`), the host name and, optionally, the port.

| Address you see in the browser    | Its origin                  |
| --------------------------------- | --------------------------- |
| `https://app.example.com/login`   | `https://app.example.com`   |
| `http://localhost:3000/dashboard` | `http://localhost:3000`     |

Always list origins, never full addresses. Write `https://app.example.com`,
not `https://app.example.com/login`. Capital letters and a trailing slash
don't matter, so `https://App.Example.com/` matches too.

## Listing several frontends

Separate origins with commas:

```dotenv
# .env
CORS_ENABLED=true
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com,https://admin.example.com
```

You can also list them in code, in `config/cors.php`. The two lists are
added together, so use whichever you prefer:

```php
// config/cors.php
'allowed_origins' => [
    'https://app.example.com',
    'http://localhost:3000',
    ...$fromEnv, // the CORS_ALLOWED_ORIGINS list from .env
],
```

## Allowing every subdomain

A pattern with `*` allows all subdomains of a site:

```php
// config/cors.php
'allowed_origins' => [
    'https://*.example.com',
],
```

This matches `https://admin.example.com` and `https://eu.admin.example.com`.
It does **not** match the bare `https://example.com`, a look-alike such as
`https://evil-example.com`, or `https://admin.example.com.evil.org`.

The single value `'*'` allows every website on the internet. Only use it for
a fully public API with no logins.

## What happens to each request

When CORS is on, VtPhp's `HandleCors` middleware (code that runs before and
after every request) looks at each request coming from a browser:

- **Preflight requests.** Before some requests, the browser first asks
  "may I?" with an `OPTIONS` request. VtPhp answers it straight away with
  `204 No Content` and the allowed methods and headers. It never reaches
  your controllers and never creates a session.
- **Normal requests.** They run as usual, and VtPhp adds
  `Access-Control-Allow-Origin` and related headers to the response.
- **Error responses get the headers too.** A `401`, `404` or `422` still
  carries CORS headers, so your frontend can read the error message instead
  of seeing a vague "CORS error".
- **Unlisted origins** get no `Access-Control-Allow-Origin` header, so the
  browser hides the response from them.

CORS only applies to paths listed in `paths` (by default `api/*`). Your
`/health` check is not affected.

## Logging in from another origin (cookies)

VtPhp keeps users logged in with a session cookie, a small value the browser
stores and sends back on every request. Browsers don't send cookies to
another origin unless you allow it on both sides.

1. Allow credentials in `.env`:

   ```dotenv
   # .env
   CORS_SUPPORTS_CREDENTIALS=true
   ```

2. Tell your frontend to send cookies:

   ```js
   // your frontend code
   fetch('https://api.example.com/api/v1/me', { credentials: 'include' });
   ```

3. Check the cookie's `SameSite` setting, which decides when browsers send
   it across sites:
   - **Same site**, for example `app.example.com` calling `api.example.com`:
     the default `SESSION_SAME_SITE=lax` works.
   - **Different sites**, for example `localhost:3000` calling
     `api.example.com`: set `SESSION_SAME_SITE=none` and
     `SESSION_SECURE_COOKIE=true`. This needs HTTPS.

When cookies are allowed, every write request (`POST`, `PUT`, `PATCH`,
`DELETE`) must be sent as JSON or carry an `X-Requested-With` header. See
[CSRF protection](security.md#csrf-protection) for why.

## Going further

Every option lives in `config/cors.php`:

| Option                 | Default                      | What it does                                                      |
| ---------------------- | ---------------------------- | ----------------------------------------------------------------- |
| `enabled`              | `CORS_ENABLED` (false)       | The on/off switch.                                                |
| `paths`                | `['api/*']`                  | Which paths CORS applies to. `*` matches anything.                |
| `allowed_origins`      | your list + `CORS_ALLOWED_ORIGINS` | Who may call the API.                                       |
| `allowed_methods`      | `['*']`                      | Methods allowed in preflights. `*` allows whatever the browser asks for. |
| `allowed_headers`      | `['*']`                      | Request headers allowed in preflights.                            |
| `exposed_headers`      | `['X-Request-ID']`           | Response headers your JavaScript may read.                        |
| `max_age`              | `0`                          | Seconds a browser may remember a preflight answer. `0` sends nothing. |
| `supports_credentials` | `CORS_SUPPORTS_CREDENTIALS` (false) | Allow cookies on cross-origin requests.                    |

When `supports_credentials` is on and `allowed_origins` contains `'*'`, VtPhp
sends back the caller's own origin instead of `*`, because browsers refuse
`*` together with cookies.

## Common mistakes

- **Listing a full address.** `https://app.example.com/login` never matches.
  Use `https://app.example.com`.
- **Wrong port.** `http://localhost:3000` and `http://localhost:5173` are
  different origins. List the one your frontend actually runs on.
- **Thinking CORS protects your data.** It doesn't. CORS only controls what
  *browsers* show to web pages. Tools such as curl and server-to-server
  calls ignore it. Protect private routes with login (see
  [Authentication](authentication.md)).
- **Cookies not arriving.** Check all three: `CORS_SUPPORTS_CREDENTIALS=true`,
  `credentials: 'include'` in your frontend, and the `SameSite` setting
  above.
- **`403 CSRF_REJECTED` on a write.** Send the body as JSON, or add the
  header `X-Requested-With: XMLHttpRequest`.

## Next steps

- [Security](security.md): CSRF protection, rate limits and more.
- [Authentication](authentication.md): logging users in and out.
- [Deploying](deploying.md): production settings, including CORS.
