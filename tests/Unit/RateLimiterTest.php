<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Cache\CacheManager;
use VtPhp\Cache\RateLimiter;
use VtPhp\Config\Repository;
use VtPhp\Container\Container;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Exceptions\TooManyRequestsHttpException;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\Pipeline;
use VtPhp\Middleware\RequestIdMiddleware;
use VtPhp\Tests\Support\TestLogger;

final class RateLimiterTest extends TestCase
{
    private function limiter(): RateLimiter
    {
        $config = new Repository(['cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]]);

        return new RateLimiter(new CacheManager($config), $config);
    }

    public function test_hits_count_down_and_then_block(): void
    {
        $limiter = $this->limiter();

        $first = $limiter->hit('login|x', 2, 60);
        $this->assertSame(['allowed' => true, 'remaining' => 1], array_intersect_key($first, ['allowed' => 1, 'remaining' => 1]));
        $this->assertGreaterThanOrEqual(1, $first['retry_after']);
        $this->assertLessThanOrEqual(60, $first['retry_after']);

        $this->assertTrue($limiter->hit('login|x', 2, 60)['allowed']);
        $third = $limiter->hit('login|x', 2, 60);
        $this->assertFalse($third['allowed']);
        $this->assertSame(0, $third['remaining']);
        $this->assertSame(3, $limiter->attempts('login|x', 60));

        $this->assertTrue($limiter->hit('login|y', 2, 60)['allowed'], 'Keys are independent.');
    }

    public function test_clear_resets_the_current_window(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('k', 1, 60);
        $limiter->hit('k', 1, 60);

        $limiter->clear('k', 60);

        $this->assertSame(0, $limiter->attempts('k', 60));
        $this->assertTrue($limiter->hit('k', 1, 60)['allowed']);
    }

    public function test_429_responses_carry_retry_after_and_custom_headers(): void
    {
        $response = (new ExceptionHandler(new TestLogger(), false))->render(new TooManyRequestsHttpException('Slow down.', 42, ['X-RateLimit-Limit' => '5']));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('42', $response->getHeaderLine('Retry-After'));
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame(42, json_decode((string) $response->getBody(), true)['error']['retry_after']);
    }

    public function test_parameters_are_rejected_by_middleware_that_does_not_accept_them(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept parameters');

        (new Pipeline(new Container()))
            ->send((new Psr17Factory())->createServerRequest('GET', '/'))
            ->through([RequestIdMiddleware::class.':oops'])
            ->then(fn (ServerRequestInterface $r): ResponseInterface => new JsonResponse([]));
    }
}
