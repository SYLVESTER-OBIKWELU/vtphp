# Building an Independent PHP API Framework

## Framework Blueprint, Architecture, Package Strategy, and Development Roadmap

> **Goal:** Build your own API-first PHP framework inspired by Laravel's developer experience and architecture, while remaining independent of Laravel and the `illuminate/*` packages.

The framework should be designed specifically for PHP backend/API projects. Mature vendor packages should provide difficult infrastructure, while **your own framework owns the application lifecycle, contracts, conventions, API behavior, developer experience, and framework integrations**.

---

# 1. What the Framework Should Be

The final framework can look conceptually like this:

```text
MyFramework
│
├── Application
├── HTTP
├── Routing
├── Middleware
├── Dependency Injection
├── Configuration
├── Database
├── ORM / Repositories
├── Validation
├── Authentication
├── Authorization
├── API Resources
├── Pagination
├── Caching
├── Events
├── Queues
├── Mail
├── Files
├── Logging
├── Exceptions
├── CLI
├── Scheduling
├── Testing
├── OpenAPI
└── Security
```

A developer using the framework should eventually be able to write code such as:

```php
return UserResource::make($user);
```

or:

```php
#[Route('GET', '/users/{id}', name: 'users.show')]
public function show(UserService $service, int $id): UserResource
{
    return UserResource::make(
        $service->find($id)
    );
}
```

while the application has **no Laravel dependency**.

---

# 2. Core Architectural Principle

Use three major layers:

```text
┌─────────────────────────────────────────┐
│             Application Code            │
│                                         │
│ Controllers / Services / Models / DTOs  │
│ Resources / Jobs / Commands / Policies  │
└───────────────────┬─────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│             YOUR FRAMEWORK              │
│                                         │
│ Kernel / Router Adapter / DI / Auth     │
│ Config / API / Exceptions / CLI         │
│ Middleware / Resources / Database API   │
└───────────────────┬─────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│            VENDOR COMPONENTS            │
│                                         │
│ Symfony / Doctrine / Monolog / PSR      │
│ Flysystem / Guzzle / PHPUnit / etc.     │
└─────────────────────────────────────────┘
```

The application should depend primarily on **your framework contracts and PSR interfaces**, not on concrete vendor implementations.

For example, prefer:

```php
use Psr\Log\LoggerInterface;
```

instead of making application code depend directly on:

```php
use Monolog\Logger;
```

Monolog can then be the implementation underneath.

---

# 3. PHP Version Strategy

A sensible starting target is:

```text
PHP 8.4+
```

PHP 8.5 is the current stable release line (GA November 2025), so a realistic CI matrix today is:

```text
PHP 8.4  (minimum supported)
PHP 8.5  (current stable)
```

with PHP 8.6 added to the matrix as soon as it enters alpha/beta, so regressions are caught before it becomes the new stable release. PHP follows an annual release cycle each November, with roughly 2 years of active support and a further year of security-only support per version — build your own support-window policy (e.g. "we support the two newest stable minor versions") and publish it, rather than leaving it implicit.

Keep your framework's supported PHP range explicit in `composer.json` (`"php": "^8.4"`) and in your documentation's compatibility table, and update both the same day you add support for a new minor version.

Avoid supporting old PHP versions simply for compatibility if doing so makes the framework architecture harder to maintain.

## 3.1 Designing for Future PHP Versions

Because this framework must stay independent and easy to move forward, bake these practices in from day one rather than retrofitting them later:

```text
- Run `composer.json`'s "php" constraint as an open upper bound (^8.4, not 8.4.*), so patch and
  minor releases are picked up automatically by users.
- Add a "test-with-lowest" and "test-with-highest" CI job (Composer's --prefer-lowest /
  --prefer-stable) so the framework is verified against both edges of its supported range.
- Never use undocumented/internal PHP or vendor APIs, and avoid relying on deprecated features
  (dynamic properties, implicit nullable typehints, curly-brace array/string offset access,
  create_function-era patterns) even where PHP still allows them — these are exactly the
  features later versions remove.
- Isolate anything version-sensitive (new syntax, new SPL/Reflection APIs, new attributes)
  behind your own framework interfaces/adapters, the same way vendor packages are isolated in
  Section 2. If PHP 8.6+ changes behavior underneath, only that adapter needs to change.
- Track the PHP RFC pipeline (https://wiki.php.net/rfc) for accepted RFCs targeting the next
  version, and budget a small compatibility pass before each November release.
- Run PHPStan/Psalm at the highest level your codebase tolerates, and enable their PHP
  version-compatibility rulesets so both "uses a too-new feature" and "uses a removed/deprecated
  feature" are caught in CI, not in production.
- Keep the framework's own code free of Composer packages that are abandoned or unmaintained;
  an abandoned dependency is the most common reason a framework can't move to a new PHP version.
```

---

# 4. Do Not Depend on Laravel

Your framework should not require:

```text
laravel/*
illuminate/*
```

Your `composer.json` can instead use independent packages such as:

```text
psr/*
symfony/*
doctrine/*
monolog/*
league/*
guzzle/*
```

Conceptually:

```text
YOUR FRAMEWORK
      ↓
PSR Contracts
      ↓
Symfony / Doctrine / Monolog / Flysystem / Guzzle
```

Not:

```text
YOUR FRAMEWORK
      ↓
Laravel
      ↓
Illuminate
```

You can implement Laravel-like concepts such as:

```text
ServiceProvider
Request
Response
Controller
Middleware
Model
Migration
Job
Resource
Gate
Policy
Config
Cache
```

without using Laravel's implementations.

---

# 5. Recommended Vendor Stack

| Area | Your Framework Owns | Vendor / Infrastructure |
|---|---|---|
| Autoloading | Namespace conventions | Composer |
| Dependency Injection | Service registration and framework integration | Symfony DependencyInjection |
| HTTP | Request lifecycle and API behavior | PSR-7/15 + implementation |
| Routing | Framework route API | Symfony Routing |
| Middleware | Pipeline API | PSR-15 |
| Configuration | Config repository/API | Symfony Config + Dotenv |
| Validation | Framework validation API | Symfony Validator |
| Serialization | Resource/DTO integration | Symfony Serializer |
| Database | Database manager/repositories | Doctrine DBAL |
| ORM | Optional model abstraction | Doctrine ORM |
| Migrations | Migration commands and conventions | Doctrine Migrations |
| Logging | Logger abstraction | Monolog |
| Cache | Cache manager API | Symfony Cache |
| Events | Framework event API | Symfony EventDispatcher / PSR-14 |
| Queue | Jobs/queue abstraction | Symfony Messenger |
| Mail | Mail abstraction | Symfony Mailer |
| HTTP Client | HTTP client abstraction | Guzzle or Symfony HttpClient |
| Files | Storage abstraction (private/public/cloud disks — Section 43) | Flysystem (local, S3/R2/MinIO) + a dedicated driver for Cloudinary |
| CLI | Framework commands and code generators | Symfony Console |
| IDs | Framework ID utilities | Symfony UID |
| Locks | Lock abstraction | Symfony Lock |
| Testing | Framework testing utilities | PHPUnit |
| Static analysis | Project rules/config | PHPStan |
| Formatting | Coding rules | PHP-CS-Fixer |
| OpenAPI | API documentation integration | Your framework + OpenAPI tooling |

### Recommended Symfony baseline

For a new framework, a sensible stable baseline is the Symfony **7.4 LTS** component generation rather than coupling your API framework to the full Symfony framework.

Keep Symfony as a source of standalone components rather than adopting Symfony's entire application structure.

---

# 6. Think in Packages

Do not build one giant repository with every framework feature inside a single package.

A useful structure is:

