# The service container and service providers

The service container is the part of VtPhp that creates objects for you.
When a controller asks for a `Mailer` or a `UserService`, the container
builds one, including anything that class needs in turn. Service providers
are the place where you teach the container about your own classes.

You can build a whole app without touching the container directly. Read this
page when you want to share one object across your app, swap an
implementation, or understand how things get "magically" passed in.

## The quick version

Ask for what you need by type, and it arrives:

```php
// app/Http/Controllers/ReportController.php
namespace App\Http\Controllers;

use VtPhp\Mail\Mailer;

final class ReportController
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function send(): array
    {
        // $this->mailer is ready to use; you never wrote "new Mailer(...)".
        return ['sent' => true];
    }
}
```

This is called dependency injection: a class lists what it depends on, and
the container hands those things in.

## How the container builds objects

When the container needs a class, it:

1. reads the class's constructor;
2. builds each typed argument the same way (so dependencies of dependencies
   work too);
3. uses default values for plain arguments such as `string $name = 'x'`.

So any ordinary class works with no setup at all. Setup is only needed when
the container can't guess what you want, for example when you ask for an
interface.

An interface is a list of methods without the code behind them, such as
`PaymentGateway`. A class that "implements" it supplies the code, such as
`StripePaymentGateway`. The container can't pick an implementation on its
own, so you tell it which one to use.

## Your first service provider

A service provider is a class where you register things with the container.
The app already has one: `app/Providers/AppServiceProvider.php`.

```php
// app/Providers/AppServiceProvider.php
namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use VtPhp\Foundation\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Whenever someone asks for PaymentGateway, give them StripePaymentGateway.
        $this->app->singleton(PaymentGateway::class, StripePaymentGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
```

Now any controller, command or class that asks for `PaymentGateway` gets
the shared `StripePaymentGateway`.

A provider has two methods:

- **`register()`** only registers things. Don't use other services here; they
  may not be registered yet.
- **`boot()`** runs after every provider has registered, so it's safe to use
  other services there.

### Adding a new provider

Create a class that extends `VtPhp\Foundation\ServiceProvider`, then add it
to the `providers` list in `config/app.php`:

```php
// config/app.php
'providers' => [
    \App\Providers\AppServiceProvider::class,
    \App\Providers\RouteServiceProvider::class,
    \App\Providers\PaymentServiceProvider::class,
],
```

Providers run on every request and every `php forge` command, in the order
listed. The framework's own core provider always runs first, so you don't
list it.

## Ways to register things

Inside a provider, `$this->app` is the container:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(Foo::class, Bar::class);          // a new Bar every time someone asks
$this->app->singleton(Foo::class, Bar::class);     // one shared Bar for the whole request
$this->app->instance(Foo::class, $existingObject); // use this exact object
$this->app->alias('foo', Foo::class);              // a short name for Foo
```

`bind` and `singleton` also accept a function, for objects that need custom
setup such as reading a setting:

```php
// app/Providers/AppServiceProvider.php
use VtPhp\Foundation\Application;

$this->app->singleton(StripePaymentGateway::class, fn (Application $app) => new StripePaymentGateway(
    apiKey: (string) $app->make('config')->get('services.stripe.key'),
));
```

The framework's `src/Foundation/CoreServiceProvider.php` registers its own
services this way, and is a good place to see more examples.

## Getting things out of the container

Most of the time you just type-hint, as in the quick version. When you need
something by hand:

```php
$mailer = app(\VtPhp\Mail\Mailer::class); // same as app()->make(Mailer::class)
$app = app();                              // the container itself

$app->make(Foo::class);                    // build or fetch a Foo
$app->get(Foo::class);                     // the same, standard (PSR-11) name
$app->has(Foo::class);                     // can it provide a Foo?
```

The helpers you use every day are shortcuts to the container: `config()`,
`cache()`, `session()`, `auth()`, `view()` and `response()`.

### Calling a function with its arguments filled in

`call()` runs a function or method and fills in its arguments. Typed
arguments come from the container, and you supply the rest by name:

```php
$result = app()->call(function (int $id, Mailer $mailer): array {
    return ['id' => $id];
}, ['id' => 5]);
```

This is how controller actions receive both route values and services.

## Going further

### Optional dependencies

If an argument is optional, such as `?PaymentGateway $gateway = null`, and
the container has no way to build it (for example an interface you haven't
registered), the argument gets its default value instead of an error. If the
class *can* be built but one of its own dependencies is broken, you still get
an error, so real mistakes aren't hidden.

### Running code after an object is built

`afterResolving()` runs a callback every time the container builds an object
of a given type:

```php
// app/Providers/AppServiceProvider.php
$this->app->afterResolving(AuditLogger::class, function (AuditLogger $logger): void {
    $logger->setChannel('audit');
});
```

The framework uses this to check [form requests](validation.md) the moment
they're passed into a controller.

### Where framework and app code live

`src/` is the framework (namespace `VtPhp\`) and `app/` is your application
(namespace `App\`). Framework code never refers to your app's classes
directly. When the framework needs something app-specific, it defines an
interface, and your app supplies the implementation. For example, the login
system can use any class implementing `VtPhp\Auth\UserProviderInterface`.
Follow the same pattern for your own reusable code.

## Common mistakes

- **"Target [...] is an interface with no binding."** You asked for an
  interface but never said which class to use. Register it with `bind()` or
  `singleton()` in a provider.
- **"Unresolvable dependency $name in class ..."** A constructor argument
  has no type the container can build and no default value. Give it a
  default, or build the class yourself with a function in `bind()`.
- **"Target class [...] does not exist."** A typo in a class name or `use`
  statement, or the class isn't in an autoloaded folder.
- **Your provider never runs.** Add it to `providers` in `config/app.php`.
- **Using a service in `register()`.** Move that code to `boot()`.

## Next steps

- [Controllers](controllers.md): where injection is used most.
- [How a request works](request-lifecycle.md): when providers run.
- [CLI](cli.md): commands get injection too.
