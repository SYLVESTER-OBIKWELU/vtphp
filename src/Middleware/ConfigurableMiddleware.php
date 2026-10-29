<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Middleware that accepts parameters from its route registration, written
 * Laravel-style as "Class:param1,param2", e.g.
 * `ThrottleRequests::class.':auth'` (see ThrottleRequests::using()).
 */
interface ConfigurableMiddleware extends MiddlewareInterface
{
    /**
     * @param array<int, string> $parameters
     */
    public function withParameters(array $parameters): static;
}