```text
your-framework/
│
├── packages/
│   ├── contracts/
│   ├── core/
│   ├── http/
│   ├── routing/
│   ├── middleware/
│   ├── config/
│   ├── dependency-injection/
│   ├── database/
│   ├── validation/
│   ├── serialization/
│   ├── authentication/
│   ├── authorization/
│   ├── cache/
│   ├── events/
│   ├── queue/
│   ├── mail/
│   ├── filesystem/
│   ├── console/
│   ├── testing/
│   └── openapi/
│
├── skeleton/
│   └── api-project/
│
├── tests/
├── docs/            # see Section 6a — one Markdown file per topic
└── composer.json
```

Possible published packages:

```text
yourvendor/contracts
yourvendor/core
yourvendor/http
yourvendor/routing
yourvendor/database
yourvendor/authentication
yourvendor/framework
```

The meta-package:

```text
yourvendor/framework
```

can depend on the standard API components.

---

# 6a. Documentation Package (`docs/`)

Treat documentation as a first-class part of the framework, not an afterthought written once at the end. Give it its own top-level `docs/` folder in the monorepo, written as plain Markdown so it renders on GitHub/GitLab, can be published to a docs site later (VitePress, Docusaurus, or even a static-site build served by your own framework), and stays easy for contributors to edit in the same PR as the code change it documents.

Suggested structure:

```text
docs/
├── README.md                    # index / table of contents
├── getting-started/
│   ├── installation.md          # composer create-project, requirements, first run
│   ├── directory-structure.md   # what each app/ folder is for
│   ├── configuration.md         # config/, .env, config caching
│   └── deployment.md            # production checklist, opcache, config:cache
│
├── core-concepts/
│   ├── application-lifecycle.md # kernel/bootstrap walkthrough (Section 8)
│   ├── dependency-injection.md
│   ├── service-providers.md
│   ├── routing.md
│   ├── middleware.md
│   ├── controllers.md
│   ├── requests-and-responses.md
│   └── exceptions.md
│
├── database/
│   ├── getting-started.md
│   ├── repositories.md
│   ├── migrations.md
│   ├── transactions.md
│   └── seeding.md
│
├── api/
│   ├── resources.md             # API Resources / ResourceCollections
│   ├── validation.md
│   ├── pagination.md
│   ├── filtering-and-sorting.md
│   ├── versioning.md
│   ├── rate-limiting.md
│   └── openapi.md
│
├── security/
│   ├── authentication.md
│   ├── authorization.md
│   ├── tokens-and-scopes.md
│   └── security-checklist.md
│
├── services/
│   ├── caching.md
│   ├── queues-and-jobs.md
│   ├── events.md
│   ├── mail.md
│   ├── filesystem.md            # local/public/private/cloud disks — Section 43
│   ├── http-client.md
│   └── scheduling.md
│
├── cli/
│   ├── forge-overview.md        # Artisan-style CLI — Section 47
│   ├── generators.md            # make:* commands and stub customization
│   └── custom-commands.md       # writing your own forge command
│
├── testing/
│   ├── unit-testing.md
│   ├── feature-testing.md
│   └── static-analysis.md
│
├── upgrading/
│   ├── upgrade-guide.md         # per-version upgrade notes
│   └── php-version-support.md   # Section 3 policy, published for users
│
├── internals/
│   ├── architecture.md          # Sections 1–2, the three-layer diagram
│   ├── package-structure.md     # Section 6
│   └── contributing.md          # coding standards, RFC/ADR process, PR flow
│
└── faq.md
```

Guidelines for the docs package:

```text
- One concept per file; keep each file short enough to be read in a few minutes.
- Every public framework contract or class mentioned in a doc should link back to its
  location under packages/*/src, so docs and code don't drift apart silently.
- Every code example in the docs should be runnable against the current skeleton app —
  treat doc examples as untested liabilities and, where practical, extract them into the
  test suite (a "doc examples compile/run" CI check) so they can't silently rot.
- Ship a docs/README.md index and, ideally, generate a searchable static site from the
  same Markdown at release time rather than maintaining two copies of the content.
- Version the docs alongside the code: a docs/ folder per major version, or a clearly
  dated "applies to vX" banner at the top of each file.
```

---

# 7. The Contracts Package

Create a package for your public framework contracts.

For example:

```text
src/
├── ApplicationInterface.php
├── KernelInterface.php
├── MiddlewareInterface.php
├── ControllerResolverInterface.php
├── ExceptionHandlerInterface.php
├── ResponseFactoryInterface.php
├── ConfigInterface.php
├── DatabaseManagerInterface.php
├── CacheManagerInterface.php
├── AuthenticatorInterface.php
├── AuthorizationInterface.php
├── ResourceInterface.php
├── PaginatorInterface.php
├── EventDispatcherInterface.php
└── ClockInterface.php
```

Some contracts should extend PSR contracts where appropriate.

Example:

```php
namespace YourVendor\Contracts;

use Psr\Container\ContainerInterface;

interface ApplicationInterface extends ContainerInterface
{
    public function boot(): void;

    public function handle(
        \Psr\Http\Message\ServerRequestInterface $request
    ): \Psr\Http\Message\ResponseInterface;
}
```

This makes the public architecture explicit and replaceable.

---

# 8. The Application Kernel

The kernel is the heart of the framework.

Conceptually:

```text
HTTP Request
      │
      ▼
Front Controller
      │
      ▼
Application
      │
      ▼
Kernel
      │
      ├── Bootstrap
      ├── Load configuration
      ├── Build container
      ├── Register services
      ├── Register routes
      ├── Build middleware
      │
      ▼
Middleware Pipeline
      │
      ▼
Router
      │
      ▼
Controller Resolver
      │
      ▼
Controller
      │
      ▼
Application Service
      │
      ▼
Repository / Database
      │
      ▼
Resource / Response
      │
      ▼
Middleware Response Pipeline
      │
      ▼
HTTP Response
```

This lifecycle is what makes the project a **framework**, not merely a collection of libraries.

---

# 9. Public Entry Point

Your `public/index.php` should be very small.

Example:

```php
<?php

declare(strict_types=1);

use App\Bootstrap;
use YourVendor\Http\ResponseEmitter;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::create();

$request = $app->requestFactory()->fromGlobals();

$response = $app->handle($request);

(new ResponseEmitter())->emit($response);
```

The application should not manually initialize routing, logging, database connections, authentication, etc. inside the front controller.

---

# 10. Bootstrap

A simple application could use:

```text
bootstrap/
├── app.php
├── container.php
├── config.php
├── routes.php
└── providers.php
```

Or a single:

```text
bootstrap/
└── app.php
```

with something like:

```php
$app = Application::create(
    basePath: dirname(__DIR__)
);

$app->withConfig(...)
    ->withProviders(...)
    ->withMiddleware(...)
    ->withRoutes(...);

return $app;
```

This provides a Laravel-like developer experience without using Laravel's code.

---

# 11. Dependency Injection

Dependency injection should be one of the first things implemented.

Controllers should be able to use:

```php
final class UserController
{
    public function __construct(
        private UserService $users,
        private LoggerInterface $logger,
    ) {}
}
```

Use Symfony DependencyInjection underneath.

Your framework can expose:

```php
$app->bind(
    UserRepositoryInterface::class,
    UserRepository::class
);
```

and:

```php
$app->singleton(
    CacheManager::class,
    fn () => new CacheManager(...)
);
```

Eventually:

```php
$app->factory(...);
$app->instance(...);
$app->alias(...);
```

---

# 12. Service Providers

A Service Provider system is worth adopting conceptually.

Example:

```php
abstract class ServiceProvider
{
    public function register(Application $app): void
    {
    }

    public function boot(Application $app): void
    {
    }
}
```

Then:

```php
final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(Application $app): void
    {
        $app->singleton(
            DatabaseManager::class,
            fn ($app) => DatabaseManager::create(
                $app->config('database')
            )
        );
    }
}
```

Your application can register:

```php
return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    AuthServiceProvider::class,
    CacheServiceProvider::class,
];
```

---

# 13. Routing

For the first major version, use Symfony Routing underneath.

Expose something like:

