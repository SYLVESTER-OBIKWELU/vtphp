# Quick Reference Guide

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Blade Templates (.blade.php)

```php
// Views automatically use .blade.php
view('welcome')  // → welcome.blade.php
view('layouts.app')  // → layouts/app.blade.php
```

## Mail System

```php
Mail::make()
    ->to('user@example.com')
    ->subject('Hello')
    ->view('emails.welcome', ['name' => 'John'])
    ->send();
```

## Logging

```php
Log::info('User logged in', ['user_id' => 123]);
Log::error('Error occurred');
logger('Quick log message');
```

## Cache

```php
Cache::put('key', 'value', 3600);
$value = Cache::get('key', 'default');
$users = Cache::remember('users', 3600, fn() => User::all());
cache('key', 'default');  // helper
```

## Session

```php
Session::put('user_id', 123);
Session::flash('success', 'Saved!');
$token = Session::token();  // CSRF
session('key', 'default');  // helper
```

## Storage

```php
Storage::put('file.txt', 'contents');
$contents = Storage::get('file.txt');
Storage::delete('file.txt');
$files = Storage::files('uploads');
```

## Events

```php
Event::listen('user.registered', function($user) {
    // Handle event
});
Event::dispatch('user.registered', $user);
event('user.registered', $user);  // helper
```

## Hashing

```php
$hashed = Hash::make('password');
Hash::check('password', $hashed);
bcrypt('password');  // helper
```

## Helpers

```php
// URLs
asset('css/style.css')
url('/path')
back()

// Session
csrf_token()
csrf_field()
old('field')

// Strings
str_random(16)
str_slug('Hello World')
str_limit($text, 100)

// Dates
now()  // Carbon instance
today()
```

## Artisan Commands

```bash
# Make
php artisan make:controller UserController
php artisan make:model User --migration
php artisan make:middleware Auth
php artisan make:provider MailServiceProvider
php artisan make:seeder UserSeeder

# Database
php artisan migrate
php artisan migrate:rollback
php artisan migrate:status
php artisan db:seed

# Cache
php artisan cache:clear
php artisan view:clear
php artisan config:cache

# Utility
php artisan route:list
php artisan key:generate
php artisan serve
php artisan tinker
```

## Installing SDKs

```bash
# Install any package
composer require vendor/package

# Create service provider
php artisan make:provider PackageServiceProvider

# Register in config/app.php
'providers' => [
    App\Providers\PackageServiceProvider::class,
],

# Use anywhere
$service = app()->resolve('service');
```

## Popular Packages

```bash
composer require aws/aws-sdk-php        # AWS
composer require stripe/stripe-php       # Payments
composer require pusher/pusher-php-server  # WebSockets
composer require intervention/image      # Images
composer require maatwebsite/excel      # Excel
```

## Documentation

- **Main**: `docs/index.md`
- **Installation**: `docs/getting-started/installation.md`
- **Features**: `docs/features/`
- **Commands**: `docs/commands/`
- **How-To**: `docs/how-to-guides/`

## File Structure

```
framework/
├── app/
│   ├── controller/
│   ├── Models/
│   ├── middleware/
│   └── Providers/
├── config/
├── core/              # Framework core
├── database/
│   ├── migrations/
│   └── seeds/
├── docs/             # Documentation
├── public_html/
├── resource/
│   └── views/        # .blade.php files
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
│   ├── cache/
│   ├── logs/
│   └── app/
└── vendor/
```

## Environment Variables

```env
APP_NAME="My App"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://example.com

DB_HOST=localhost
DB_DATABASE=mydb
DB_USERNAME=user
DB_PASSWORD=pass

MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...

LOG_LEVEL=error
CACHE_DRIVER=file
SESSION_LIFETIME=120
```

## Quick Example: Build a Blog

```bash
# 1. Create model and migration
php artisan make:model Post --migration

# 2. Edit migration (database/migrations/xxx_create_posts_table.php)
$table->id();
$table->string('title');
$table->text('content');
$table->timestamps();

# 3. Run migration
php artisan migrate

# 4. Create controller
php artisan make:controller PostController --resource

# 5. Add routes (routes/web.php)
$router->resource('/posts', 'App\Controller\PostController');

# 6. Create view (resource/views/posts/index.blade.php)
@extends('layouts.app')
@section('content')
    @foreach($posts as $post)
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->content }}</p>
    @endforeach
@endsection

# 7. Done! Visit /posts
```

## Support

- 📖 Read `docs/index.md`
- 🔍 Check `FINAL_ENHANCEMENT_SUMMARY.md`
- 💬 Review example code in `app/`
