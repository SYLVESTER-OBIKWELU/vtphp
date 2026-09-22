<?php

declare(strict_types=1);

use App\Controllers\UserController;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    $router->controller(UserController::class);
});