```php
$router->get(
    '/users/{id}',
    [UserController::class, 'show']
);
```

The framework should translate that to the underlying routing implementation.

Symfony Routing supports route collections, dynamic parameters, HTTP methods, URL generation, and attribute-based routes.

---

# 14. Attribute Routing

You can create your own route attribute:

```php
namespace YourVendor\Routing;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Route
{
    public function __construct(
        public string|array $method,
        public string $path,
        public ?string $name = null,
    ) {}
}
```

Application example:

```php
#[Route(
    method: 'GET',
    path: '/api/v1/users/{id}',
    name: 'users.show'
)]
public function show(int $id)
{
}
```

Your architecture becomes:

```text
YOUR Route Attribute
        ↓
Your Router
        ↓
Symfony Routing
```

The application does not need to know how Symfony represents routes internally.

---

# 15. Middleware

Use PSR-15 as the middleware boundary.

Typical pipeline:

```text
Request
   ↓
CORS
   ↓
Request ID
   ↓
Rate Limit
   ↓
Authentication
   ↓
Authorization
   ↓
Validation
   ↓
Controller
   ↓
Response
```

Example:

```php
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestId = $request->getHeaderLine('X-Request-ID')
            ?: bin2hex(random_bytes(16));

        $request = $request->withAttribute(
            'request_id',
            $requestId
        );

        $response = $handler->handle($request);

        return $response->withHeader(
            'X-Request-ID',
            $requestId
        );
    }
}
```

Framework usage:

```php
$middleware->global([
    RequestIdMiddleware::class,
    CorsMiddleware::class,
    RateLimitMiddleware::class,
]);

$middleware->group('auth', [
    Authenticate::class,
]);
```

---

# 16. Request and Response

Use a **PSR-7-first architecture**.

The application can work with:

```php
ServerRequestInterface
ResponseInterface
StreamInterface
UriInterface
```

and PSR-17 factories for creating HTTP objects.

Your framework can add Laravel-like conveniences:

```php
$request->input('email');
$request->query('page');
$request->header('Authorization');
$request->json();
$request->bearerToken();
```

The application uses your convenience layer, but your low-level HTTP architecture stays standards-based.

---

# 17. JSON API Responses

A useful developer API is:

```php
return response()->json([
    'message' => 'User created',
    'data' => $user,
], 201);
```

Also support:

```php
return UserResource::make($user);
```

and a fluent response API:

```php
return response()
    ->json($data)
    ->status(201)
    ->header('X-Request-ID', $requestId);
```

Support:

```text
JSON
XML (optional)
downloads
streams
empty responses
redirects
pagination
errors
```

JSON should be the primary representation.

---

# 18. API Resources

Build resources as a first-class framework feature.

Instead of:

```php
return $user;
```

prefer:

```php
return UserResource::make($user);
```

Example:

```php
final class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'created_at' => $this->resource->createdAt->toIso8601String(),
        ];
    }
}
```

This helps prevent exposing:

```text
password
remember_token
internal flags
admin notes
security fields
database metadata
```

Symfony Serializer can be used underneath for object/array transformation.

---

# 19. Validation

Do not invent a validation engine unless there is a clear reason.

Use Symfony Validator underneath and expose a framework-friendly API.

Example:

```php
$request->validate([
    'name' => ['required', 'string', 'max:100'],
    'email' => ['required', 'email'],
]);
```

A more structured approach is request DTOs:

```php
final class CreateUserRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}
```

You can also support validation attributes.

---

# 20. Controllers Should Be Thin

Avoid large controllers containing business logic.

Instead of:

```php
public function store(Request $request)
{
    // validate
    // hash password
    // query database
    // send email
    // create transaction
    // dispatch job
    // return response
}
```

use:

```text
Controller
    ↓
Request DTO
    ↓
Service / Use Case
    ↓
Repository
    ↓
Domain
    ↓
Response Resource
```

Example:

```php
final class UserController
{
    public function store(
        CreateUserRequest $request,
        CreateUserService $service
    ): Response {
        $user = $service->execute($request);

        return UserResource::make($user)
            ->status(201);
    }
}
```

---

# 21. Database Strategy

Two main options:

## Option A — DBAL + Repositories

Use:

```text
doctrine/dbal
```

and build:

```text
DatabaseManager
Query layer
Repositories
Transactions
Pagination
Migrations
```

## Option B — Full ORM

Use:

```text
doctrine/orm
```

and build your framework-level model experience above it.

### Recommended Approach

For v1:

```text
Doctrine DBAL
+
Repositories
+
Your DatabaseManager
```

Then add a richer ORM/model layer later.

Avoid writing an Eloquent replacement before your framework foundation is stable.

---

# 22. Repository Pattern

Application-level repository contracts should look like:

```php
interface UserRepositoryInterface
{
    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function delete(User $user): void;
}
```

Then services depend on:

```php
UserRepositoryInterface
```

instead of concrete database classes.

This lets the implementation change later.

---

# 23. Transactions

Make transactions first-class:

```php
$db->transaction(function () {
    // database work
});
```

or:

```php
$db->beginTransaction();

try {
    // ...
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();

    throw $e;
}
```

This is especially important for:

```text
payments
wallets
orders
properties
inventory
financial operations
```

---

# 24. Migrations

Expose your own CLI commands:

```powershell
php forge make:migration create_users_table
php forge migrate
php forge migrate:rollback
php forge migrate:status
```

Migration example:

```php
final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        // schema
    }

    public function down(): void
    {
        // rollback
    }
}
```

Doctrine Migrations can provide the underlying infrastructure.

---

# 25. Authentication

Treat authentication as a pluggable subsystem.

Support strategies such as:

```text
API tokens
JWT
session authentication (optional)
OAuth2 integrations
custom authenticators
```

Define:

```php
interface AuthenticatorInterface
{
    public function authenticate(
        ServerRequestInterface $request
    ): ?AuthenticatedUser;
}
```

The framework should not care whether the token is:

```text
opaque database token
JWT
OAuth token
external identity token
```

---

# 26. API Token Design

A token system should generally follow:

```text
random token
     ↓
hash token
     ↓
store hash
     ↓
identify user
     ↓
check expiry
     ↓
check revocation
     ↓
check scopes
```

Store fields such as:

```text
token_id
token_hash
user_id
name
scopes
expires_at
last_used_at
revoked_at
created_at
```

This supports revocation and auditing.

JWT should be an optional authentication package rather than the only built-in authentication strategy.

---

# 27. Authorization

Keep authorization separate from authentication.

```text
Authentication
=
Who are you?

Authorization
=
Are you allowed to do this?
```

Example:

```php
$authorization->allows(
    $user,
    'update',
    $property
);
```

Policy example:

```php
final class PropertyPolicy
{
    public function update(
        User $user,
        Property $property
    ): bool {
        return $property->userId === $user->id;
    }
}
```

---

# 28. Roles and Permissions

Do not hard-code application roles into the framework.

Do not permanently embed:

```text
admin
agent
customer
manager
```

Instead provide a system that supports:

```text
User
 ↓
Roles
 ↓
Permissions
 ↓
Policies
```

Example permissions:

```text
property.create
property.update
property.delete
property.approve
property.publish
```

The application defines actual business roles.

---

# 29. Exception Handling

Provide a centralized API exception handler.

Map exceptions to responses such as:

```text
ValidationException        → 422
AuthenticationException    → 401
AuthorizationException     → 403
NotFoundException          → 404
ConflictException          → 409
TooManyRequestsException  → 429
Throwable                  → 500
```

Use a predictable JSON response format:

```json
{
    "success": false,
    "error": {
        "code": "USER_NOT_FOUND",
        "message": "User not found."
    }
}
```

---

# 30. Error Codes

Do not make clients depend only on human-readable messages.

Instead of:

```json
{
    "message": "Email already exists"
}
```

use:

```json
{
    "error": {
        "code": "EMAIL_ALREADY_EXISTS",
        "message": "The email address is already registered."
    }
}
```

