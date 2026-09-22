<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16));
        $request = $request->withAttribute('request_id', $requestId);

        return $handler->handle($request)->withHeader('X-Request-ID', $requestId);
    }
}
