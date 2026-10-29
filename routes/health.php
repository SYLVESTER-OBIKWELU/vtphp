<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->get('/health', [HealthController::class, 'index'], 'health');
