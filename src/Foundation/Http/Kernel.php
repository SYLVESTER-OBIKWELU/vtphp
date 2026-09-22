<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Foundation\Application;
use VtPhp\Middleware\Pipeline;
use VtPhp\Middleware\RequestIdMiddleware;
use VtPhp\Routing\Router;

final class Kernel
{
    /** @var array<int, class-string> */
    protected array $middleware = [
        RequestIdMiddleware::class,
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
            return $this->exceptions->render($e, $request);
        }
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // Reserved for after-response hooks (e.g. flushing queued jobs).
    }
}