Example application codes:

```text
EMAIL_ALREADY_EXISTS
INVALID_CREDENTIALS
TOKEN_EXPIRED
PROPERTY_NOT_FOUND
PAYMENT_FAILED
INSUFFICIENT_BALANCE
```

---

# 31. Pagination

Make pagination a first-class framework feature.

Example:

```php
$users = $repository->paginate(
    page: 1,
    perPage: 20
);
```

API result:

```json
{
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 120,
        "last_page": 6
    },
    "links": {
        "first": "...",
        "last": "...",
        "next": "..."
    }
}
```

Also support cursor pagination later:

```text
?page=2
```

and:

```text
?cursor=abc123
```

---

# 32. Filtering, Sorting, and Searching

Your framework should provide a safe query/filter abstraction.

Examples:

```http
GET /properties?status=published
```

```http
GET /properties?price_min=100000&price_max=500000
```

```http
GET /properties?sort=-created_at
```

Potential query definitions:

```php
Filter::string('status');
Filter::number('price');
Filter::boolean('verified');
Filter::date('created_at');
```

Never turn arbitrary request parameters directly into SQL.

---

# 33. API Versioning

Build API versioning into the router.

Example:

```text
/api/v1/users
/api/v2/users
```

Possible API:

```php
$api->version('v1', function ($router) {
    require routes_v1.php;
});
```

URL-based versioning is a simple first implementation.

---

# 34. Rate Limiting

Support:

```text
global rate limits
per-user limits
per-IP limits
per-route limits
authentication limits
```

Examples:

```text
login → 5/minute
public API → 60/minute
authenticated API → 300/minute
admin → 1000/minute
```

Use the cache abstraction underneath so the backing store can be:

```text
Redis
APCu
Memcached
database
```

---

# 35. Cache

Expose a clean caching abstraction:

```php
cache()->get('users.123');
```

```php
cache()->put(
    'users.123',
    $user,
    ttl: 3600
);
```

Where possible, use PSR cache interfaces for application-facing dependencies.

Potential backends:

```text
Redis
Filesystem
APCu
Database
```

Symfony Cache is suitable as infrastructure underneath.

---

# 36. Redis

Make Redis an adapter, not a framework requirement.

Architecture:

```text
Cache
 ├── Redis
 ├── APCu
 ├── Filesystem
 └── Database
```

Queue systems can similarly support:

```text
Redis
Database
RabbitMQ
Amazon SQS
```

---

# 37. Jobs and Queues

Create your own job abstraction.

Example:

```php
final class SendWelcomeEmail implements Job
{
    public function __construct(
        public readonly int $userId
    ) {}

    public function handle(
        Mailer $mailer,
        UserRepositoryInterface $users
    ): void {
        // ...
    }
}
```

Developer experience:

```php
dispatch(new SendWelcomeEmail($user->id));
```

Use Symfony Messenger underneath.

Eventually support:

```text
sync
async
retry
backoff
failed jobs
delayed jobs
queue names
priorities
```

---

# 38. Events

Provide framework-level events:

```php
event(new UserRegistered($user));
```

Example event:

```php
final class UserRegistered
{
    public function __construct(
        public readonly User $user
    ) {}
}
```

Listener:

```php
final class SendWelcomeEmail
{
    public function __invoke(UserRegistered $event): void
    {
        // ...
    }
}
```

Possible flow:

```text
UserRegistered
      │
      ├── SendWelcomeEmail
      ├── CreateWallet
      ├── NotifyAdmin
      └── CreateAuditLog
```

---

# 39. Logging

Application code should depend on:

```php
LoggerInterface
```

rather than a concrete logger.

Example:

```php
$logger->info('User registered', [
    'user_id' => $user->id,
]);
```

Eventually produce structured logs such as:

```json
{
    "level": "info",
    "message": "User registered",
    "request_id": "a81...",
    "user_id": 12,
    "timestamp": "..."
}
```

Use Monolog underneath.

---

# 40. Request IDs

Every HTTP request should have:

```text
X-Request-ID
```

Flow:

```text
HTTP request
     ↓
request ID
     ↓
application logs
     ↓
database audit
     ↓
queue job
     ↓
external API calls
```

This is extremely useful for production debugging.

---

# 41. HTTP Client

Expose an application-friendly HTTP client while keeping a vendor-independent abstraction.

Example:

```php
$client->get(...);
$client->post(...);
$client->put(...);
$client->delete(...);
```

You can back it with Guzzle or Symfony HttpClient.

The application should not depend directly on one concrete HTTP library.

---

# 42. Mail

Do not implement SMTP yourself.

Use Symfony Mailer underneath.

Possible developer API:

```php
Mail::to($user->email)
    ->send(new WelcomeMail($user));
```

or:

```php
$mailer->send($message);
```

The application should not care which SMTP provider is configured.

---

# 43. Filesystem

Use Flysystem (`league/flysystem` v3) as the underlying implementation for real filesystem-shaped storage (local disk, S3-compatible object storage), sitting behind your own `FilesystemManagerInterface` / `StorageInterface` contracts so application code never talks to Flysystem directly — the same adapter pattern used for the database and cache layers in Section 2.

## 43.1 Framework API

```php
// Default disk (from config: filesystems.default)
storage()->put('avatars/user-1.jpg', $contents);

// A specific named disk
storage()->disk('public')->put('avatars/user-1.jpg', $contents);
storage()->disk('private')->put('contracts/2026-invoice.pdf', $contents);
storage()->disk('s3')->put('exports/report.csv', $contents);
storage()->disk('cloudinary')->put('users/1/avatar.jpg', $contents);

storage()->disk('public')->url('avatars/user-1.jpg');       // public, permanent URL
storage()->disk('private')->temporaryUrl('contracts/x.pdf', now()->addMinutes(5));
storage()->disk('s3')->temporaryUrl('exports/report.csv', now()->addMinutes(10));

storage()->disk('public')->exists($path);
storage()->disk('public')->delete($path);
storage()->disk('public')->size($path);
storage()->disk('public')->mimeType($path);
```

## 43.2 Disk Types

Disks fall into three categories, each with different guarantees — the framework should make the distinction explicit rather than treating every disk as interchangeable:

```text
private  — never web-accessible directly; every read goes through the application
           (auth + authorization checked), served via a signed/temporary URL or a
           streamed controller response. Use for: user documents, invoices, KYC files.

public   — served directly from a public URL (local /storage symlink, or a public
           cloud bucket/CDN). No per-request auth. Use for: avatars, product images,
           anything meant to be linked directly.

cloud    — an umbrella for any remote provider: S3 / S3-compatible (R2, MinIO,
           DigitalOcean Spaces, Wasabi) via league/flysystem-aws-s3-v3, and
           purpose-built media services like Cloudinary. "cloud" is not itself a
           driver — it's a category; each provider is its own driver/disk.
```

Config (`config/filesystems.php`):

```php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'), // set for R2 / MinIO / Spaces
            'visibility' => 'private',
        ],

        'cloudinary' => [
            'driver'    => 'cloudinary',
            'cloud_name'=> env('CLOUDINARY_CLOUD_NAME'),
            'api_key'   => env('CLOUDINARY_API_KEY'),
            'api_secret'=> env('CLOUDINARY_API_SECRET'),
            'folder'    => env('CLOUDINARY_FOLDER', 'app'),
        ],
    ],
];
```

## 43.3 Important correction — Cloudinary is not a plain Flysystem adapter

Unlike S3/R2/MinIO, which are genuine object stores and map cleanly onto Flysystem's `read/write/delete/list` model via `league/flysystem-aws-s3-v3`, Cloudinary is a media-transformation and CDN service (on-the-fly image/video resizing, format conversion, delivery URLs) — there is no official League adapter, and treating it as a flat filesystem loses most of its value. Two honest options:

