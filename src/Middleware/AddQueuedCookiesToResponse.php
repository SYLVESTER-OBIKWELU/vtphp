<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Cookie\CookieJar;

/**
 * Attaches every cookie queued via CookieJar (e.g. by StartSession) to the
 * outgoing response as Set-Cookie headers. Must run outside (before) any
 * middleware that queues cookies, since PSR-15 pipelines unwind
 * outer-first — see Pipeline for the exact ordering semantics.
 */
final class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    public function __construct(private readonly CookieJar $cookies)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach ($this->cookies->queued() as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());
        }

        return $response;
    }
}
