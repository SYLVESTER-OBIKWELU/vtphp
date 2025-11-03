<?php

// Web routes

use App\Controller\UserController;

$router->get('/', function($request) {
    return view('welcome');
})->name('home');

// Example routes with Laravel-like features
$router->get('/users', [UserController::class, 'index'])
    ->name('users.index');

$router->get('/users/{id}', [UserController::class, 'show'])
    ->name('users.show')
    ->where('id', '[0-9]+');

$router->post('/users', [UserController::class, 'store'])
    ->name('users.store');

$router->put('/users/{id}', [UserController::class, 'update'])
    ->name('users.update')
    ->where('id', '[0-9]+');

$router->delete('/users/{id}', [UserController::class, 'destroy'])
    ->name('users.destroy')
    ->where('id', '[0-9]+');

// Or using resource method (creates all CRUD routes automatically)
// $router->resource('/posts', PostController::class);

// Route groups example
// $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function($router) {
//     $router->get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
//     $router->get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
// });
