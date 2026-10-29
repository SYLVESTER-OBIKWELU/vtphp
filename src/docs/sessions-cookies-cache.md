# Sessions, cookies & cache

## Sessions

`VtPhp\Session\SessionManager` starts a `Session` for each request (bound
into the container by the `StartSession` middleware), backed by a
`SessionStoreInterface` driver configured in `config/session.php`:

- `database` (default): a `sessions` table, created by `php forge migrate`.
  Each row stores the session id, the logged-in `user_id` (so a user's
  sessions can be found or revoked with one query), the payload and
  `last_activity`. This lets several app servers share sessions.
  `SESSION_CONNECTION` and `SESSION_TABLE` pick the connection and table.
- `file`: `storage/framework/sessions/`, one file per session.
- `array`: in-memory and not persisted; only useful for tests and CLI.

Payloads are unserialized with `allowed_classes: false`, so a tampered file
or row can never be turned into arbitrary PHP objects.

Env vars: `SESSION_DRIVER`, `SESSION_LIFETIME` (minutes), `SESSION_COOKIE`,
`SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SESSION_CONNECTION`,
`SESSION_TABLE`.

### Expired sessions

Expired sessions are never used. They're also swept away automatically: on
2 in 100 requests (`'lottery' => [2, 100]` in `config/session.php`), the
current store deletes sessions idle for longer than `SESSION_LIFETIME`. To
sweep on a schedule instead, set the lottery to `[0, 100]` and run
`php forge session:gc` from cron.

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

Any string works as a key, including Laravel-style `user:1` or `posts/latest`.
Characters that PSR-16 reserves (`{}()/\@:`) are URL-encoded before they
reach the store, so distinct keys never collide.

Supported drivers (`CACHE_DRIVER` / `config/cache.php`'s `stores`):

| Driver     | Notes                                                                                     |
| ---------- | ----------------------------------------------------------------------------------------- |
| `database` | Default. A `cache` table created by `php forge migrate`; shared by every app server. `CACHE_CONNECTION` and `CACHE_TABLE` pick the connection and table. |
| `file`     | Persists to `storage/framework/cache/data`.                                               |
| `array`    | In-memory, per-request only. Useful for tests.                                            |
| `redis`    | See below; requires an extra package.                                                     |

Flush a store with `php forge cache:clear` (the default store) or
`php forge cache:clear file` (a named one).

### Redis is opt-in

`symfony/cache` (which provides the Redis adapter) is always installed, but
actually selecting `CACHE_DRIVER=redis` additionally requires either the
`ext-redis` PHP extension or the `predis/predis` Composer package — neither
is a hard dependency of this framework, so installations that don't need
Redis stay lightweight. Configure the connection via `REDIS_HOST`,
`REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CACHE_DB`.
