# Sessions, cookies & cache

These three features let your app remember things:

- A **session** remembers one visitor between requests, such as who is logged in.
- A **cookie** is a small value the browser stores and sends back with every request.
- The **cache** remembers results that are slow to work out, so you don't repeat the work.

## The quick version

```php
// inside a controller action
session()->put('theme', 'dark');      // remember something for this visitor
session()->get('theme');              // 'dark', on this and later requests

cache()->put('stats', $stats, 600);   // keep for 600 seconds, for everyone
cache()->get('stats');                // the saved value, or null
```

Both store their data in your database by default, so run this once before
starting the app:

```bash
php forge migrate
```

It creates the `sessions` and `cache` tables.

## Sessions

### How a session works

On a visitor's first request, VtPhp creates a session and sends the browser a
cookie named `vtphp_session` holding its id. The browser sends the cookie back
on every request, and VtPhp loads the matching session. Logins are stored this
way (see [Authentication](authentication.md)).

### Reading and writing

```php
session()->put('cart_items', 3);
session()->get('cart_items');            // 3
session()->get('missing', 'default');    // 'default' when the key doesn't exist
session()->has('cart_items');            // true
session()->forget('cart_items');         // remove one key
session()->all();                        // everything, as an array
```

Changes are saved automatically at the end of the request, even if the
request ends in an error.

`session()` only works during a web request. It isn't available in
`php forge` commands.

### Where sessions are stored

Set `SESSION_DRIVER` in `.env`:

| Driver | Where | When to use |
| --- | --- | --- |
| `database` (default) | the `sessions` table | Most apps. Works when you run several servers. |
| `file` | `storage/framework/sessions/` | A single server without database sessions. |
| `array` | memory only, lost after the request | Automated tests. |

With the `database` driver, each row also records the logged-in user's id in
a `user_id` column. That makes it easy to find all of one user's sessions.

### Session settings

| `.env` setting | Default | Meaning |
| --- | --- | --- |
| `SESSION_LIFETIME` | `120` | Minutes of inactivity before a session expires. |
| `SESSION_COOKIE` | `vtphp_session` | The cookie's name. |
| `SESSION_SECURE_COOKIE` | empty | Empty: the cookie is marked `Secure` (HTTPS only) automatically on HTTPS requests. `true`/`false` forces it. |
| `SESSION_SAME_SITE` | `lax` | When browsers send the cookie to other sites. Use `none` for a frontend on another domain (needs HTTPS). |
| `SESSION_CONNECTION`, `SESSION_TABLE` | default connection, `sessions` | Which database and table the `database` driver uses. |

### Cleaning up expired sessions

Expired sessions are never used. They're also deleted automatically: on about
2 out of every 100 requests, VtPhp removes sessions that have been idle longer
than `SESSION_LIFETIME`. The odds are set by `'lottery' => [2, 100]` in
`config/session.php`.

If you'd rather clean up on a schedule, set the lottery to `[0, 100]` and run
this from a scheduled task (cron):

```bash
php forge session:gc
```

### Starting fresh

```php
session()->regenerate();   // give the session a new id, keep its data
session()->invalidate();   // clear all data and give it a new id
```

Login and logout already do this for you.

## Cookies

You rarely need cookies directly. When you do, queue one and VtPhp attaches
it to the response:

```php
// inside a controller action
cookie()->queue(cookie()->make(
    name: 'remember_theme',
    value: 'dark',
    minutes: 60 * 24 * 30,   // 30 days; 0 means "until the browser closes"
    secure: true,            // only send over HTTPS
));

cookie()->forget('remember_theme');   // tell the browser to delete it
```

Read a cookie the browser sent:

```php
use VtPhp\Http\Request;

public function show(Request $request): array
{
    return ['theme' => $request->cookie('remember_theme', 'light')];
}
```

Cookies you make are `HttpOnly` by default, which means browser JavaScript
can't read them. Only the session cookie becomes `Secure` automatically on
HTTPS, so pass `secure: true` for your own cookies on a live site.

## Cache

### Saving and reading

```php
cache()->put('exchange_rate', 1.08, 3600);   // keep for 3600 seconds (1 hour)
cache()->put('settings', $settings);         // no time limit given: kept until removed
cache()->forever('site_name', 'VtPhp');      // the same, said explicitly

cache()->get('exchange_rate');               // 1.08
cache()->get('missing', 'fallback');         // 'fallback'
cache()->has('exchange_rate');               // true
cache()->forget('exchange_rate');            // remove one item
cache()->flush();                            // remove everything
```

You can store strings, numbers, booleans and arrays.

### "Remember" a slow result

`remember()` returns the cached value if it exists. If not, it runs your
function, saves the result, and returns it. This is the cache method you'll
use most:

```php
$report = cache()->remember('daily_report', 600, function () {
    return buildDailyReport();   // slow, so only run it every 10 minutes
});
```

### Keys

A key is any string, so Laravel-style keys like `user:1` and `posts/latest`
work. Characters that the underlying cache library reserves (`{}()/\@:`) are
encoded safely before saving, so two different keys never clash.

### Where the cache is stored

Set `CACHE_DRIVER` in `.env`:

| Driver | Where | When to use |
| --- | --- | --- |
| `database` (default) | the `cache` table | Most apps. Shared by all your servers. |
| `file` | `storage/framework/cache/data` | A single server. |
| `array` | memory only, lost after the request | Automated tests. |
| `redis` | a Redis server | Busy apps. Needs extra setup, see below. |

Use a different store for one call:

```php
cache()->store('file')->get('key');
cache()->store('file')->set('key', 'value', 60);
```

`store()` returns a standard cache object (PSR-16), so its methods are `get`,
`set`, `has`, `delete` and `clear`, rather than `put` and `forget`.

### Clearing the cache

```bash
php forge cache:clear        # the default store
php forge cache:clear file   # a specific store
```

## Going further

### Redis

Redis is a fast in-memory database often used for caching. To use it, install
the `ext-redis` PHP extension or the `predis/predis` Composer package, then
set:

```dotenv
# .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CACHE_DB=1
```

### Settings files

- `config/session.php`: drivers, lifetime, cookie options, the lottery.
- `config/cache.php`: the default store, `CACHE_PREFIX`, and each store's
  options (`CACHE_CONNECTION` and `CACHE_TABLE` for the `database` store).

## Common mistakes

- **"Table not found" for `sessions` or `cache`.** Run `php forge migrate`.
- **The session forgets everything after each request.** `SESSION_DRIVER` is
  `array`. Use `database` or `file`.
- **Cached values vanish straight away.** `CACHE_DRIVER` is `array`, which
  only lasts one request. Rate limiting also stops working with `array`.
- **`session()` or `auth()` fail in a `php forge` command.** Sessions only
  exist during web requests.
- **Using `cache()->store('x')->put(...)`.** A store has `set()`, not `put()`.
  Use `cache()->put()` for the default store.

## Next steps

- [Authentication](authentication.md): logins, which are built on sessions.
- [Security](security.md): rate limiting, which uses the cache.
- [CLI](cli.md): `cache:clear`, `session:gc` and other commands.
