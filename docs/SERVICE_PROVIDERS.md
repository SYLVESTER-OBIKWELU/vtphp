# Service Providers & Package Integration

## Table of Contents

- [Introduction](#introduction)
- [Creating Service Providers](#creating-service-providers)
- [Registering Providers](#registering-providers)
- [Using Third-Party SDKs](#using-third-party-sdks)
- [Package Auto-Discovery](#package-auto-discovery)
- [Examples](#examples)

## Introduction

Service Providers are the central place for configuring and bootstrapping your application. They allow you to bind services into the container, register event listeners, middleware, and anything else your application needs before handling requests.

This system makes your framework compatible with third-party packages and SDKs installed via Composer.

## Creating Service Providers

Generate a new service provider using Artisan:

```bash
php artisan make:provider CustomServiceProvider
```

This creates a provider in `app/Providers/`:

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Bind services into the container
        $this->app->bind('custom.service', function() {
            return new \App\Services\CustomService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Bootstrap services after all providers registered
        // Load configuration, register middleware, etc.
    }
}
```

## Registering Providers

Add your provider to `config/app.php`:

```php
return [
    // ... other config

    'providers' => [
        App\Providers\AppServiceProvider::class,
        App\Providers\CustomServiceProvider::class,
        // Third-party providers
        Vendor\Package\ServiceProvider::class,
    ],
];
```

Providers are automatically loaded when the application boots.

## Register vs Boot

### register()

- Called immediately when provider is registered
- Used to bind things into the service container
- Other services may not be available yet
- Should only bind services, not use them

```php
public function register()
{
    $this->app->bind('mailer', function() {
        return new Mailer(config('mail'));
    });
}
```

### boot()

- Called after ALL providers have been registered
- All services are available in the container
- Can safely use other services
- Used for: loading routes, views, publishing assets, etc.

```php
public function boot()
{
    // All providers registered, safe to use services
    $router = $this->app->router();
    $router->middleware('custom', CustomMiddleware::class);
}
```

## Using Third-Party SDKs

### Installing Packages

```bash
composer require vendor/package-name
```

### Integrating SDKs

Create a service provider for the SDK:

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Vendor\SDK\Client;

class SDKServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('sdk.client', function() {
            return new Client([
                'api_key' => env('SDK_API_KEY'),
                'api_secret' => env('SDK_API_SECRET'),
            ]);
        });
    }
}
```

Register in `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\SDKServiceProvider::class,
],
```

Use in your application:

```php
$client = app()->resolve('sdk.client');
$result = $client->doSomething();
```

## Package Auto-Discovery

If a Composer package includes a service provider, you can manually register it or create an adapter:

### Manual Registration

```php
// config/app.php
'providers' => [
    // ...
    Vendor\Package\ServiceProvider::class,
],
```

### Creating an Adapter

For packages that don't follow our ServiceProvider pattern:

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Vendor\Package\Library;

class LibraryAdapter extends ServiceProvider
{
    public function register()
    {
        // Initialize the third-party library
        $library = new Library([
            'config' => config('library'),
        ]);

        $this->app->bind('library', function() use ($library) {
            return $library;
        });
    }

    public function boot()
    {
        // Configure the library after registration
        app()->resolve('library')->setEnvironment(env('APP_ENV'));
    }
}
```

## Examples

### Database Service Provider

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Core\Database;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('db', function() {
            return Database::getInstance(config('database'));
        });
    }
}
```

### Mailer Service Provider

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use PHPMailer\PHPMailer\PHPMailer;

class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('mailer', function() {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = env('MAIL_PORT', 587);

            return $mail;
        });
    }
}
```

### AWS SDK Integration

```bash
composer require aws/aws-sdk-php
```

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Aws\S3\S3Client;

class AWSServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('s3', function() {
            return new S3Client([
                'version' => 'latest',
                'region'  => env('AWS_REGION', 'us-east-1'),
                'credentials' => [
                    'key'    => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);
        });
    }
}
```

Usage in controllers:

```php
class FileController extends Controller
{
    public function upload(Request $request)
    {
        $s3 = app()->resolve('s3');

        $result = $s3->putObject([
            'Bucket' => env('AWS_BUCKET'),
            'Key'    => 'uploads/' . $request->file('file')->name,
            'Body'   => fopen($request->file('file')->tmp_name, 'r'),
        ]);

        return $this->json(['url' => $result['ObjectURL']]);
    }
}
```

### Stripe Payment Integration

```bash
composer require stripe/stripe-php
```

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Stripe\StripeClient;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('stripe', function() {
            return new StripeClient(env('STRIPE_SECRET_KEY'));
        });
    }
}
```

### Pusher Real-time Service

```bash
composer require pusher/pusher-php-server
```

```php
<?php

namespace App\Providers;

use Core\ServiceProvider;
use Pusher\Pusher;

class BroadcastServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('pusher', function() {
            return new Pusher(
                env('PUSHER_APP_KEY'),
                env('PUSHER_APP_SECRET'),
                env('PUSHER_APP_ID'),
                [
                    'cluster' => env('PUSHER_APP_CLUSTER'),
                    'useTLS' => true
                ]
            );
        });
    }
}
```

## Best Practices

1. **One responsibility** - Each provider should handle one service or related group of services
2. **Use configuration** - Store credentials in `.env` and access via `config()` or `env()`
3. **Lazy loading** - Use closures in `bind()` to instantiate services only when needed
4. **Document dependencies** - Add comments about required environment variables
5. **Test providers** - Ensure services are properly registered and bootable

## Helper Functions

Access bound services using the app container:

```php
// Via App instance
$service = app()->resolve('service.name');

// In controllers (if using base Controller)
$service = $this->app->resolve('service.name');
```

Create a helper for commonly used services:

```php
// core/helpers.php
if (!function_exists('mailer')) {
    function mailer() {
        return app()->resolve('mailer');
    }
}
```

## Tips

- Providers are loaded in the order they're registered
- Use `boot()` for operations that depend on other services
- Third-party packages often include their own service providers
- You can create providers for your own package abstractions
- Use type-hinting in closures for better IDE support