```text
1. Write a thin custom StorageInterface driver ("CloudinaryDriver") on top of the
   official cloudinary/cloudinary_php SDK, implementing only the subset of
   StorageInterface that makes sense (put/delete/url) and exposing Cloudinary-specific
   extras (transformation(), eager transformations, signed delivery URLs) through a
   separate, explicit MediaInterface rather than pretending it is a generic disk.
2. Keep Cloudinary out of the generic filesystem abstraction entirely and offer it as
   its own optional yourvendor/media-cloudinary package, used explicitly by
   application code that actually needs transformations — this keeps the core
   FilesystemManagerInterface honest about what a "disk" can guarantee.
```

Either way, document the distinction in `docs/services/filesystem.md` (Section 6a) so users don't expect Cloudinary disks to behave exactly like S3 disks.

## 43.4 Architecture

```text
Application
   ↓
StorageInterface (yourvendor/contracts)
   ↓
FilesystemManager (disk resolution, config, temporary URLs)
   ↓
   ├── LocalAdapter ──────────► league/flysystem-local          (private/public disks)
   ├── S3Adapter ─────────────► league/flysystem-aws-s3-v3       (s3 / r2 / minio / Spaces)
   ├── FtpAdapter ────────────► league/flysystem-ftp / sftp-v3
   └── CloudinaryDriver ──────► cloudinary/cloudinary_php        (media, not generic disk)
```

Each application can register additional disks/drivers at boot without modifying the framework core — driver registration should be a public extension point (`storage()->extend('cloudinary', fn () => new CloudinaryDriver(...))`), the same pattern Section 85 (Package Discovery) uses for other subsystems.

---

# 44. Configuration

Create a configuration repository.

Avoid scattering:

```php
getenv('DATABASE_HOST')
```

through application code.

Prefer:

```php
config('database.host');
config('app.name');
config('mail.default');
```

Load environment values during bootstrap.

Potential infrastructure:

```text
symfony/config
symfony/dotenv
```

---

# 45. `.env`

Example:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_DRIVER=redis
```

Environment variables should enter the system through configuration, rather than being accessed all over the application.

---

# 46. Configuration, Route, and Container Caching

Production commands can eventually include:

```powershell
php forge config:cache
php forge route:cache
php forge container:cache
```

Potential generated cache files:

```text
bootstrap/cache/config.php
bootstrap/cache/routes.php
bootstrap/cache/container.php
```

This reduces runtime startup work.

---

# 47. CLI (`forge`) — an Artisan-Inspired, Laravel-Independent Console

Your framework needs its own first-class CLI, in the same spirit as Laravel's Artisan: a single entry-point binary, auto-discovered commands, generators that scaffold boilerplate from stubs, and an easy path for application developers to add their own commands. Laravel is used here purely as a **reference for developer experience** — the implementation underneath is your own, built on **Symfony Console**, with none of the `illuminate/console` code.

Example name:

```text
forge
```

Usage:

```powershell
php forge                 # lists all available commands, grouped by namespace
php forge help make:model # detailed help for one command
```

## 47.1 Command Set (Artisan-equivalent coverage)

```powershell
# Introspection / app info
php forge about
php forge env
php forge list

# Local development
php forge serve
php forge tinker              # interactive REPL (PsySH or a lightweight custom shell)

# Code generation ("make:*", see 47.3)
php forge make:controller UserController
php forge make:model User
php forge make:service UserService
php forge make:repository UserRepository
php forge make:dto CreateUserData
php forge make:request CreateUserRequest
php forge make:resource UserResource
php forge make:policy UserPolicy
php forge make:middleware EnsureTokenIsValid
php forge make:event UserRegistered
php forge make:listener SendWelcomeEmail
php forge make:job ProcessPayment
php forge make:command SyncInventory
php forge make:test UserServiceTest
php forge make:migration create_users_table
php forge make:seeder UserSeeder
php forge make:factory UserFactory

# Database
php forge migrate
php forge migrate:rollback
php forge migrate:refresh
php forge migrate:status
php forge db:seed

# Routing / config
php forge route:list
php forge route:cache
php forge config:cache
php forge config:clear
php forge cache:clear

# Storage (Section 43)
php forge storage:link         # symlink storage/app/public -> public/storage

# Queues / scheduling
php forge queue:work
php forge queue:retry
php forge queue:failed
php forge schedule:run
php forge schedule:list

# Auth
php forge key:generate
php forge token:issue --user=1 --scope=read

# Quality
php forge test
php forge stan                 # PHPStan wrapper
php forge fmt                  # PHP-CS-Fixer wrapper
```

## 47.2 Architecture

```text
bin/forge (entry script)
   ↓
Symfony\Component\Console\Application
   ↓
YourVendor\Console\Kernel
   ├── Boots the same Application/Container as the HTTP kernel (Section 8),
   │   so commands have access to config, DB, cache, storage, etc.
   ├── CommandRegistry — auto-discovers commands from:
   │       - framework packages (each ships its own commands)
   │       - app/Commands/ in the application
   │       - composer "extra.forge.commands" in any installed package (Section 85)
   └── Dispatches to Symfony Console for parsing, help text, and I/O.
```

A custom command is just a class the developer writes and drops in `app/Commands/`, with no manual registration step required:

```php
namespace App\Commands;

use YourVendor\Console\Command;
use YourVendor\Console\Attributes\AsCommand;

#[AsCommand(name: 'reports:daily', description: 'Generate the daily reconciliation report')]
class GenerateDailyReport extends Command
{
    public function handle(ReportService $reports): int
    {
        $reports->generateDaily();
        $this->info('Daily report generated.');

        return self::SUCCESS;
    }
}
```

## 47.3 Generators / Stubs

Every `make:*` command renders a versioned stub rather than hand-building strings, so application developers can override the framework's default templates:

```text
packages/console/stubs/
├── controller.stub
├── model.stub
├── request.stub
├── resource.stub
├── job.stub
├── event.stub
├── listener.stub
├── migration.stub
└── test.stub
```

```powershell
php forge stub:publish     # copies stubs into app/stubs/ for full customization
```

Symfony Console remains the strong infrastructure choice underneath; `forge` itself, its command taxonomy, generators, and stub system are entirely yours.

---

# 48. Code Generation

Code generation will significantly improve the developer experience.

Commands should eventually generate:

```text
Controller
Service
Repository
Model
DTO
Request
Resource
Policy
Migration
Job
Event
Listener
Command
Test
```

Example:

```powershell
php forge make:controller ProductController
```

creates:

```text
src/Controllers/ProductController.php
```

---

# 49. Application Project Structure

A generated API application can look like:

```text
my-api/
│
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   ├── Repositories/
│   ├── DTOs/
│   ├── Requests/
│   ├── Resources/
│   ├── Policies/
│   ├── Jobs/
│   ├── Events/
│   ├── Listeners/
│   └── Commands/
│
├── bootstrap/
│   └── app.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   ├── auth.php
│   ├── mail.php
│   └── queue.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php
│   └── health.php
│
├── storage/
│   ├── app/
│   │   ├── private/       # "private" disk root — never web-accessible
│   │   └── public/        # "public" disk root — symlinked via `forge storage:link`
│   ├── framework/
│   │   ├── cache/
│   │   └── views/
│   └── logs/
│
├── docs/                  # project-specific docs (API notes, ADRs, runbooks) —
│                           # distinct from the framework's own docs/ (Section 6a)
│
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Integration/
│
├── public/
│   ├── index.php
│   └── storage -> ../storage/app/public   # symlink created by `forge storage:link`
│
├── .env
├── .env.example
├── composer.json
└── forge
```

---

# 50. Do Not Put Business Logic in the Framework

The framework should not know about:

```text
Property
FootballMatch
InsurancePolicy
Agent
Invoice
Wallet
Order
Payment
```

Those are application/domain concepts.

Your framework should provide mechanisms for handling them.

---

# 51. Separate Framework and Domain Layers

Framework:

```text
HTTP
Database
Cache
Queue
Mail
Logging
Auth infrastructure
```

Application/domain:

```text
Users
Properties
Payments
Orders
Football
Insurance
Marketplace
Agencies
```

The framework should remain generic.

---

# 52. Application / Domain Service Layer

Encourage a structure like:

```text
Controller
    ↓
