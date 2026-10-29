<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Container\Container;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\Pipeline;

final class ContainerResolvedMiddleware implements MiddlewareInterface
{
    public function __construct(public FakeDependency $dependency)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute('resolved', true));
    }
}

final class PipelineTest extends TestCase
{
    private function request(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', 'http://localhost/');
    }

    /**
     * @param list<string> $log
     */
    private function recorder(string $name, array &$log): MiddlewareInterface
    {
        return new class ($name, $log) implements MiddlewareInterface {
            /** @param list<string> $log */
            public function __construct(private string $name, private array &$log)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->log[] = "{$this->name}:before";
                $response = $handler->handle($request);
                $this->log[] = "{$this->name}:after";

                return $response;
            }
        };
    }

    public function test_middleware_wraps_the_destination_like_an_onion(): void
    {
        $log = [];

        (new Pipeline(new Container()))
            ->send($this->request())
            ->through([$this->recorder('a', $log), $this->recorder('b', $log)])
            ->then(function () use (&$log): ResponseInterface {
                $log[] = 'destination';

                return new JsonResponse([]);
            });

        $this->assertSame(['a:before', 'b:before', 'destination', 'b:after', 'a:after'], $log);
    }

    public function test_middleware_can_short_circuit(): void
    {
        $reached = false;
        $blocker = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new JsonResponse(['blocked' => true], 403);
            }
        };

        $response = (new Pipeline(new Container()))
            ->send($this->request())
            ->through([$blocker])
            ->then(function () use (&$reached): ResponseInterface {
                $reached = true;

                return new JsonResponse([]);
            });

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($reached);
    }

    public function test_class_names_are_resolved_through_the_container(): void
    {
        $response = (new Pipeline(new Container()))
            ->send($this->request())
            ->through([ContainerResolvedMiddleware::class])
            ->then(fn (ServerRequestInterface $request): ResponseInterface => new JsonResponse(['resolved' => $request->getAttribute('resolved')]));

        $this->assertSame('{"resolved":true}', (string) $response->getBody());
    }

    public function test_an_empty_pipeline_calls_the_destination_directly(): void
    {
        $response = (new Pipeline(new Container()))
            ->send($this->request())
            ->through([])
            ->then(fn (): ResponseInterface => new JsonResponse(['ok' => true]));

        $this->assertSame('{"ok":true}', (string) $response->getBody());
    }
}
