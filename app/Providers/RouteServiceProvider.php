<?php

declare(strict_types=1);

namespace App\Providers;

use VtPhp\Foundation\ServiceProvider;
use VtPhp\Routing\Router;

final class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $this->loadRoutes($router, $this->app->basePath('routes/health.php'));
        $this->loadRoutes($router, $this->app->basePath('routes/api.php'));
    }

    private function loadRoutes(Router $router, string $file): void
    {
        if (is_file($file)) {
            (function (string $file) use ($router): void {
                require $file;
            })($file);
        }
    }
}
