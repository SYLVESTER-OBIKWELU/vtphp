# VTPHP Framework - Virtual Tech PHP

A powerful, modern PHP framework inspired by Laravel, featuring Eloquent-like ORM, Blade templating (.blade.php), service providers, collections, comprehensive CLI tools, and modern frontend stack with Vite + Tailwind CSS.

---

## 🎯 Quick Navigation

- **[📚 Full Documentation →](docs/index.md)** - Complete guides and tutorials
- **[⚡ Quick Start →](docs/getting-started/QUICK_START.md)** - Get started in 5 minutes
- **[📖 Complete Guide →](docs/VTPHP_COMPLETE_GUIDE.md)** - Everything you need to know
- **[🔍 Quick Reference →](docs/QUICK_REFERENCE.md)** - Commands and snippets

---

## ✨ Features

✅ **MVC Architecture** - Clean separation of concerns  
✅ **Eloquent-like ORM** - ActiveRecord pattern with query builder  
✅ **Blade Templating** - .blade.php files with layouts, components, slots  
✅ **Service Providers** - SDK and package integration support  
✅ **Collections** - 40+ powerful array manipulation methods  
✅ **RESTful Routing** - Resource routes with middleware (CORS, CSRF, Auth)  
✅ **Database Migrations** - Version control for your database  
✅ **Validation System** - 20+ built-in validation rules  
✅ **Artisan CLI** - 95+ commands for rapid development  
✅ **Beautiful Error Pages** - Laravel-like exception handler with stack traces  
✅ **Vite + Tailwind CSS** - Modern frontend tooling with hot reload  
✅ **Alpine.js** - Lightweight reactive framework included  
✅ **Mail System** - PHPMailer integration  
✅ **Logging** - Monolog with multiple channels  
✅ **Cache** - File, Redis, Database drivers  
✅ **Storage** - Flysystem for local/cloud storage  
✅ **Events** - Event dispatcher system  
✅ **Queue** - Background job processing

## 📦 Installation & Setup

### 1. Install PHP Dependencies

```bash
composer install
```

### 2. Install Node Dependencies (for Vite + Tailwind)

```bash
npm install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database:

```env
APP_NAME="VTPHP Framework"
APP_ENV=development
APP_DEBUG=true
APP_KEY=your_generated_key_here

DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Create Database

Create a MySQL database:

```sql
CREATE DATABASE your_database_name;
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Build Frontend Assets

**Development:**

```bash
npm run dev
```

**Production:**

```bash
npm run build
```

### 7. Start Development Server

```bash
php artisan serve
```

Visit: `http://localhost:8000`

## 🚀 Quick Examples

### Create a Model

```bash
php artisan make:model Post --migration
```

### Create a Controller

```bash
php artisan make:controller PostController --resource
```

### Create a Migration

```bash
php artisan make:migration create_posts_table
```

### Define Routes

Edit `routes/web.php`:

```php
$router->resource('/posts', 'App\Controller\PostController');
```

### Create API Routes

Edit `routes/api.php`:

```php
$router->apiResource('/posts', 'App\Controller\Api\PostController');
```

## Common CLI Commands

```bash
# List all commands
php artisan list

# Make commands
php artisan make:controller UserController --resource
php artisan make:model User --migration
php artisan make:migration create_users_table
php artisan make:middleware CheckAge
php artisan make:seeder DatabaseSeeder
php artisan make:request StoreUserRequest
php artisan make:provider CustomServiceProvider
php artisan make:command SendEmails

# Migration commands
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh
php artisan migrate:status
php artisan migrate:reset

# Database commands
php artisan db:seed
php artisan db:wipe

# Cache commands
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan config:cache

# Utility commands
php artisan route:list
php artisan key:generate
php artisan tinker

# Development server
php artisan serve
php artisan serve --port=8080
```

## Using Collections

```php
$users = User::all();

// Filter and transform
$activeUsers = $users
    ->where('status', 'active')
    ->sortBy('name')
    ->pluck('email');

// Map data
$userNames = collect($users)->map(function($user) {
    return strtoupper($user->name);
});

// Group by attribute
$byRole = $users->groupBy('role');
```

## Service Providers for SDK Integration

Install any Composer package and integrate it:

```bash
composer require vendor/package
```

Create a service provider:

```bash
php artisan make:provider PackageServiceProvider
```

```php
class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('package', function() {
            return new Package(env('PACKAGE_KEY'));
        });
    }
}
```

Register in `config/app.php`:

```php
'providers' => [
    App\Providers\PackageServiceProvider::class,
],
```

## Blade Templating

Create layouts and components:

```php
<!-- layouts/app.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
</head>
<body>
    @yield('content')
</body>
</html>

<!-- home.php -->
@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <h1>Welcome!</h1>

    @component('components.card')
        @slot('title')
            Featured Content
        @endslot

        This is the card body.
    @endcomponent
@endsection
```

## 📁 Folder Structure

```
framework/
├── app/
│   ├── Controller/        # Your controllers
│   ├── Middleware/        # Custom middleware
│   ├── Models/            # Your models
│   ├── Mail/              # Mail classes
│   ├── Events/            # Event classes
│   ├── Jobs/              # Queue jobs
│   └── Policies/          # Authorization policies
├── config/                # Configuration files
├── core/                  # Framework core (View, Router, Model, etc.)
├── database/
│   ├── migrations/        # Database migrations
│   └── factories/         # Model factories
├── docs/                  # 📚 Complete Documentation
├── public_html/           # Web root (index.php)
│   └── build/             # Built assets (Vite)
├── resources/
│   ├── views/             # Blade templates (.blade.php)
│   ├── css/               # Tailwind CSS
│   └── js/                # Alpine.js + Axios
├── routes/                # Route definitions
│   ├── web.php            # Web routes
│   └── api.php            # API routes
├── storage/
│   ├── app/               # File storage
│   ├── cache/             # Cache files
│   └── logs/              # Log files
├── tests/                 # PHPUnit tests
└── vendor/                # Composer dependencies
```

## 📚 Documentation

All documentation is now located in the **`docs/`** folder:

- **[Documentation Index](docs/index.md)** - Start here!
- **[Quick Start Guide](docs/getting-started/QUICK_START.md)** - 5-minute setup
- **[Complete Framework Guide](docs/VTPHP_COMPLETE_GUIDE.md)** - Everything you need
- **[Quick Reference](docs/QUICK_REFERENCE.md)** - Commands and snippets
- **[Blade Templating](docs/BLADE.md)** - Layouts, components, directives
- **[Service Providers](docs/SERVICE_PROVIDERS.md)** - SDK integration
- **[Collections](docs/COLLECTIONS.md)** - 40+ array methods
- **[API Development](docs/API.md)** - REST API guide
- **[Advanced Topics](docs/ADVANCED.md)** - Transactions, uploads, caching

## 🎓 Next Steps

1. **Read** [Quick Start Guide](docs/getting-started/QUICK_START.md) to get up and running
2. **Follow** [CRUD Tutorial](docs/how-to-guides/crud-tutorial.md) to build your first app
3. **Learn** [Blade Templating](docs/BLADE.md) for beautiful views
4. **Explore** [Documentation Index](docs/index.md) for everything else

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This framework is open-sourced software licensed under the MIT license.

---

**VTPHP Framework v1.0.0** - Built with ❤️ by Virtual Tech 4. Use collections for data manipulation 5. Check example controllers in `app/Controller/` 6. Study example views in `resource/views/`

## Support

For detailed documentation, see `docs/README.md`

Happy coding! 🚀
