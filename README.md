# VtPhp

An independent, API-first PHP framework with a Laravel-inspired developer experience —
built on PSR standards plus Symfony/Doctrine/Monolog components, with `illuminate/database`
(Eloquent ORM), `eftec/bladeone` (Blade templating), and `symfony/mailer` (Mail) as
opt-in, well-isolated dependencies for the features that benefit most from them.

```php
// routes/api.php
$router->apiResource('users', UserController::class);

// app/Http/Controllers/UserController.php
public function store(StoreUserRequest $request): UserResource
{
    return UserResource::make(User::query()->create($request->validated()))->status(201);
}

public function show(User $user): UserResource   // route-model binding, 404 if missing
{
    return UserResource::make($user);
}
```

```powershell
php forge make:model Product -a   # model, migration, seeder, controller, requests, resource
```

## Documentation

In-depth, per-topic guides live in [src/docs/](src/docs/README.md): routing,
controllers, middleware, validation, resources, models, migrations, sessions
& cookies & cache, authentication, views, mail, exceptions, the container,
and the `forge` CLI.

## Requirements

- PHP ^8.5
- ext-json, ext-mbstring, ext-pdo (+ the PDO driver for your database)

## Getting started

```powershell
composer install
copy .env.example .env
php forge key:generate
php forge migrate
php forge db:seed
php forge serve
```

Then visit `http://127.0.0.1:8000/health`, or log in as a seeded user with
`POST /api/v1/login` (`{"email": "ada@example.com", "password": "password"}`).
`APP_KEY` must be set (`key:generate`) because it signs the email-verification links.

By default `DB_CONNECTION=sqlite`, so `php forge migrate` creates
`database/database.sqlite` automatically — no external database server needed to
try the framework. `php forge db:seed` populates it with two sample users via
Eloquent. Swap in `mysql`/`pgsql` in `.env` for a real database.

## Project layout

```text
app/            Your application code, in Laravel's layout:
  Http/           Controllers/, Requests/ (form requests), Resources/, Middleware/
  Mail/           Mailables
  Models/         Eloquent models
  Providers/      Service providers
bootstrap/      Application bootstrap (builds the container, config, providers)
config/         Configuration files (env-driven)
database/       Migrations
public/         Web server document root (public/index.php is the front controller)
routes/         Route definition files, loaded by App\Providers\RouteServiceProvider
src/            The framework itself, namespace VtPhp\ (Container, HTTP, Routing, ...)
  stubs/        Templates used by `forge make:*` (override via `forge stub:publish`)
storage/        Logs, cache, and file storage (private/public disks)
tests/          PHPUnit tests
```

