# VtPhp

An independent, API-first PHP framework with a Laravel-inspired developer experience —
built on PSR standards plus Symfony/Doctrine/Monolog components, with `illuminate/database`
(Eloquent ORM), `eftec/bladeone` (Blade templating), and `symfony/mailer` (Mail) as
opt-in, well-isolated dependencies for the features that benefit most from them.

```php
#[Route(method: 'GET', path: '/users/{id}', name: 'users.show')]
public function show(int $id): UserResource
{
    return UserResource::make($this->service->find($id));
}
```

## Requirements

- PHP ^8.4
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

Then visit `http://127.0.0.1:8000/health` or `http://127.0.0.1:8000/api/v1/users`.

By default `DB_CONNECTION=sqlite`, so `php forge migrate` creates
`database/database.sqlite` automatically — no external database server needed to
try the framework. `php forge db:seed` populates it with two sample users via
Eloquent. Swap in `mysql`/`pgsql` in `.env` for a real database.

## Project layout

```text
app/            Your application code (Controllers, Services, Models, Resources, ...)
bootstrap/      Application bootstrap (builds the container, config, providers)
config/         Configuration files (env-driven)
database/       Migrations
public/         Web server document root (public/index.php is the front controller)
routes/         Route definition files, loaded by App\Providers\RouteServiceProvider
src/            The framework itself, namespace VtPhp\ (Container, HTTP, Routing, ...)
stubs/          Templates used by `forge make:*` generators
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

php forge make:controller UserController
php forge make:model Product
php forge make:resource ProductResource
php forge make:middleware EnsureTokenIsValid
php forge make:migration create_products_table
php forge make:seeder ProductSeeder
php forge make:mail OrderShipped

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
- **Routing** — `symfony/routing` underneath, plus a `#[Route]` attribute for controller-based routing.
- **Middleware** — PSR-15 pipeline (`VtPhp\Middleware\Pipeline`).
- **Exceptions** — centralized `ExceptionHandler` mapping exceptions to a structured `{"success":false,"error":{...}}` JSON envelope.
- **Database** — `doctrine/dbal` behind `VtPhp\Database\DatabaseManager`, with a small file-based migration runner. `illuminate/database` (Eloquent) runs alongside it via `VtPhp\Database\EloquentManager`, reading the same `config/database.php`, for applications that want ActiveRecord-style models.
- **Validation** — a dependency-free rule-string validator (`required|string|max:100`) that throws `ValidationException`.
- **Resources** — `JsonResource` / `ResourceCollection` for shaping API output and pagination envelopes.
- **Views** — `eftec/bladeone` behind `VtPhp\View\BladeEngine` for Blade-syntax templates (e.g. email bodies), resolved from `resources/views/`.
- **Mail** — `symfony/mailer` behind `VtPhp\Mail\Mailer`, with a Laravel-style `Mailable` base class (`app/Mail/`) and a `log` transport for local dev (no SMTP server needed).
- **Logging** — `monolog/monolog` behind `Psr\Log\LoggerInterface`.
- **CLI** — `symfony/console` behind the `forge` binary, with stub-based `make:*` generators (controllers, models, resources, middleware, migrations, seeders, mailables).

## Sample API endpoints

`routes/api.php` registers a sample `User` CRUD resource plus password-recovery
and email-verification endpoints under the `/api/v1` prefix:

| Method | URI                                       | Description                                   |
|--------|--------------------------------------------|------------------------------------------------|
| GET    | `/api/v1/users`                             | List users                                     |
| POST   | `/api/v1/users`                             | Create a user (`name`, `email`, `password`, `password_confirmation`) |
| GET    | `/api/v1/users/{id}`                        | Show a user                                    |
| PATCH  | `/api/v1/users/{id}`                        | Update a user (`name`, `email` — both optional)|
| DELETE | `/api/v1/users/{id}`                        | Delete a user (`204 No Content`)               |
| POST   | `/api/v1/password/forgot`                   | Request a password reset email (`email`)       |
| POST   | `/api/v1/password/reset`                    | Reset a password (`email`, `token`, `password`, `password_confirmation`) |
| POST   | `/api/v1/email/verification-notification`  | (Re)send the email verification link (`email`)|
| GET    | `/api/v1/email/verify/{id}/{hash}`          | Verify an email address via the emailed link   |

Notes:

- User creation requires a `password` (min 8 chars) confirmed via
  `password_confirmation`, matching the `confirmed` validation rule.
- `forgot()` and the verification-notification endpoint respond with the same
  generic success message whether or not the email exists (or is already
  verified), to avoid leaking account existence.
- Reset tokens are single-use, hashed at rest (`password_reset_tokens` table),
  and expire after 60 minutes.
- Since `MAIL_MAILER=log` by default, reset/verification links are written to
  `storage/logs/app.log` instead of being emailed — copy the link/token from
  there when testing locally.

## Database, seeding & Eloquent

`app/Models/User.php` is an Eloquent model (`Illuminate\Database\Eloquent\Model`),
and `app/Repositories/EloquentUserRepository.php` is the default binding for
`UserRepositoryInterface`. Migrations still use the Doctrine DBAL-based
`php forge migrate` runner (`database/migrations/`); seeders are plain classes
extending `VtPhp\Database\Seeder` (`database/seeders/`):

```powershell
php forge make:migration create_products_table
php forge make:seeder ProductSeeder
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

Following the blueprint's own v0.1 → v1.0 roadmap, this build covers Phases 1–9
(foundation, HTTP, routing, controllers, middleware, exceptions, database,
validation, API resources) plus the CLI, Eloquent ORM, Blade templating, and
mail. Authentication/authorization, cache, rate limiting, events, queues,
filesystem drivers, and OpenAPI generation are the next phases — the config
files and `.env` keys for most of them are already scaffolded in `config/` to
make that work additive rather than a rewrite.

## Testing

```powershell
composer test    # phpunit
composer stan     # phpstan analyse
composer fmt      # php-cs-fixer fix
```
