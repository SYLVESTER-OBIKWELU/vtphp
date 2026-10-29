# Deploying to production

Deploying means putting your app on a server where real users can reach it.
This page walks you through it step by step, lists the settings that must
change for production, and is honest about what VtPhp doesn't provide yet.

## The quick version

On the server, in your project folder:

```bash
composer install --no-dev --optimize-autoloader   # install code, without development tools
cp .env.example .env                              # then edit .env (see below)
php forge key:generate --force                    # create APP_KEY, first deploy only
php forge migrate --force                         # create or update database tables
```

Then point your web server at the `public/` folder, switch on HTTPS, and
check that `https://your-domain/health` answers `{"status":"ok"}`.

The rest of this page explains each step.

## What the server needs

- **PHP 8.5** with these extensions (add-ons compiled into PHP): `json`,
  `mbstring`, `pdo`, the PDO driver for your database (`pdo_mysql`,
  `pdo_pgsql` or `pdo_sqlite`), and `openssl` for encrypted email.
- **Composer**, PHP's package installer.
- **A web server**: Nginx with PHP-FPM, or Apache. PHP-FPM is the program
  that runs PHP for the web server.
- **A database**: MySQL, PostgreSQL or SQLite. Use MySQL or PostgreSQL if
  you run more than one server.
- Optional: **Redis**, a fast in-memory store, for the cache. It needs the
  `redis` PHP extension or the `predis/predis` Composer package.

Run `php -m` on the server to list the installed extensions.

## Step 1: install the code

Copy your project to the server (for example with `git clone`), then:

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` skips testing tools you don't need in production.
`--optimize-autoloader` makes PHP find your classes faster.

## Step 2: production settings (`.env`)

Copy `.env.example` to `.env` and change it. This is a typical production
`.env`:

```dotenv
# .env (production)
APP_NAME=MyApi
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapi
DB_USERNAME=myapi
DB_PASSWORD=a-long-random-password

SESSION_DRIVER=database
CACHE_DRIVER=database
RATE_LIMIT_STORE=
SESSION_SECURE_COOKIE=
TRUSTED_PROXIES=

CORS_ENABLED=true
CORS_ALLOWED_ORIGINS=https://app.example.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="My API"

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

The settings that matter most:

- **`APP_ENV=production`** switches on production safety checks, such as
  requiring `--force` for database commands.
- **`APP_DEBUG=false`** hides error details from users. Never leave it `true`
  in production; error responses would show file paths and code.
- **`APP_KEY`** is the secret used to sign links such as email-verification
  links. Create it once:

  ```bash
  php forge key:generate --force
  ```

  Never change it afterwards: every signed link already sent would stop
  working.
- **`APP_URL`** is the API's public address. Verification links use it.
- **`FRONTEND_URL`** is your website's address. Password-reset links point
  there.

Keep `.env` out of version control. It holds passwords.

## Step 3: create the database tables

```bash
php forge migrate --force
```

In production, `migrate` needs `--force` so you can't change the database
by accident. Run it again on every deploy; it only applies new migrations.

Don't run `php forge db:seed` in production unless you've written seeders
for real data. The sample users are skipped in production anyway.

## Step 4: folder permissions

The web server must be able to write to `storage/`. VtPhp keeps its logs,
compiled templates, and file-based sessions and cache there:

```bash
chown -R www-data:www-data storage
chmod -R 775 storage
```

`www-data` is the usual web server user on Debian and Ubuntu; yours may
differ. If you use SQLite, the `database/` folder must be writable too.

## Step 5: the web server

Point the web server's document root, the folder it serves files from, at
`public/`. Never point it at the project folder itself, or `.env` could be
downloaded.

All requests must go to `public/index.php`. For Nginx:

```nginx
# /etc/nginx/sites-available/myapi
server {
    listen 80;
    server_name api.example.com;
    root /var/www/myapi/public;
    index index.php;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
```

For Apache:

