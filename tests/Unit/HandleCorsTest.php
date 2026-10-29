<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\HandleCors;

final class HandleCorsTest extends TestCase
{
    private bool $handlerCalled = false;

    /**
     * @param array<string, mixed> $overrides
     */
    private function cors(array $overrides = []): HandleCors
    {
        return new HandleCors(new Repository(['cors' => array_merge([
            'enabled' => true,
            'paths' => ['api/*'],
            'allowed_origins' => ['https://app.example.com', 'http://localhost:3000/'],
            'allowed_methods' => ['*'],
            'allowed_headers' => ['*'],
            'exposed_headers' => ['X-Request-ID'],
            'max_age' => 0,
            'supports_credentials' => false,
        ], $overrides)]));
    }

    /**
     * @param array<string, string> $headers
     */
    private function dispatch(HandleCors $cors, string $method, string $path, array $headers = []): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest($method, 'http://api.test'.$path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->handlerCalled = false;

        return $cors->process($request, new class ($this->handlerCalled) implements RequestHandlerInterface {
            public function __construct(private bool &$called)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new JsonResponse(['ok' => true]);
            }
        });
    }

    public function test_an_allowed_origin_gets_cors_headers(): void
    {
        $response = $this->dispatch($this->cors(), 'GET', '/api/v1/users', ['Origin' => 'https://app.example.com']);

        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('X-Request-ID', $response->getHeaderLine('Access-Control-Expose-Headers'));
        $this->assertSame('Origin', $response->getHeaderLine('Vary'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertTrue($this->handlerCalled);
    }

    public function test_a_disallowed_origin_gets_no_allow_origin_header(): void
    {
        $response = $this->dispatch($this->cors(), 'GET', '/api/v1/users', ['Origin' => 'https://evil.example.org']);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->getHeaderLine('Vary'), 'Caches must still vary by origin.');
        $this->assertTrue($this->handlerCalled, 'The request still runs; the browser hides the response.');
    }

    public function test_nothing_happens_when_disabled_off_path_or_without_an_origin(): void
    {
        $origin = ['Origin' => 'https://app.example.com'];

        $this->assertFalse($this->dispatch($this->cors(['enabled' => false]), 'GET', '/api/v1/users', $origin)->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($this->dispatch($this->cors(), 'GET', '/health', $origin)->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($this->dispatch($this->cors(), 'GET', '/api/v1/users')->hasHeader('Vary'));
    }

    public function test_preflight_is_answered_without_running_the_app(): void
    {
        $response = $this->dispatch($this->cors(['max_age' => 600]), 'OPTIONS', '/api/v1/users', [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'PATCH',
            'Access-Control-Request-Headers' => 'Content-Type, X-Requested-With',
        ]);

        $this->assertFalse($this->handlerCalled);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'), 'A trailing slash in config still matches.');
        $this->assertSame('PATCH', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type, X-Requested-With', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function test_preflight_lists_configured_methods_and_headers(): void
    {
        $response = $this->dispatch($this->cors(['allowed_methods' => ['GET', 'POST'], 'allowed_headers' => ['Content-Type']]), 'OPTIONS', '/api/v1/users', [
            'Origin' => 'https://app.example.com',
            'Access-Control-Request-Method' => 'DELETE',
        ]);

        $this->assertSame('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertFalse($response->hasHeader('Access-Control-Max-Age'));
    }

    public function test_preflight_from_a_disallowed_origin_gets_no_cors_headers(): void
    {
        $response = $this->dispatch($this->cors(), 'OPTIONS', '/api/v1/users', [
            'Origin' => 'https://evil.example.org',
            'Access-Control-Request-Method' => 'DELETE',
        ]);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertFalse($this->handlerCalled);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function wildcardOrigins(): iterable
    {
        yield 'subdomain' => ['https://admin.example.com', true];
        yield 'nested subdomain' => ['https://eu.admin.example.com', true];
        yield 'bare domain' => ['https://example.com', false];
        yield 'look-alike domain' => ['https://evil-example.com', false];
        yield 'suffix attack' => ['https://admin.example.com.evil.org', false];
        yield 'wrong scheme' => ['http://admin.example.com', false];
    }

    #[DataProvider('wildcardOrigins')]
    public function test_wildcard_subdomain_origins(string $origin, bool $allowed): void
    {
        $response = $this->dispatch($this->cors(['allowed_origins' => ['https://*.example.com']]), 'GET', '/api/x', ['Origin' => $origin]);

        $this->assertSame($allowed, $response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_any_origin_and_credentials(): void
    {
        $any = $this->dispatch($this->cors(['allowed_origins' => ['*']]), 'GET', '/api/x', ['Origin' => 'https://anyone.test']);
        $this->assertSame('*', $any->getHeaderLine('Access-Control-Allow-Origin'));

        $withCredentials = $this->dispatch($this->cors(['allowed_origins' => ['*'], 'supports_credentials' => true]), 'GET', '/api/x', ['Origin' => 'https://anyone.test']);
        $this->assertSame('https://anyone.test', $withCredentials->getHeaderLine('Access-Control-Allow-Origin'), 'Browsers reject * with credentials.');
        $this->assertSame('true', $withCredentials->getHeaderLine('Access-Control-Allow-Credentials'));
    }
}
