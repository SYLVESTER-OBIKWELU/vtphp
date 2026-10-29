<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Auth\AuthManager;
use VtPhp\Exceptions\AuthenticationException;

/**
 * Rejects guests with 401. By default it checks the `web` (session) guard;
 * pass guards to accept others, tried in order:
 *
 *     [Authenticate::class]                    // session cookie only
 *     [Authenticate::using('api')]             // bearer token only
 *     [Authenticate::using('web', 'api')]      // either
 *
 * The guard that matched becomes the default for the rest of the request,
 * so auth()->user() and $request->user() work whichever was used.
 */
final class Authenticate implements ConfigurableMiddleware
{
    /** @var array<int, string> */
    private array $guards = ['web'];

    public function __construct(private readonly AuthManager $auth)
    {
    }

    public static function using(string ...$guards): string
    {
        return self::class.':'.implode(',', $guards);
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->guards = $parameters !== [] ? array_values($parameters) : ['web'];

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->shouldUse($guard);

                return $handler->handle($request);
            }
        }

        throw new AuthenticationException();
    }
}
