<?php

// Load composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables early
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load helpers (includes env() function)
require_once __DIR__ . '/../core/helpers.php';

// Now register error handler (after env() is available)
require_once __DIR__ . '/../core/ErrorHandler.php';
Core\ErrorHandler::register();

// Set timezone
date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

// Start session
if (!session_id()) {
    session_start();
}

use Core\App;

try {
    $app = new App();

    // Load routes
    $app->loadRoutes(__DIR__ . '/../routes/web.php');
    $app->loadRoutes(__DIR__ . '/../routes/api.php');

    $app->run();
} catch (\Throwable $e) {
    Core\ErrorHandler::handleException($e);
}


