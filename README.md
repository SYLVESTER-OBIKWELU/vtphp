## VIRTUAL TECH HYPERTEXT PREPROCESSOR FRAMEWORK
# (VtPhp) — An Independent, API-First PHP Framework

VtPhp is a PHP framework for building **APIs**: the back ends that mobile
apps, websites and other programs talk to. It handles the routine work,
such as URLs, input checking, the database, logins, security and error
messages, so you can focus on what your API actually does.

If you've used Laravel, you'll feel at home: the folder layout, the
`forge` command (like Artisan) and the Eloquent database layer work the same
way. If you haven't, the [docs](src/docs/README.md) start from the
beginning.

## What it looks like

Build a whole notes API (database table, validation, five endpoints) in a
few minutes:

```bash
php forge make:model Note -a
```

```php
// routes/api.php
$router->apiResource('notes', NoteController::class);
```

```php
// app/Http/Controllers/NoteController.php (generated for you)
public function store(StoreNoteRequest $request): NoteResource
{
    // Only runs if the input passed StoreNoteRequest's rules.
    return NoteResource::make(Note::query()->create($request->validated()))->status(201);
}

public function show(Note $note): NoteResource
{
    // VtPhp found the note for you, or already answered 404.
    return NoteResource::make($note);
}
```

## Quick start

You need **PHP 8.5+** (with the `mbstring`, `pdo` and `pdo_sqlite`
extensions) and **[Composer](https://getcomposer.org/)**.

```bash
composer install
cp .env.example .env        # Windows PowerShell: Copy-Item .env.example .env
php forge key:generate
php forge migrate
php forge db:seed           # optional: two sample users, password "password"
php forge serve
```

Open <http://127.0.0.1:8000/health> and you'll see
`{"status":"ok","version":"0.1.0"}`. The app uses an SQLite database file by
default, so there's no database server to install.

New to all this? Follow [Getting started](src/docs/getting-started.md), then
build [your first API](src/docs/your-first-api.md).

## Documentation

All guides live in [src/docs](src/docs/README.md), in learning order:

- **Start here:** [Getting started](src/docs/getting-started.md) ·
  [Your first API](src/docs/your-first-api.md) ·
  [How a request works](src/docs/request-lifecycle.md)
- **The basics:** [Routing](src/docs/routing.md) ·
  [Controllers](src/docs/controllers.md) ·
  [Validation](src/docs/validation.md) ·
  [Resources](src/docs/resources.md) · [Models](src/docs/models.md) ·
  [Migrations](src/docs/migrations.md)
- **Real apps:** [Authentication](src/docs/authentication.md) ·
  [Sessions, cookies and cache](src/docs/sessions-cookies-cache.md) ·
  [Mail](src/docs/mail.md) · [Views](src/docs/views.md) ·
  [Middleware](src/docs/middleware.md) · [CORS](src/docs/cors.md)
- **Production:** [Security](src/docs/security.md) ·
  [Errors](src/docs/exceptions.md) · [Testing](src/docs/testing.md) ·
  [Deploying](src/docs/deploying.md)
- **Reference:** [The forge CLI](src/docs/cli.md) ·
  [Container](src/docs/container.md)

## What's included

- **Routing:** route files, `apiResource()` for the five standard endpoints,
  and automatic lookup of records from URLs (with a `404` if missing).
- **Validation:** form request classes with Laravel-style rules
  (`required|email|unique:users,email`), checked before your code runs.
- **Database:** Eloquent models, Laravel-style migrations, and seeders.
  Works with SQLite, MySQL and PostgreSQL.
- **Accounts:** sign-up, login and logout with sessions, password reset, and
  email verification with signed, expiring links.
- **API tokens** for mobile apps and scripts, with per-token abilities and
  expiry.
- **Authorization:** policies and gates decide who may do what.
- **Security, on by default:** rate limiting, CSRF protection, security
  headers, safe proxy handling, protection against account guessing, and
  production safeguards on destructive commands.
- **Sessions and cache** stored in the database (or files or Redis), and
  **CORS** you can switch on from `.env`.
- **Mail** with Blade templates. In development, emails go to the log
  instead of being sent.
- **The `forge` CLI:** `make:model -a` and other generators, migrations, a
  development server and more.
- **400+ automated tests** and static analysis.

## The sample app

The project comes with a small user system you can use or replace:

| Endpoint | What it does |
| --- | --- |
| `POST /api/v1/users` | Sign up (`name`, `email`, `password`, `password_confirmation`) |
| `POST /api/v1/login` / `POST /api/v1/logout` | Log in / out (sets a session cookie) |
| `GET /api/v1/me` | The logged-in user |
| `GET /api/v1/users`, `GET /api/v1/users/{id}` | List / show users (login required) |
| `PATCH /api/v1/users/{id}`, `DELETE /api/v1/users/{id}` | Edit / delete your own account |
| `POST /api/v1/password/forgot`, `POST /api/v1/password/reset` | Reset a forgotten password |
| `POST /api/v1/email/verification-notification` | Send an email-verification link |

See [Authentication](src/docs/authentication.md) for how it all works.

## Not available yet

To save you looking: model factories, queues and background jobs, events,
file storage drivers, config/route caching, and generated API documentation
(OpenAPI) aren't built yet. The [design notes](src/docs/php_api_framework_blueprint.md)
describe the roadmap.

## Development

```bash
composer test     # run the test suite
composer stan     # static analysis (PHPStan)
composer fmt      # fix code style
```

See [Testing](src/docs/testing.md) for writing your own tests.

## License

MIT.
