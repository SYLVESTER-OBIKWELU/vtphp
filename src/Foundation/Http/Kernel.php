<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Foundation\Application;
use VtPhp\Middleware\AddQueuedCookiesToResponse;
use VtPhp\Middleware\HandleCors;
use VtPhp\Middleware\HandleExceptions;
use VtPhp\Middleware\Pipeline;
use VtPhp\Middleware\RequestIdMiddleware;
use VtPhp\Middleware\StartSession;
use VtPhp\Middleware\TrustProxies;
use VtPhp\Routing\Router;

final class Kernel
{
    /**
     * Outermost first. HandleExceptions stays last so every middleware above
     * it also wraps error responses. HandleCors sits near the top so CORS
     * preflights are answered before sessions start, and error responses
     * still get CORS headers.
     *
     * @var array<int, class-string>
     */
    protected array $middleware = [
        TrustProxies::class,
        RequestIdMiddleware::class,
        HandleCors::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        HandleExceptions::class,
    ];

    public function __construct(
        private Application $app,
        private Router $router,
        private ExceptionHandler $exceptions,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return (new Pipeline($this->app))
                ->send($request)
                ->through($this->middleware)
                ->then(fn (ServerRequestInterface $request): ResponseInterface => $this->router->dispatch($request));
        } catch (\Throwable $e) {
            // Last resort: a global middleware itself failed before
            // HandleExceptions could catch it.
            return $this->exceptions->render($e, $request);
        }
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // Reserved for after-response hooks (e.g. flushing queued jobs).
    }
}
