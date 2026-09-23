<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Auth\AuthManager;
use VtPhp\Exceptions\AuthenticationException;

/**
 * Rejects the request with a 401 if the given guard has no authenticated
 * user. Intended to be attached per-route via #[Route(middleware: [...])].
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly string $guard = 'web',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->auth->guard($this->guard)->guest()) {
            throw new AuthenticationException();
        }

        return $handler->handle($request);
    }
}
