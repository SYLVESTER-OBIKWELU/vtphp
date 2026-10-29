<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\ThrottleRequests;
use VtPhp\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1', 'middleware' => [ThrottleRequests::using('api')]], function (Router $router): void {
    $throttleAuth = [ThrottleRequests::using('auth')];
    // Logged in with a session cookie (browsers) or a bearer token (apps).
    $signedIn = [Authenticate::using('web', 'api')];

    // Sign-up is public; everything else needs a logged-in user.
    $router->post('/users', [UserController::class, 'store'], 'users.store', $throttleAuth);
    $router->apiResource('users', UserController::class, ['except' => ['store'], 'middleware' => $signedIn]);

    $router->post('/login', [AuthController::class, 'login'], 'auth.login', $throttleAuth);
    $router->post('/logout', [AuthController::class, 'logout'], 'auth.logout');
    $router->get('/me', [AuthController::class, 'me'], 'auth.me', $signedIn);

    // API tokens for mobile apps, scripts and other servers.
    $router->post('/tokens', [TokenController::class, 'store'], 'tokens.store', $throttleAuth);
    $router->get('/tokens', [TokenController::class, 'index'], 'tokens.index', $signedIn);
    $router->delete('/tokens/{token}', [TokenController::class, 'destroy'], 'tokens.destroy', $signedIn);

    $router->post('/password/forgot', [PasswordResetController::class, 'forgot'], 'password.forgot', $throttleAuth);
    $router->post('/password/reset', [PasswordResetController::class, 'reset'], 'password.reset', $throttleAuth);

    $router->post('/email/verification-notification', [EmailVerificationController::class, 'send'], 'verification.send', $throttleAuth);
    $router->get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'], 'verification.verify');
});
