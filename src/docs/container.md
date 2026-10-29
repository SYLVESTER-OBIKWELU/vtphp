# Container & service providers

`VtPhp\Container\Container` is a PSR-11 container with reflection-based
autowiring — any class with a resolvable constructor (typed class
parameters, or scalar parameters with defaults) can simply be `make()`'d or
type-hinted, with no manual binding required.

```php
$app->bind(Foo::class, Bar::class);          // new instance every time
$app->singleton(Foo::class, Bar::class);     // shared instance
$app->instance(Foo::class, $existingObject); // pre-built shared instance
$app->alias('foo', Foo::class);              // short name -> FQCN

$app->make(Foo::class);
$app->get(Foo::class); // PSR-11 alias for make()
$app->has(Foo::class);
```

The concrete argument to `bind()`/`singleton()` can be a class name or a
closure `fn (Application $app) => ...` for cases that need custom
construction logic (reading config, wrapping another service, etc.) — see
`src/Foundation/CoreServiceProvider.php` for many examples.

## The `app()` helper

```php
app();                    // the Application (container) instance itself
app(Foo::class);          // same as app()->make(Foo::class)
```

Other helpers (`config()`, `cache()`, `session()`, `auth()`, `view()`,
`response()`, ...) are thin wrappers around `app()->make(...)` — see
`src/Support/helpers.php`.

## Service providers

A service provider is a class extending `VtPhp\Foundation\ServiceProvider`
with `register()` (bind things into the container) and/or `boot()` (runs
after all providers are registered — safe to resolve other services here):

```php
namespace App\Providers;

use VtPhp\Foundation\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepositoryInterface::class, EloquentUserRepository::class);
    }
}
```

Register your provider in `bootstrap/app.php`'s provider list (alongside
`AppServiceProvider`/`RouteServiceProvider`) so it runs on every request.

## Layering: `src/` vs `app/`

`src/` is the framework core (`VtPhp\` namespace) and `app/` is your
application (`App\` namespace). Framework code in `src/` never references
concrete `App\` classes — where the framework needs an app-specific concern
(e.g. "how do I look up a user?"), it defines a generic interface in `src/`
(e.g. `VtPhp\Auth\UserProviderInterface`) that your app implements in `app/`
and binds in a service provider. Follow this same pattern for your own
framework-adjacent extensions.