`src/` is the framework core (`VtPhp\` namespace) and `app/` is your application
(`App\` namespace) — both ship in this one repository for now. Per the framework's
own "avoid overbuilding v1" principle, `src/` can be extracted into standalone
Composer packages later (see the multi-package layout described in the blueprint)
once the contracts stabilize.

## `forge` CLI

```powershell
php forge about               # application info
php forge serve               # run the PHP built-in server
php forge route:list          # list all registered routes
php forge key:generate        # generate APP_KEY

php forge make:model Product -a          # or any of -m -s -c -r -R -j
php forge make:controller ProductController --model=Product -R -j
php forge make:request StoreProductRequest
php forge make:resource Product
php forge make:middleware EnsureTokenIsValid
php forge make:migration create_products_table
php forge make:seeder ProductSeeder
php forge make:mail OrderShipped
php forge stub:publish                  # customize the generator stubs

php forge migrate
php forge migrate:status
php forge migrate:rollback
php forge db:seed
```

## Architecture

```text
Application Code (app/)
        ↓
VtPhp Framework (src/) — contracts, HTTP kernel, router, container, exceptions
        ↓
PSR Standards + Vendor Components (Symfony Routing/Console/Dotenv, Doctrine DBAL, Monolog)
```

- **Container** — `src/Container/Container.php`: PSR-11 container with reflection-based autowiring.
- **HTTP** — PSR-7 (via `nyholm/psr7`) with a convenience `VtPhp\Http\Request` wrapper and a fluent `JsonResponse`.
- **Routing** — `symfony/routing` underneath, Laravel-style route files with `apiResource()`, route-model binding, and an optional `#[Route]` attribute.
- **Middleware** — PSR-15 pipeline (`VtPhp\Middleware\Pipeline`).
- **Exceptions** — centralized `ExceptionHandler` mapping exceptions to a structured `{"success":false,"error":{...}}` JSON envelope.
- **Database** — Eloquent models (`illuminate/database`, booted by `VtPhp\Database\EloquentManager`) used directly from controllers, and Laravel-style `Schema::create()` migrations. The migration runner tracks batches through `doctrine/dbal` (`VtPhp\Database\DatabaseManager`); both read `config/database.php`.
- **Validation** — Laravel-style `FormRequest` classes validated on injection, `$request->validate()`, and a rule-string validator (`required|string|max:100`, `unique:users,email`) that throws `ValidationException`.
- **Resources** — `JsonResource` / `ResourceCollection` for shaping API output and pagination envelopes.
- **Views** — `eftec/bladeone` behind `VtPhp\View\BladeEngine` for Blade-syntax templates (e.g. email bodies), resolved from `resources/views/`.
- **Mail** — `symfony/mailer` behind `VtPhp\Mail\Mailer`, with a Laravel-style `Mailable` base class (`app/Mail/`) and a `log` transport for local dev (no SMTP server needed).
- **Logging** — `monolog/monolog` behind `Psr\Log\LoggerInterface`.
- **CLI** — `symfony/console` behind the `forge` binary, with Artisan-style `make:*` generators (`make:model -a` scaffolds the whole resource) rendered from `src/stubs/`.

## Sample API endpoints

`routes/api.php` registers a sample `User` CRUD resource plus password-recovery
and email-verification endpoints under the `/api/v1` prefix:

| Method | URI                                       | Description                                                              |
| ------ | ----------------------------------------- | ------------------------------------------------------------------------ |
| GET    | `/api/v1/users`                           | List users                                                               |
| POST   | `/api/v1/users`                           | Create a user (`name`, `email`, `password`, `password_confirmation`)     |
| GET    | `/api/v1/users/{user}`                    | Show a user                                                              |
| PUT/PATCH | `/api/v1/users/{user}`                 | Update a user (`name`, `email` — both optional)                          |
| DELETE | `/api/v1/users/{user}`                    | Delete a user (`204 No Content`)                                         |
| POST   | `/api/v1/password/forgot`                 | Request a password reset email (`email`)                                 |
| POST   | `/api/v1/password/reset`                  | Reset a password (`email`, `token`, `password`, `password_confirmation`) |
| POST   | `/api/v1/email/verification-notification` | (Re)send the email verification link (`email`)                           |
| GET    | `/api/v1/email/verify/{user}/{hash}`      | Verify an email address via the emailed link                             |

Notes:

- Sign-up (`POST /users`) is public. The other `users` routes require a
  logged-in session, and users may only update or delete their own account
  (`403` otherwise). See [authentication](src/docs/authentication.md#protecting-routes).
- User creation requires a `password` (min 8 chars) confirmed via
  `password_confirmation`. A taken email is a `422` (`unique` rule).
- `forgot()` and the verification-notification endpoint respond with the same
  generic success message whether or not the email exists (or is already
  verified), to avoid leaking account existence.
- Reset tokens are single-use, hashed at rest (`password_reset_tokens` table),
  and expire after 60 minutes.
- Since `MAIL_MAILER=log` by default, reset/verification links are written to
  `storage/logs/app.log` instead of being emailed — copy the link/token from
  there when testing locally.

## Sessions, cookies & cache

The framework ships Laravel-style session-based auth, cookie handling, and a
pluggable cache layer (`symfony/cache` under the hood).

| Method | URI              | Description                                              |
| ------ | ---------------- | -------------------------------------------------------- |
| POST   | `/api/v1/login`  | Log in (`email`, `password`) — sets a session cookie     |
| POST   | `/api/v1/logout` | Log out — invalidates the session                        |
| GET    | `/api/v1/me`     | Current authenticated user (requires the session cookie) |

- **Sessions** — `VtPhp\Session\SessionManager` starts a `Session` per request
  (bound into the container by the `StartSession` middleware), backed by a
  `SessionStoreInterface` driver: `file` (default, `storage/framework/sessions/`,
  serialized with `allowed_classes: false` to avoid PHP object-injection) or
  `array` (non-persistent, tests/CLI only). Configure via `config/session.php`
  / `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_COOKIE`, `SESSION_SECURE_COOKIE`,
  `SESSION_SAME_SITE` env vars.
- **Cookies** — outgoing cookies are queued via the `cookie()` helper
  (`VtPhp\Cookie\CookieJar`) and attached to the response as `Set-Cookie`
  headers by the `AddQueuedCookiesToResponse` middleware. Incoming cookies can
  be read with `$request->cookie('name')` or `$psrRequest->getCookieParams()`.
- **Auth** — `config/auth.php` has a default `web` guard (`driver: session`)
  backed by an `eloquent` user provider pointed at `App\Models\User`. Use
  `auth()->attempt([...])`, `->user()`, `->check()`, `->logout()`, or
  `$request->user()`. Protect routes with the `Authenticate` middleware.
- **Cache** — the `cache()` helper (`VtPhp\Cache\CacheManager`) exposes
  `get`/`put`/`has`/`forget`/`remember`/`flush`, resolving a PSR-16 store per
  `config/cache.php`. Supported drivers: `array` (in-memory), `file`
  (`storage/framework/cache/data`), and `redis`.
- **Redis is an optional adapter, not a hard dependency** — `symfony/cache` is
  always installed, but actually selecting `CACHE_DRIVER=redis` requires the
  app to additionally install `ext-redis` or `predis/predis`. Configure the
  connection via `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CACHE_DB`.

## Database, seeding & Eloquent

Models are Eloquent models (`Illuminate\Database\Eloquent\Model`) used directly
from controllers, as in Laravel. Migrations use Laravel's schema builder through
`VtPhp\Database\Schema`; seeders are plain classes extending
`VtPhp\Database\Seeder` (`database/seeders/`):

```php
Schema::create('products', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

```powershell
php forge make:model Product -ms     # model + migration + seeder
php forge migrate
php forge db:seed
```

## Blade views & Mail

Render a Blade view (from `resources/views/`) with the `view()` helper:

```php
echo view('emails.welcome', ['user' => $user]);
```

Define a Mailable and send it through the `Mailer`:

```php
// app/Mail/WelcomeEmail.php
final class WelcomeEmail extends Mailable
{
    public function __construct(private readonly User $user) {}

    public function build(): void
    {
        $this->to($this->user->email)
            ->subject('Welcome to VtPhp!')
            ->view('emails.welcome', ['user' => $this->user]);
    }
}

app(\VtPhp\Mail\Mailer::class)->send(new WelcomeEmail($user));
```

`MAIL_MAILER` defaults to `log` (writes the rendered email to the app log instead
of sending it) so mail works out of the box with no SMTP server configured. Set
it to `smtp` and configure `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`
in `.env` for real delivery.

## What's not included yet

Following the blueprint's v0.1 → v1.0 roadmap, this build covers the
foundation, HTTP, routing, controllers, middleware, exceptions, database,
validation and API resources, plus the CLI, Eloquent ORM, Blade templating,
mail, session authentication and cache. Still to come: token (bearer)
authentication, authorization (gates/policies), model factories, rate
limiting, events, queues, filesystem drivers and OpenAPI generation. The
config files and `.env` keys for several of them are already scaffolded in
`config/`, so that work can be added rather than rewritten.

## Testing

```powershell
composer test    # phpunit
composer stan     # phpstan analyse
composer fmt      # php-cs-fixer fix

php vendor/bin/phpunit --exclude-group defect   # everything that works
php vendor/bin/phpunit --group defect           # known bugs, captured as failing tests
```

`tests/Unit` covers each subsystem in isolation. `tests/Feature` boots the real
app against a temporary SQLite database and sends requests through the HTTP
kernel. Tests in the `defect` group document known bugs and fail until those
bugs are fixed.
