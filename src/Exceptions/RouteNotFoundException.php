<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

/**
 * No route matches the URL (as opposed to a route that matched but whose
 * record doesn't exist). In debug mode the 404 suggests similar URLs.
 */
final class RouteNotFoundException extends NotFoundHttpException
{
    public function __construct(string $method, string $path)
    {
        parent::__construct("No endpoint matches {$method} {$path}.");
    }
}