Application Service / Use Case
    ↓
Domain
    ↓
Infrastructure
```

Examples:

```text
CreateProperty
ApproveProperty
PublishProperty
AssignAgent
ProcessPayment
RefundPayment
RegisterUser
VerifyAgency
```

These are application-specific use cases, not framework classes.

---

# 53. Transaction and Event Ordering

Be deliberate about transactional events.

Preferred pattern for many important operations:

```text
Create payment
 ↓
Commit transaction
 ↓
PaymentCreated event
 ↓
Queue notification
```

Avoid:

```text
Create payment
 ↓
Send notification
 ↓
Transaction fails
```

This is particularly important for payments, wallets, orders, and inventory.

---

# 54. Idempotency

API frameworks should support idempotency, especially for financial or retry-prone operations.

HTTP example:

```http
Idempotency-Key: abc123
```

Possible framework API:

```php
$idempotency->execute(
    key: $request->header('Idempotency-Key'),
    callback: fn () => $service->execute()
);
```

Useful for:

```text
payments
orders
wallet operations
webhooks
resource creation
```

---

# 55. Webhooks

A reusable webhook subsystem can contain:

```text
Webhook
├── endpoint
├── event
├── payload
├── signature
├── delivery
├── attempts
├── response
└── status
```

Security features should include:

```text
HMAC signing
timestamps
replay protection
retries
exponential backoff
signature verification
```

---

# 56. Security

Build secure defaults for:

```text
input validation
password hashing
constant-time comparisons
token hashing
rate limits
CORS
security headers
request size limits
JSON depth limits
SQL parameterization
mass assignment protection
file upload restrictions
path traversal protection
SSRF protection helpers
secret handling
exception redaction
audit logs
```

Use PHP's security primitives and established libraries instead of inventing cryptographic algorithms.

---

# 57. Password Hashing

Expose an abstraction such as:

```php
$passwordHasher->hash($password);
$passwordHasher->verify($password, $hash);
```

Do not let individual applications choose low-level hashing details everywhere.

---

# 58. Audit Logging

Make it easy to record:

```text
who
did what
to what
when
from where
request ID
result
```

Example:

```json
{
    "user_id": 20,
    "action": "property.approved",
    "resource": "property",
    "resource_id": 430,
    "request_id": "..."
}
```

---

# 59. OpenAPI

For an API-first framework, OpenAPI should eventually be a first-class feature.

Potential endpoints:

```text
/openapi.json
/docs
```

Generate API documentation from route/schema metadata such as:

```php
#[Route(...)]
#[Response(...)]
#[QueryParameter(...)]
#[Body(...)]
```

Possible outputs:

```text
Swagger UI
OpenAPI JSON
OpenAPI YAML
client generation
```

---

# 60. Health Checks

Provide standard endpoints:

```text
GET /health
GET /ready
GET /live
```

Example:

```json
{
    "status": "ok",
    "version": "1.0.0"
}
```

Readiness can check:

```text
database
cache
queue
external dependencies
```

---

# 61. Observability

Eventually support:

```text
logs
metrics
tracing
request IDs
database timing
HTTP client timing
queue timing
```

You can later integrate OpenTelemetry without changing application code if the abstractions are designed well.

---

# 62. Testing Architecture

The framework itself requires extensive automated testing.

Include:

```text
Unit tests
Integration tests
HTTP tests
Database tests
Container tests
Routing tests
Middleware tests
CLI tests
Queue tests
```

A framework test client can provide:

```php
$response = $this->get('/api/v1/users');

$this->assertStatus(200);

$this->assertJsonStructure([
    'data',
]);
```

---

# 63. Static Analysis

Recommended tools:

```text
PHPStan
PHPUnit
PHP-CS-Fixer
```

Potential additions:

```text
Rector
Infection
Psalm
```

Use strict static analysis early.

---

# 64. CI/CD

Your repository should automatically run:

```text
composer validate
composer audit
phpstan
phpunit
php-cs-fixer --dry-run
```

Test at minimum:

```text
PHP 8.4
PHP 8.5
```

and your supported databases.

---

# 65. Semantic Versioning

During early development:

```text
0.x
```

is appropriate.

Once your contracts stabilize:

```text
1.0.0
```

Use:

```text
1.0.0
1.1.0
1.2.0
2.0.0
```

and document breaking changes.

---

# 66. Avoid Exposing Vendor Classes

This is one of the most important rules.

Avoid application code like:

```php
function foo(\Doctrine\DBAL\Connection $db)
```

everywhere.

Prefer:

```php
function foo(DatabaseConnectionInterface $db)
```

Similarly, consider your own abstractions for:

```text
Request
Response
Cache
Mailer
Storage
Queue
```

Where PSR already provides an appropriate standard, use the PSR type directly.

Good examples:

```php
Psr\Log\LoggerInterface
Psr\Http\Message\ServerRequestInterface
Psr\Container\ContainerInterface
```

---

# 67. Vendor Adapters

Think of external libraries as adapters.

Example:

```text
yourvendor/cache
│
├── CacheManager
├── CacheInterface
│
└── Adapters/
    ├── SymfonyCacheAdapter
    ├── RedisAdapter
    └── ArrayAdapter
```

The architectural flow becomes:

```text
Framework
    ↓
CacheInterface
    ↓
SymfonyCacheAdapter
    ↓
Symfony Cache
```

Use this concept throughout the framework.

---

# 68. Avoid Overbuilding v1

Do not start by trying to build everything.

Avoid implementing all of these immediately:

```text
ORM
WebSockets
GraphQL
OAuth
Scheduler
OpenAPI
Storage
Mail
Events
Queues
Notifications
Search
Admin
Templating
```

Build a small, correct framework first.

---

# 69. MVP Framework Scope

Define v0.1 approximately as:

```text
1. Composer
2. Application
3. Dependency Injection
4. Configuration
5. HTTP Request/Response
6. Router
7. Middleware
8. Controller Resolver
9. Exception Handler
10. JSON Response
11. Validation
12. Database
13. Migrations
14. Logging
15. CLI
16. Testing
```

That is already a legitimate API framework.

---

# 70. v0.2

Add:

```text
Authentication
Authorization
API Resources
Pagination
Caching
Rate Limiting
Events
```

---

# 71. v0.3

Add:

```text
Queues
Jobs
Mail
Filesystem
HTTP Client
Scheduling
Notifications
Webhooks
```

---

# 72. v1.0

Aim for:

```text
OpenAPI
CLI generators
Configuration cache
Route cache
Container compilation
Production optimizations
Documentation
Upgrade guides
Strict backwards compatibility
```

---

# 73. The First Working Application

The ultimate test is whether a developer can create an API quickly.

Example:

```powershell
composer create-project yourvendor/api my-api

cd my-api

php forge key:generate
php forge migrate
php forge serve
```

Then:

```powershell
php forge make:controller UserController
```

and access:

```text
http://127.0.0.1:8000
```

---

# 74. Target Developer Experience

A finished framework could look like:

```php
use YourVendor\Routing\Attributes\Route;

final class UserController
{
    #[Route('GET', '/api/v1/users')]
    public function index(
        UserRepositoryInterface $users
    ): JsonResponse {
        return UserResource::collection(
            $users->paginate()
        );
    }

