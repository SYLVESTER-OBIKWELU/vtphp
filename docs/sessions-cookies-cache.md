# Sessions, cookies & cache

## Sessions

`VtPhp\Session\SessionManager` starts a `Session` for each request (bound
into the container by the `StartSession` middleware), backed by a
`SessionStoreInterface` driver configured in `config/session.php`:

- `file` (default) — `storage/framework/sessions/`, serialized with
  `allowed_classes: false` so a tampered/corrupted session file can never be
  deserialized into arbitrary PHP objects.
- `array` — non-persistent, in-memory; only useful for tests/CLI, since data
  doesn't survive past the current process.

Env vars: `SESSION_DRIVER`, `SESSION_LIFETIME` (minutes), `SESSION_COOKIE`,
`SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`.

Access the current session with the `session()` helper (or type-hint
`VtPhp\Session\Session` in anything the container resolves):

```php
session()->put('key', 'value');
session()->get('key');
session()->has('key');
session()->forget('key');
session()->all();
session()->regenerate(); // rotate the session id, keep attributes
session()->invalidate();  // clear attributes and rotate the id
```

## Cookies

Outgoing cookies are queued via the `cookie()` helper
(`VtPhp\Cookie\CookieJar`) and attached to the response as `Set-Cookie`
headers by the `AddQueuedCookiesToResponse` middleware:

```php
cookie()->queue(cookie()->make(
    name: 'remember_me',
    value: $token,
    minutes: 60 * 24 * 30,
));

cookie()->forget('remember_me');
```

Read incoming cookies with `$request->cookie('name')` (or
`$psrRequest->getCookieParams()` on the raw PSR-7 request).

## Cache

The `cache()` helper (`VtPhp\Cache\CacheManager`) wraps a PSR-16
(`symfony/cache`) store resolved from `config/cache.php`:

```php
cache()->put('key', $value, 60);      // ttl in seconds
cache()->forever('key', $value);
cache()->get('key', 'default');
cache()->has('key');
cache()->forget('key');
cache()->flush();

cache()->remember('expensive-key', 60, function () {
    return computeExpensiveThing();
});

cache()->store('redis')->get('key'); // a specific named store, bypassing 'default'
```

Supported drivers (`CACHE_DRIVER` / `config/cache.php`'s `stores`):

| Driver  | Notes                                                          |
| ------- | -------------------------------------------------------------- |
| `array` | In-memory, per-request only. Good default for local dev/tests. |
| `file`  | Persists to `storage/framework/cache/data`.                    |
| `redis` | See below — requires an extra package.                         |

### Redis is opt-in

`symfony/cache` (which provides the Redis adapter) is always installed, but
actually selecting `CACHE_DRIVER=redis` additionally requires either the
`ext-redis` PHP extension or the `predis/predis` Composer package — neither
is a hard dependency of this framework, so installations that don't need
Redis stay lightweight. Configure the connection via `REDIS_HOST`,
`REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CACHE_DB`.
