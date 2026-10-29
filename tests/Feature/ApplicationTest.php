<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use VtPhp\Cache\CacheManager;
use VtPhp\Config\Repository;
use VtPhp\Database\DatabaseManager;
use VtPhp\Foundation\Application;
use VtPhp\Foundation\ServiceProvider;
use VtPhp\Mail\Mailer;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;
use VtPhp\View\BladeEngine;

final class LateProvider extends ServiceProvider
{
    public static int $booted = 0;

    public function boot(): void
    {
        ++self::$booted;
    }
}

final class ApplicationTest extends FeatureTestCase
{
    public function test_bootstrap_resolves_paths_relative_to_the_base_path(): void
    {
        $base = $this->app->basePath();

        $this->assertDirectoryExists($base);
        $this->assertSame($base.'/config', $this->app->configPath());
        $this->assertSame($base.'/storage/logs', $this->app->storagePath('logs'));
        $this->assertSame($base.'/database/migrations', $this->app->databasePath('migrations'));
        $this->assertSame($base.'/app/Models', $this->app->appPath('/Models'));
        $this->assertSame($base.'/resources/views', $this->app->resourcePath('views'));
    }

    public function test_environment_and_debug_come_from_config(): void
    {
        $this->assertSame('testing', $this->app->environment());
        $this->assertFalse($this->app->isDebug());
    }

    public function test_core_services_are_singletons_with_aliases(): void
    {
        $expected = [
            'config' => Repository::class,
            'router' => Router::class,
            'db' => DatabaseManager::class,
            'view' => BladeEngine::class,
            'mailer' => Mailer::class,
            'cache' => CacheManager::class,
            'app' => Application::class,
        ];

        foreach ($expected as $alias => $class) {
            $this->assertInstanceOf($class, $this->app->make($alias), $alias);
            $this->assertSame($this->app->make($alias), $this->app->make($class), "{$alias} must be shared.");
        }
    }

    public function test_global_helpers_resolve_from_the_current_application(): void
    {
        $this->assertSame($this->app, app());
        $this->assertSame($this->app, Application::getInstance());
        $this->assertSame('VtPhp', config('app.name'));
        $this->assertInstanceOf(Repository::class, config());
        $this->assertSame($this->app->basePath('x'), base_path('x'));
        $this->assertSame($this->app->storagePath('x'), storage_path('x'));
        $this->assertInstanceOf(CacheManager::class, cache());
    }

    public function test_php_timezone_is_pinned_to_the_app_timezone(): void
    {
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame('UTC', now()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), now()->getTimestamp(), 2);
    }

    public function test_routes_are_loaded_by_the_route_service_provider(): void
    {
        $names = array_keys($this->app->make(Router::class)->routes()->all());

        $this->assertContains('health', $names);
        $this->assertContains('users.index', $names);
        $this->assertContains('auth.me', $names);
    }

    public function test_registering_a_provider_twice_returns_the_same_instance_and_late_providers_boot_immediately(): void
    {
        LateProvider::$booted = 0;

        $first = $this->app->register(LateProvider::class);
        $second = $this->app->register(LateProvider::class);

        $this->assertSame($first, $second);
        $this->assertSame(1, LateProvider::$booted);
    }
}
