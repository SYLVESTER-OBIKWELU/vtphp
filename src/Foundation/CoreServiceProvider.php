<?php

declare(strict_types=1);

namespace VtPhp\Foundation;

use Psr\Log\LoggerInterface;
use VtPhp\Config\Repository;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\EloquentManager;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Http\ResponseFactory;
use VtPhp\Logging\LogManager;
use VtPhp\Mail\Mailer;
use VtPhp\Routing\ControllerDispatcher;
use VtPhp\Routing\Router;
use VtPhp\View\BladeEngine;

/**
 * Registers framework-internal singletons. Always loaded first by
 * Application::bootstrap(), regardless of the app's own provider list.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ControllerDispatcher::class,
            fn (Application $app) => new ControllerDispatcher($app)
        );

        $this->app->singleton(
            Router::class,
            fn (Application $app) => new Router($app->make(ControllerDispatcher::class))
        );

        $this->app->singleton(
            DatabaseManager::class,
            fn (Application $app) => new DatabaseManager($app->make(Repository::class))
        );

        $this->app->singleton(
            LogManager::class,
            fn (Application $app) => new LogManager($app->make(Repository::class))
        );

        $this->app->singleton(
            LoggerInterface::class,
            fn (Application $app) => $app->make(LogManager::class)->channel()
        );

        $this->app->singleton(
            ExceptionHandler::class,
            fn (Application $app) => new ExceptionHandler(
                $app->make(LoggerInterface::class),
                (bool) $app->make(Repository::class)->get('app.debug', false),
            )
        );

        $this->app->singleton(ResponseFactory::class, fn () => new ResponseFactory());

        $this->app->singleton(
            EloquentManager::class,
            fn (Application $app) => new EloquentManager($app->make(Repository::class))
        );

        $this->app->singleton(
            BladeEngine::class,
            fn (Application $app) => new BladeEngine($app->make(Repository::class))
        );

        $this->app->singleton(
            Mailer::class,
            fn (Application $app) => new Mailer($app->make(Repository::class), $app->make(LoggerInterface::class))
        );

        $this->app->make(EloquentManager::class)->boot();

        $this->app->alias('router', Router::class);
        $this->app->alias('db', DatabaseManager::class);
        $this->app->alias('view', BladeEngine::class);
        $this->app->alias('mailer', Mailer::class);
    }
}
