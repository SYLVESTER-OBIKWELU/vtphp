<?php

declare(strict_types=1);

use VtPhp\Foundation\Application;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            'empty' => '',
            default => $value,
        };
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $instance = Application::getInstance();

        return $abstract === null ? $instance : $instance->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $repository = app('config');

        return $key === null ? $repository : $repository->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return app()->appPath($path);
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (!function_exists('view')) {
    /**
     * @param array<string, mixed> $data
     */
    function view(string $view, array $data = []): string
    {
        return app(\VtPhp\View\BladeEngine::class)->render($view, $data);
    }
}

if (!function_exists('response')) {
    function response(): \VtPhp\Http\ResponseFactory
    {
        return app(\VtPhp\Http\ResponseFactory::class);
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone((string) config('app.timezone', 'UTC')));
    }
}

if (!function_exists('cache')) {
    function cache(): \VtPhp\Cache\CacheManager
    {
        return app(\VtPhp\Cache\CacheManager::class);
    }
}

if (!function_exists('cookie')) {
    function cookie(): \VtPhp\Cookie\CookieJar
    {
        return app(\VtPhp\Cookie\CookieJar::class);
    }
}

if (!function_exists('session')) {
    function session(): \VtPhp\Session\Session
    {
        return app(\VtPhp\Session\Session::class);
    }
}

if (!function_exists('auth')) {
    function auth(?string $guard = null): \VtPhp\Auth\GuardInterface
    {
        return app(\VtPhp\Auth\AuthManager::class)->guard($guard);
    }
}