```apache
# /etc/apache2/sites-available/myapi.conf
<VirtualHost *:80>
    ServerName api.example.com
    DocumentRoot /var/www/myapi/public

    <Directory /var/www/myapi/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

Don't use `php forge serve` in production. It's PHP's built-in development
server and handles one request at a time.

## Step 6: HTTPS

Serve your API over HTTPS, for example with a free certificate from Let's
Encrypt. Over HTTPS, VtPhp automatically:

- marks the session cookie `Secure` (sent only over HTTPS);
- sends `Strict-Transport-Security`, telling browsers to always use HTTPS.

If a load balancer or reverse proxy (a server that sits in front of yours
and passes requests on) handles HTTPS for you, list its address so VtPhp
believes what it reports:

```dotenv
# .env
TRUSTED_PROXIES=10.0.0.0/8
```

Without this, every visitor seems to come from the proxy's address over
plain HTTP. All visitors then share one rate limit, and cookies aren't
marked `Secure`. See [Security](security.md#behind-a-load-balancer-trusted_proxies).

## Step 7: check it works

```bash
curl https://api.example.com/health
```

```json
{"status":"ok","version":"0.1.0"}
```

```bash
php forge about     # should show Environment: production, Debug: false
```

## Going further

### Running more than one server

Everything the servers share must live outside any single server:

- **Sessions:** `SESSION_DRIVER=database`. The `file` driver keeps sessions
  on one machine, which logs users out when they reach another. Sessions
  can't be stored in Redis yet.
- **Cache:** `CACHE_DRIVER=database` or `redis`.
- **Rate limits:** `RATE_LIMIT_STORE` defaults to your cache. It must be
  shared (`database` or `redis`), or each server counts separately. Never use
  `array`, which turns rate limiting off.

### Speed: OPcache

OPcache keeps compiled PHP code in memory so it isn't re-read on every
request. Make sure it's on for PHP-FPM, in your `php.ini`:

```ini
; php.ini
opcache.enable=1
```

If you also set `opcache.validate_timestamps=0` for extra speed, PHP stops
noticing changed files, so restart PHP-FPM after every deploy.

### Logs

Logs go to `storage/logs/app.log` by default (`LOG_PATH`). The file grows
forever, so rotate it with a tool such as `logrotate`. On platforms that
collect output instead, such as containers, set `LOG_CHANNEL=stderr`. Set
`LOG_LEVEL=warning` to skip routine messages.

Every response has an `X-Request-ID` header, and server errors are logged
with it. When a user reports a problem, search the log for their request ID.

### Email

Set `MAIL_MAILER=smtp` and the `MAIL_*` values from your email provider. Use
`MAIL_ENCRYPTION=tls` (port 587) or `ssl` (port 465). `MAIL_TIMEOUT`
(10 seconds by default) stops a slow mail server from holding requests.
With `QUEUE_CONNECTION=database`, emails sent with `queue()` (including the
built-in reset and verification emails) go out from the queue worker, so
keep one running.

### The queue worker

If you set `QUEUE_CONNECTION=database`, run `php forge queue:work` all the
time with systemd or Supervisor, and restart it after every deploy so it
picks up the new code. [Queues](queues.md#in-production) has a ready-made
systemd file.

### Cleaning up old sessions

Expired sessions are deleted automatically on about 2 in 100 requests. If
you'd rather do it on a schedule, set `'lottery' => [0, 100]` in
`config/session.php` and run this from cron (a scheduler on Linux servers),
for example hourly:

```bash
php /var/www/myapi/forge session:gc
```

### Deploying an update

```bash
git pull
composer install --no-dev --optimize-autoloader
php forge migrate --force
# restart PHP-FPM if opcache.validate_timestamps=0
sudo systemctl restart myapi-worker   # if you run a queue worker
```

### What VtPhp doesn't provide yet

- **Docker and CI templates.** There's no Dockerfile or ready-made pipeline;
  you'll write your own.
- **Config and route caching.** Every request reads the config and route
  files. With OPcache on, that's fast, but there's no `config:cache` command.
- **Long-running servers** such as RoadRunner, FrankenPHP's worker mode or
  Swoole. VtPhp keeps some state between requests, so one user's login can
  leak into the next request on these servers. Use PHP-FPM or Apache's PHP
  module, which start fresh for every request.
- **A deep health check.** `/health` reports that PHP is running, not that the
  database or cache is reachable.

## Troubleshooting

- **A blank page or "500" with nothing in the log.** `storage/` isn't
  writable. Fix the permissions (Step 4).
- **Every response is 500 and the log mentions a `sessions` or `cache`
  table.** Run `php forge migrate --force`.
- **"Re-run with --force".** You're in production. Add `--force` to
  `migrate`, `migrate:rollback`, `db:seed` or `key:generate`.
- **Users are logged out on every request behind a load balancer.** Use
  `SESSION_DRIVER=database`, not `file`.
- **Everyone hits rate limits together.** Set `TRUSTED_PROXIES`.
- **Asking for a verification email returns 500, and the log says "No
  application key is set".** Set `APP_KEY` with
  `php forge key:generate --force`.

## Production checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_KEY` set once, and kept secret
- [ ] `APP_URL` and `FRONTEND_URL` point at your real addresses
- [ ] Web server document root is `public/`
- [ ] HTTPS on, and `TRUSTED_PROXIES` set if you use a load balancer
- [ ] `php forge migrate --force` run
- [ ] `storage/` writable by the web server
- [ ] Sessions, cache and rate limits on a shared store (`database` or `redis`)
- [ ] `CORS_ALLOWED_ORIGINS` lists only your frontends
- [ ] Real SMTP settings with `MAIL_ENCRYPTION`
- [ ] `QUEUE_CONNECTION=database` and a queue worker kept running (or `sync`, on purpose)
- [ ] OPcache on, logs rotated
- [ ] Tests pass before you deploy: `composer test`

## Next steps

- [Security](security.md): what each protection does.
- [CORS](cors.md): letting your frontend call the API.
- [Testing](testing.md): run the tests before every release.
