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

if (!function_exists('gate')) {
    /**
     * The authorization gate: gate()->allows('update', $note).
     */
    function gate(): \VtPhp\Auth\Access\Gate
    {
        return app(\VtPhp\Auth\Access\Gate::class);
    }
}

if (!function_exists('dispatch')) {
    /**
     * Queue a job: dispatch(new SendInvoice($order))->delay(60).
     */
    function dispatch(\VtPhp\Queue\Job $job): \VtPhp\Queue\PendingDispatch
    {
        return new \VtPhp\Queue\PendingDispatch($job);
    }
}

if (!function_exists('abort')) {
    /**
     * Stop and send an error response: abort(404), abort(403, 'Not yours.'),
     * abort(503, 'Down for maintenance.', ['Retry-After' => '600']).
     *
     * @param array<string, string> $headers
     */
    function abort(int $status, string $message = '', array $headers = []): never
    {
        throw \VtPhp\Exceptions\HttpException::forStatus($status, $message, $headers);
    }
}

if (!function_exists('abort_if')) {
    /**
     * abort_if($note->locked, 423, 'This note is locked.')
     *
     * @param array<string, string> $headers
     */
    function abort_if(bool $condition, int $status, string $message = '', array $headers = []): void
    {
        if ($condition) {
            abort($status, $message, $headers);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * abort_unless($user->is_admin, 403)
     *
     * @param array<string, string> $headers
     */
    function abort_unless(bool $condition, int $status, string $message = '', array $headers = []): void
    {
        if (!$condition) {
            abort($status, $message, $headers);
        }
    }
}
