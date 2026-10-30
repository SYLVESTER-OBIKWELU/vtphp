<?php

declare(strict_types=1);

namespace VtPhp\Foundation;

use Psr\Log\LoggerInterface;
use VtPhp\Auth\Access\Gate;
use VtPhp\Auth\AuthManager;
use VtPhp\Container\BindingResolutionException;
use VtPhp\Cache\CacheManager;
use VtPhp\Config\Repository;
use VtPhp\Cookie\CookieJar;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\EloquentManager;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Exceptions\NotFoundHint;
use VtPhp\Http\FormRequest;
use VtPhp\Http\ResponseFactory;
use VtPhp\Logging\LogManager;
use VtPhp\Mail\Mailer;
use VtPhp\OpenApi\SwaggerController;
use VtPhp\Pagination\PaginationState;
use VtPhp\Queue\QueueManager;
use VtPhp\Queue\Worker;
use VtPhp\Routing\ControllerDispatcher;
use VtPhp\Routing\Router;
use VtPhp\Session\SessionManager;
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
                $app->basePath(),
                // Resolved only when a 404 happens in debug mode.
                static fn (ServerRequestInterface $request): string => (new NotFoundHint(
                    $app->make(Router::class),
                    $app->make(Repository::class),
                ))($request),
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

        $this->app->singleton(
            CacheManager::class,
            fn (Application $app) => new CacheManager($app->make(Repository::class), $app->make(DatabaseManager::class))
        );

        $this->app->singleton(CookieJar::class, fn () => new CookieJar());

        $this->app->singleton(
            SessionManager::class,
            fn (Application $app) => new SessionManager($app->make(Repository::class))
        );

        $this->app->singleton(
            AuthManager::class,
            fn (Application $app) => new AuthManager($app, $app->make(Repository::class))
        );

        $this->app->singleton(Gate::class, fn (Application $app) => new Gate($app, static function (): ?object {
            // Outside an HTTP request (e.g. a console command) there is no
            // session or token, so there is no current user.
            try {
                return auth()->user();
            } catch (BindingResolutionException) {
                return null;
            }
        }));

        $this->app->singleton(
            QueueManager::class,
            fn (Application $app) => new QueueManager($app, $app->make(Repository::class))
        );

        $this->app->bind(
            Worker::class,
            fn (Application $app) => new Worker($app->make(QueueManager::class), $app, $app->make(LoggerInterface::class))
        );

        $this->app->afterResolving(
            FormRequest::class,
            static fn (FormRequest $request) => $request->validateResolved(),
        );

        $this->app->make(EloquentManager::class)->boot();
        PaginationState::resolveUsing($this->app);

        $this->app->alias('router', Router::class);
        $this->app->alias('db', DatabaseManager::class);
        $this->app->alias('view', BladeEngine::class);
        $this->app->alias('mailer', Mailer::class);
        $this->app->alias('cache', CacheManager::class);
        $this->app->alias('queue', QueueManager::class);
    }

    public function boot(): void
    {
        $config = $this->app->make(Repository::class);

        // SWAGGER_ENABLED=true: interactive API docs at /api-docs.
        if ($config->get('swagger.enabled', false)) {
            $path = '/'.trim((string) $config->get('swagger.path', '/api-docs'), '/');
            $middleware = array_values(array_filter((array) $config->get('swagger.middleware', []), 'is_string'));
            $router = $this->app->make(Router::class);

            $router->get($path, [SwaggerController::class, 'ui'], 'swagger.ui', $middleware);
            $router->get(SwaggerController::jsonPath($path), [SwaggerController::class, 'json'], 'swagger.json', $middleware);
        }
    }
}
