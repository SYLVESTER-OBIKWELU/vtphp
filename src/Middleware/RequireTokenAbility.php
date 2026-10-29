<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Exceptions\AuthorizationException;

/**
 * Requires the current API token to have every listed ability (403
 * otherwise). Session logins pass, since they aren't limited by token
 * abilities. Put it after Authenticate:
 *
 *     [Authenticate::using('web', 'api'), RequireTokenAbility::using('notes:write')]
 */
final class RequireTokenAbility implements ConfigurableMiddleware
{
    /** @var array<int, string> */
    private array $abilities = [];

    public static function using(string ...$abilities): string
    {
        return self::class.':'.implode(',', $abilities);
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->abilities = array_values($parameters);

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = auth()->user();

        foreach ($this->abilities as $ability) {
            if ($user !== null && method_exists($user, 'tokenCan') && !$user->tokenCan($ability)) {
                throw new AuthorizationException("This token is not allowed to [{$ability}].");
            }
        }

        return $handler->handle($request);
    }
}