    #[Route('POST', '/api/v1/users')]
    public function store(
        CreateUserRequest $request,
        UserService $service
    ): JsonResponse {
        return UserResource::make(
            $service->create($request)
        )->status(201);
    }
}
```

Service example:

```php
final class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private EventDispatcherInterface $events,
    ) {}

    public function create(
        CreateUserRequest $request
    ): User {
        // business logic
    }
}
```

This provides the convenience of a modern framework while keeping the architecture independent.

---

# 75. Framework Internal Directory Structure

A useful starting structure:

```text
packages/core/src/
│
├── Application.php
├── Kernel.php
│
├── Bootstrap/
│   ├── Bootstrapper.php
│   ├── Environment.php
│   └── ApplicationFactory.php
│
├── Container/
│   ├── Container.php
│   └── ServiceProvider.php
│
├── Config/
│   ├── ConfigRepository.php
│   └── ConfigLoader.php
│
├── Http/
│   ├── Request.php
│   ├── Response.php
│   ├── JsonResponse.php
│   ├── ResponseFactory.php
│   └── ResponseEmitter.php
│
├── Routing/
│   ├── Router.php
│   ├── Route.php
│   ├── RouteCollection.php
│   └── ControllerInvoker.php
│
├── Middleware/
│   ├── Pipeline.php
│   └── MiddlewareStack.php
│
├── Exceptions/
│   ├── Handler.php
│   ├── HttpException.php
│   ├── ValidationException.php
│   └── ...
│
└── Support/
    ├── Arr.php
    ├── Str.php
    └── Helpers.php
```

Keep `Support/` intentionally small. Do not allow it to become a dumping ground for unrelated utilities.

---

# 76. Avoid Making Facades the Foundation

A framework can offer convenience facades later, but the architecture should favor dependency injection.

Instead of making this the core:

```php
Cache::put(...);
DB::table(...);
Auth::user();
Log::info(...);
```

prefer:

```php
public function __construct(
    CacheInterface $cache,
    DatabaseManagerInterface $db,
    AuthManagerInterface $auth,
    LoggerInterface $logger,
) {}
```

This improves:

```text
dependency visibility
testability
static analysis
maintainability
```

Facades can be an optional convenience feature.

---

# 77. Helpers Should Stay Limited

Do not create hundreds of global helper functions.

A small set of intentional helpers is enough:

```text
app()
config()
response()
request()
now()
dispatch()
```

Actual behavior should mostly live in services and framework components.

---

# 78. Supported PHP Extensions

Document your framework's requirements clearly.

At minimum, many API deployments will need common PHP extensions such as:

```text
ctype
json
mbstring
openssl
pdo
```

and the specific database drivers:

```text
pdo_mysql
pdo_pgsql
```

depending on the selected database.

Do not assume every production host has the same extensions.

---

# 79. Performance Strategy

Do not optimize before the architecture is correct.

Priorities:

```text
correctness
testability
security
maintainability
```

Then optimize with:

```text
Composer optimized autoload
configuration cache
route cache
compiled dependency container
serializer metadata cache
OPcache
Redis
connection reuse
lazy services
minimal middleware
```

---

# 80. Long-Running Runtime Support

Design the framework so it can eventually run under:

```text
PHP-FPM
FrankenPHP
RoadRunner
Swoole
ReactPHP
```

This requires careful handling of global/static state.

A useful principle is:

```text
One request = controlled request state
```

Do not allow request-specific information to leak into later requests in long-running workers.

---

# 81. Framework Lifecycle

A clean lifecycle can eventually look like:

```php
$app->boot();

$response = $app->handle($request);

$app->terminate($request, $response);
```

This makes it easier to support:

```text
before-request hooks
after-response hooks
termination hooks
queue workers
CLI commands
scheduled tasks
```

---

# 82. HTTP, CLI, and Worker Runtimes

Do not create unrelated application bootstraps.

Use:

```text
Application
   │
   ├── HTTP runtime
   ├── CLI runtime
   └── Worker runtime
```

The same services can then work in all environments:

```text
UserService
DatabaseManager
Logger
Config
Cache
```

---

# 83. Queue Workers

A queue worker should bootstrap the same application:

```powershell
php forge queue:work
```

Architecture:

```text
Application
   ↓
Container
   ↓
Queue Worker
   ↓
Job
```

Do not create an entirely separate framework for workers.

---

# 84. Scheduling

Eventually support:

```powershell
php forge schedule:run
```

Possible scheduling API:

```text
everyMinute()
hourly()
daily()
weekly()
cron()
```

Implement this after the command and queue infrastructure is stable.

---

# 85. Package Discovery

Eventually your packages can declare providers through Composer metadata.

Example:

```json
{
    "extra": {
        "your-framework": {
            "providers": [
                "Package\\ServiceProvider"
            ]
        }
    }
}
```

Your framework can discover those providers after Composer installation.

---

# 86. Your Own Package Ecosystem

Once the framework is stable, you can create reusable packages such as:

```text
yourvendor/framework
yourvendor/auth
yourvendor/payments
yourvendor/paystack
yourvendor/flutterwave
yourvendor/maps
yourvendor/notifications
yourvendor/audit
yourvendor/media
yourvendor/openapi
yourvendor/rbac
yourvendor/tenancy
```

The package itself can remain generic enough to work with multiple PHP frameworks where appropriate.

---

# 87. Framework vs Ecosystem

You are effectively building two layers.

## Framework

```text
HTTP
Routing
DI
Config
Database
Validation
Auth
Cache
Queue
CLI
```

## Ecosystem

```text
Paystack
Flutterwave
AWS
Firebase
Google Maps
SMS providers
Email providers
Cloud storage
Insurance APIs
Payment integrations
```

Do not mix these.

The framework should not know anything about Paystack.

A package such as:

```text
yourvendor/paystack
```

should know about Paystack.

---

# 88. Framework Package Dependency Rules

Keep the dependency graph clean.

Good:

```text
Contracts
    ↓
Core
    ↓
HTTP
    ↓
Routing
```

Bad:

```text
Routing
X→ Database
X→ Mail
X→ Authentication
```

Avoid circular dependencies.

A good rule:

```text
Contracts
    ↓
Core
    ↓
