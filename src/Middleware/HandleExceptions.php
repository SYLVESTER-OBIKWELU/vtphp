<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Exceptions\ExceptionHandler;

/**
 * Turns exceptions from routing, route middleware and controllers into JSON
 * error responses *inside* the global middleware stack, so error responses
 * still get X-Request-ID, queued cookies and a saved session from the
 * middleware wrapped around it. Must be the innermost global middleware.
 */
final class HandleExceptions implements MiddlewareInterface
{
    public function __construct(private readonly ExceptionHandler $exceptions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->exceptions->render($e, $request);
        }
    }
}
