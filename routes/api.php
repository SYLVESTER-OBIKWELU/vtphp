<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserController;
use VtPhp\Middleware\Authenticate;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    // Sign-up is public; everything else needs a logged-in user.
    $router->post('/users', [UserController::class, 'store'], 'users.store');
    $router->apiResource('users', UserController::class, ['except' => ['store'], 'middleware' => [Authenticate::class]]);

    $router->post('/login', [AuthController::class, 'login'], 'auth.login');
    $router->post('/logout', [AuthController::class, 'logout'], 'auth.logout');
    $router->get('/me', [AuthController::class, 'me'], 'auth.me', [Authenticate::class]);

    $router->post('/password/forgot', [PasswordResetController::class, 'forgot'], 'password.forgot');
    $router->post('/password/reset', [PasswordResetController::class, 'reset'], 'password.reset');

    $router->post('/email/verification-notification', [EmailVerificationController::class, 'send'], 'verification.send');
    $router->get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'], 'verification.verify');
});
