<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A PSR-15 middleware pipeline ("onion") that resolves string middleware
 * class names lazily via the container.
 */
final class Pipeline
{
    private ServerRequestInterface $request;

    /** @var array<int, string|MiddlewareInterface> */
    private array $middleware = [];

    public function __construct(private ContainerInterface $container)
    {
    }

    public function send(ServerRequestInterface $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param array<int, string|MiddlewareInterface> $middleware
     */
    public function through(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function then(\Closure $destination): ResponseInterface
    {
        $handler = new class ($destination) implements RequestHandlerInterface {
            public function __construct(private \Closure $destination)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->destination)($request);
            }
        };

        $container = $this->container;

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function (RequestHandlerInterface $next, string|MiddlewareInterface $middleware) use ($container) {
                return new class ($container, $middleware, $next) implements RequestHandlerInterface {
                    public function __construct(
                        private ContainerInterface $container,
                        private string|MiddlewareInterface $middleware,
                        private RequestHandlerInterface $next,
                    ) {
                    }

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        if ($this->middleware instanceof MiddlewareInterface) {
                            return $this->middleware->process($request, $this->next);
                        }

                        // "Class:param1,param2" passes parameters to a ConfigurableMiddleware.
                        [$class, $parameters] = array_pad(explode(':', $this->middleware, 2), 2, null);
                        $instance = $this->container->get($class);

                        if ($parameters !== null) {
                            if (!$instance instanceof ConfigurableMiddleware) {
                                throw new \InvalidArgumentException("Middleware [{$class}] does not accept parameters.");
                            }

                            $instance = $instance->withParameters(explode(',', $parameters));
                        }

                        return $instance->process($request, $this->next);
                    }
                };
            },
            $handler,
        );

        return $pipeline->handle($this->request);
    }
}
