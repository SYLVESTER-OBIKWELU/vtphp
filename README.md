# VtPhp

An independent, API-first PHP framework with a Laravel-inspired developer experience —
built entirely on PSR standards and Symfony/Doctrine/Monolog components. **Zero
`laravel/*` or `illuminate/*` dependencies.**

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
php forge serve
```

Then visit `http://127.0.0.1:8000/health` or `http://127.0.0.1:8000/api/v1/users`.

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

php forge migrate
php forge migrate:status
php forge migrate:rollback
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
- **Database** — `doctrine/dbal` behind `VtPhp\Database\DatabaseManager`, with a small file-based migration runner.
- **Validation** — a dependency-free rule-string validator (`required|string|max:100`) that throws `ValidationException`.
- **Resources** — `JsonResource` / `ResourceCollection` for shaping API output and pagination envelopes.
- **Logging** — `monolog/monolog` behind `Psr\Log\LoggerInterface`.
- **CLI** — `symfony/console` behind the `forge` binary, with stub-based `make:*` generators.

## What's not included yet

Following the blueprint's own v0.1 → v1.0 roadmap, this build covers Phases 1–9
(foundation, HTTP, routing, controllers, middleware, exceptions, database,
validation, API resources) plus the CLI. Authentication/authorization, cache,
rate limiting, events, queues, mail, filesystem drivers, and OpenAPI generation
are the next phases — the config files and `.env` keys for most of them are
already scaffolded in `config/` to make that work additive rather than a rewrite.

## Testing

```powershell
composer test    # phpunit
composer stan     # phpstan analyse
composer fmt      # php-cs-fixer fix
```
