# Changelog

All notable changes to VTPHP Framework will be documented in this file.

---

## [1.0.0] - 2025-11-03

### 🎉 Initial Release

#### Core Framework

- ✅ Complete MVC architecture
- ✅ Eloquent-like ORM with ActiveRecord pattern
- ✅ Query Builder with fluent interface
- ✅ Database Schema & Migrations
- ✅ RESTful Router with middleware support
- ✅ Request & Response classes
- ✅ Blade-like templating engine (.blade.php support)
- ✅ Validation system (20+ rules)
- ✅ Collections (40+ methods)

#### CLI Tools

- ✅ 95+ Artisan commands
- ✅ Code generators (make:model, make:controller, etc.)
- ✅ Migration commands (migrate, rollback, fresh, reset)
- ✅ Cache management (cache:clear, config:cache, etc.)
- ✅ Queue workers (queue:work, queue:listen, etc.)
- ✅ Optimization commands (optimize, optimize:clear)
- ✅ Development server (serve command)

#### Advanced Features

- ✅ Service Providers for SDK integration
- ✅ Event dispatcher system
- ✅ Queue system (sync, database, redis)
- ✅ Broadcasting (Pusher, Redis pub/sub)
- ✅ Notification system (mail, database, broadcast, SMS)
- ✅ Mail system (PHPMailer integration)
- ✅ Logging (Monolog with multiple channels)
- ✅ Caching (file, Redis, database, memcached)
- ✅ Session management (file, database, Redis)
- ✅ Cookie handling
- ✅ File storage (Flysystem - local, S3, FTP)
- ✅ Hash helpers (bcrypt)

#### Middleware

- ✅ Authentication middleware
- ✅ CORS middleware
- ✅ CSRF protection
- ✅ Custom middleware support

#### Frontend Stack

- ✅ Vite integration for fast HMR
- ✅ Tailwind CSS v3 with PostCSS
- ✅ Alpine.js for reactivity
- ✅ Axios for HTTP requests
- ✅ Custom Tailwind components
- ✅ Vite helper functions for assets

#### Configuration

- ✅ Environment variable support (.env)
- ✅ Configuration files for all systems
- ✅ Database configuration
- ✅ Mail configuration
- ✅ Cache configuration
- ✅ Session configuration
- ✅ Queue configuration
- ✅ Filesystem configuration
- ✅ Services configuration
- ✅ Logging configuration

#### Documentation

- ✅ Comprehensive documentation in `docs/` folder
- ✅ Quick Start Guide
- ✅ Complete Framework Guide
- ✅ Quick Reference
- ✅ Blade templating guide
- ✅ Collections guide
- ✅ Service Providers guide
- ✅ API development guide
- ✅ Advanced topics guide
- ✅ CRUD tutorial
- ✅ Command reference

#### Vendor Packages

- ✅ phpmailer/phpmailer - Email sending
- ✅ monolog/monolog - Logging
- ✅ nesbot/carbon - Date/time manipulation
- ✅ guzzlehttp/guzzle - HTTP client
- ✅ vlucas/phpdotenv - Environment variables
- ✅ symfony/var-dumper - Debugging
- ✅ symfony/console - CLI framework
- ✅ symfony/http-foundation - HTTP abstractions
- ✅ league/flysystem - Filesystem abstraction
- ✅ predis/predis - Redis client
- ✅ phpunit/phpunit - Testing
- ✅ fakerphp/faker - Fake data generation

#### Helper Functions

35+ helper functions including:

- `dd()`, `dump()` - Debugging
- `env()`, `config()` - Configuration
- `view()`, `redirect()`, `json()` - Responses
- `collect()` - Collections
- `bcrypt()`, `hash_check()` - Hashing
- `old()`, `session()`, `cookie()` - Session/Cookie
- `route()`, `url()`, `asset()` - URLs
- `cache()`, `log()`, `event()` - Core services
- `str_*()`, `array_*()` - String/Array helpers

#### Base Classes

- ✅ Controller base class
- ✅ Model base class
- ✅ Middleware base class
- ✅ Migration base class
- ✅ Job base class
- ✅ Policy base class

#### Examples Included

- ✅ UserController with CRUD operations
- ✅ User model with sample methods
- ✅ Sample views (welcome, users, layouts, components)
- ✅ Sample migration
- ✅ Route examples (web, API)

---

## [Unreleased]

### Planned Features

- WebSocket support
- Task scheduling
- Model factories and seeders
- Database query logging
- Request throttling/rate limiting
- Multi-language support (i18n)
- Built-in authentication scaffolding
- Password reset functionality
- Email verification
- Two-factor authentication
- Role-based access control (RBAC)

---

## Breaking Changes

### From resource/ to resources/

- **Changed:** Moved all views from `resource/views/` to `resources/views/`
- **Action:** Update any hardcoded paths in your application
- **Migration:** Views are automatically detected in new location

---

## Upgrade Guide

### To v1.0.0

This is the initial release. Fresh installation recommended.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
copy .env.example .env
php artisan key:generate

# 3. Setup database
php artisan migrate

# 4. Build assets
npm run build

# 5. Start server
php artisan serve
```

---

## Credits

VTPHP Framework is inspired by Laravel and built with love by the Virtual Tech team.

### Special Thanks To

- Laravel - For inspiration and patterns
- Tailwind CSS - For the amazing utility-first CSS framework
- Alpine.js - For lightweight reactivity
- All open-source contributors

---

**Note:** Semantic versioning is used for all releases (MAJOR.MINOR.PATCH)
