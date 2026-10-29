<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Auth\Access\Gate;

/**
 * Requires a gate ability for a whole route (403 otherwise):
 *
 *     $router->get('/reports', [ReportController::class, 'index'], 'reports', [
 *         Authenticate::class, Can::using('viewReports'),
 *     ]);
 *
 * For checks about a specific record ("may they edit *this* note?") use
 * $this->authorize('update', $note) in the controller instead, where the
 * record has already been loaded.
 */
final class Can implements ConfigurableMiddleware
{
    private string $ability = '';

    public function __construct(private readonly Gate $gate)
    {
    }

    public static function using(string $ability): string
    {
        return self::class.':'.$ability;
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->ability = $parameters[0] ?? '';

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->gate->authorize($this->ability);

        return $handler->handle($request);
    }
}
