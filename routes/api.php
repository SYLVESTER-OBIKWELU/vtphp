<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\EmailVerificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\UserController;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    $router->controller(UserController::class);
    $router->controller(PasswordResetController::class);
    $router->controller(EmailVerificationController::class);
    $router->controller(AuthController::class);
});