Infrastructure
```

while application features sit above those layers.

---

# 89. Composer Package Example

A meta-framework package might look conceptually like:

```json
{
    "name": "yourvendor/framework",
    "description": "Independent API framework for PHP",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",

        "psr/container": "^2.0",
        "psr/http-message": "^2.0",
        "psr/http-server-handler": "^1.0",
        "psr/http-server-middleware": "^1.0",
        "psr/log": "^3.0",
        "psr/event-dispatcher": "^1.0",
        "psr/simple-cache": "^3.0",
        "psr/clock": "^1.0",

        "symfony/config": "^7.4",
        "symfony/console": "^7.4",
        "symfony/dependency-injection": "^7.4",
        "symfony/dotenv": "^7.4",
        "symfony/event-dispatcher": "^7.4",
        "symfony/routing": "^7.4",
        "symfony/serializer": "^7.4",
        "symfony/uid": "^7.4",
        "symfony/validator": "^7.4",

        "doctrine/dbal": "^4.4",
        "monolog/monolog": "^3.12"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\Framework\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YourVendor\\Framework\\Tests\\": "tests/"
        }
    }
}
```

In the real multi-package repository, individual dependencies should live in the package that actually needs them rather than putting the entire stack into one package.

---

# 90. Dependency Philosophy

Ask this question for every new feature:

## Is this framework-specific behavior?

Implement it yourself.

Examples:

```text
Application
Kernel
Framework contracts
Route API
Resource system
API response conventions
Service Provider system
Framework CLI
Middleware orchestration
Auth manager
API conventions
```

## Is this difficult infrastructure with mature standards/libraries?

Prefer an established package.

Examples:

```text
dependency injection
database abstraction
logging
serialization
validation
routing
cache backend
queue transport
SMTP
filesystem
HTTP transport
UUID
```

This balance prevents the project from becoming unnecessarily large.

---

# 91. Recommended Development Roadmap

## Phase 1 — Foundation

Build:

```text
Composer
PSR-4
Application
Kernel
Container
Service Provider
Config
Environment
CLI
```

Success condition:

```powershell
php forge about
```

works.

---

## Phase 2 — HTTP

Build:

```text
Request
Response
JSON
Response emitter
HTTP exceptions
```

Success condition:

```text
GET /hello
```

returns:

```json
{
    "message": "Hello"
}
```

---

## Phase 3 — Router

Implement:

```text
GET
POST
PUT
PATCH
DELETE
parameters
groups
prefixes
middleware
route names
```

Success condition:

```text
/api/v1/users/{id}
```

works.

---

## Phase 4 — Controllers

Implement:

```text
constructor DI
method DI
route parameters
request injection
response conversion
```

Success condition:

```php
public function show(
    UserService $service,
    int $id
)
```

is automatically resolved.

---

## Phase 5 — Middleware

Implement:

```text
global middleware
group middleware
route middleware
pipeline
```

---

## Phase 6 — Exceptions

Centralize:

```text
404
401
403
422
429
500
```

handling.

---

## Phase 7 — Database

Implement:

```text
DatabaseManager
connections
queries
transactions
repositories
migrations
```

---

## Phase 8 — Validation + DTO

Implement:

```text
Request DTO
validation
serialization
```

---

## Phase 9 — API Resources

Implement:

```text
Resource
ResourceCollection
Pagination
JSON envelopes
```

At this point, you have a genuinely useful API framework.

---

## Phase 10 — Authentication

Implement:

```text
guards
bearer tokens
token storage
scopes
expiration
revocation
authorization
policies
```

---

## Phase 11 — Cache + Rate Limiting

Implement:

```text
cache manager
Redis adapter
rate limiter
throttling
```

---

## Phase 12 — Events + Jobs

Implement:

```text
events
listeners
jobs
queue
retry
backoff
failed jobs
```

---

## Phase 13 — Ecosystem

Add:

```text
mail
filesystem
notifications
HTTP client
webhooks
OpenAPI
scheduler
```

---

## Phase 14 — Developer Experience

Polish:

```text
forge make:controller
forge make:model
forge make:service
forge make:request
forge make:resource
forge make:test
forge route:list
forge db:seed
```

---

# 92. Suggested Framework v1.0 Completion Criteria

Do not call the framework 1.0 until a clean installation can support:

```text
✔ routing
✔ controllers
✔ dependency injection
✔ middleware
✔ configuration
✔ environment
✔ JSON responses
✔ validation
✔ DTOs
✔ API resources
✔ database
✔ migrations
✔ transactions
✔ authentication
✔ authorization
✔ cache
✔ rate limiting
✔ logging
✔ exceptions
✔ pagination
✔ queues
✔ events
✔ CLI (forge, artisan-equivalent command coverage + generators)
✔ multi-driver filesystem (private, public, s3/r2/minio, cloudinary)
✔ testing
✔ OpenAPI
✔ health checks
✔ production configuration
✔ documentation (docs/ Markdown set, Section 6a)
✔ PHP support matrix documented + CI-tested (Section 3)
```

And the dependency tree contains:

```text
0 Laravel dependencies
0 Illuminate dependencies
```

The public interfaces should be documented and covered by tests.

---

# 93. Recommended Final Architecture

A strong target architecture is:

```text
PHP 8.4+
        │
        ▼
Composer
        │
        ▼
PSR Contracts
        │
        ▼
Your Framework Contracts
        │
        ▼
Your Framework Core
        │
 ┌──────┼────────┬─────────┐
 ▼      ▼        ▼         ▼
HTTP  Routing  Database  Config
 │      │        │         │
 ▼      ▼        ▼         ▼
PSR   Symfony  Doctrine   Symfony
              │
              ▼
        MySQL / PostgreSQL
```

Infrastructure:

```text
Infrastructure
├── Monolog
├── Symfony Cache
├── Symfony Messenger
├── Symfony Mailer
├── Flysystem
└── Guzzle
```

Application layer:

```text
Business Applications
├── Users
├── Properties
├── Payments
├── Football
├── Insurance
├── Marketplace
└── etc.
```

---

# 94. API-First Recommendation

Because the goal is API-based PHP projects, make the framework **API-first rather than a general web framework**.

The core should prioritize:

```text
JSON
REST
DTOs
Resources
Validation
Bearer authentication
Pagination
Rate limiting
OpenAPI
Webhooks
Queues
Events
Transactions
API versioning
Structured errors
Observability
```

Do not put these into the core unless there is a strong reason:

```text
Blade
SSR views
Livewire
frontend scaffolding
template compilation
asset pipelines
```

Session and CSRF systems can be optional packages instead of core features.

---

# 95. The First Repository to Build

Start with:

```text
your-framework/
│
├── packages/
│   ├── contracts/
│   ├── core/
│   ├── http/
│   ├── routing/
│   ├── config/
│   ├── console/
│   └── framework/
│
├── skeleton/
│   └── api/
│
├── tests/
├── docs/
├── composer.json
└── README.md
```

Then get this sequence working before adding advanced systems:

```text
Composer
   ↓
Application
   ↓
Container
   ↓
Config
   ↓
HTTP
   ↓
Router
   ↓
Middleware
   ↓
Controller
   ↓
JSON response
```

Once that works reliably, add database, validation, resources, auth, cache, queues, and the ecosystem packages.

---

# 96. Final Design Principle

The framework should provide:

```text
YOUR conventions
+
YOUR contracts
+
YOUR developer experience
+
YOUR application lifecycle
+
mature vendor infrastructure
```

not:

```text
Laravel code rewritten under a different namespace
```

The strongest version of this project is therefore an **independent, API-first PHP framework with Laravel-inspired ergonomics, PSR-based contracts, Symfony/Doctrine/Monolog/Flysystem/Guzzle infrastructure, modular Composer packages, a `forge` CLI, and a clean separation between framework code and application business logic.**

---

# References and Further Reading

- PHP-FIG — PHP Standards Recommendations: https://www.php-fig.org/
- Composer documentation: https://getcomposer.org/doc/
- Symfony Components: https://symfony.com/doc/current/components/index.html
- Symfony Releases: https://symfony.com/releases
- Symfony DependencyInjection: https://symfony.com/doc/current/service_container.html
- Symfony Routing: https://symfony.com/doc/current/routing.html
- Symfony Validator: https://symfony.com/doc/current/validation.html
- Symfony Serializer: https://symfony.com/doc/current/serializer.html
- Symfony Cache: https://symfony.com/doc/current/cache.html
- Symfony Messenger: https://symfony.com/doc/current/messenger.html
- Symfony Console: https://symfony.com/doc/current/components/console.html
- Doctrine DBAL: https://www.doctrine-project.org/projects/dbal.html
- Doctrine ORM: https://www.doctrine-project.org/projects/orm.html
- Monolog: https://seldaek.github.io/monolog/
- Flysystem: https://flysystem.thephpleague.com/
- Flysystem AWS S3 v3 adapter: https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/
- Cloudinary PHP SDK: https://cloudinary.com/documentation/php_integration
- Guzzle: https://docs.guzzlephp.org/
- PHPUnit: https://phpunit.de/
- PHPStan: https://phpstan.org/
- PHP supported versions: https://www.php.net/supported-versions.php
- PHP RFCs: https://wiki.php.net/rfc

---

# Recommended Next Engineering Step

Turn this blueprint into an **actual monorepo specification** containing:

1. Every package and its responsibility.
2. Individual `composer.json` files.
3. Complete directory trees.
4. Core interfaces and class definitions.
5. Application bootstrap lifecycle.
6. Dependency Injection implementation.
7. HTTP + routing implementation.
8. Middleware pipeline.
9. `forge` CLI architecture, command registry, and stub-based generators.
10. Filesystem drivers: local private, local public, S3-compatible, and Cloudinary.
11. Skeleton API application.
12. `/api/v1/users` working example.
13. Test suite structure.
14. CI workflow (including the PHP-version compatibility matrix from Section 3).
15. Release/versioning strategy.
16. `docs/` Markdown documentation set (Section 6a), populated per topic.

That should be the first implementation document before writing the framework itself.
