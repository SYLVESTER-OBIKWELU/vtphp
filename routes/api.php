<?php

// API routes with CORS middleware

use App\Controller\Api\UserController;
use App\Middleware\CorsMiddleware;

$router->group(['prefix' => 'api', 'middleware' => [CorsMiddleware::class]], function($router) {
    
    // Public API routes
    $router->post('/login', ['App\Controller\Api\AuthController', 'login']);
    $router->post('/register', ['App\Controller\Api\AuthController', 'register']);
    
    // Protected API routes
    $router->group(['middleware' => ['App\Middleware\AuthMiddleware']], function($router) {
        $router->get('/user', ['App\Controller\Api\AuthController', 'user']);
        $router->post('/logout', ['App\Controller\Api\AuthController', 'logout']);
        
        // API Resources
        $router->apiResource('/users', [UserController::class, 'index']);
        $router->apiResource('/posts', 'App\Controller\Api\PostController');
    });
});
