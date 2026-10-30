<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['tag' => 'Health'], function (Router $router): void {
    $router->get('/', [HealthController::class, 'welcome'], 'root');
    $router->get('/health', [HealthController::class, 'index'], 'health');
});


