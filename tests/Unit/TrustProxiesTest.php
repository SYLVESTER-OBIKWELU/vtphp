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
use VtPhp\Http\Request;
use VtPhp\Middleware\TrustProxies;

final class TrustProxiesTest extends TestCase
{
    /**
     * @param array<int, string> $trusted
     * @param array<string, string> $headers
     */
    private function seen(array $trusted, string $remote, array $headers = []): Request
    {
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://api.example.com/x', ['REMOTE_ADDR' => $remote]);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $captured = null;
        (new TrustProxies(new Repository(['http' => ['trusted_proxies' => $trusted]])))->process($request, new class ($captured) implements RequestHandlerInterface {
            public function __construct(private ?ServerRequestInterface &$captured)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return new JsonResponse([]);
            }
        });

        return Request::fromPsr($captured ?? throw new \LogicException('Handler not called.'));
    }

    public function test_an_untrusted_peer_cannot_spoof_ip_or_scheme(): void
    {
        $request = $this->seen(['10.0.0.1'], '203.0.113.9', ['X-Forwarded-For' => '1.2.3.4', 'X-Forwarded-Proto' => 'https']);

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertFalse($request->isSecure());
    }

    public function test_a_trusted_proxy_supplies_client_ip_and_https(): void
    {
        $request = $this->seen(['10.0.0.0/8'], '10.1.2.3', ['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443']);

        $this->assertSame('198.51.100.7', $request->ip());
        $this->assertTrue($request->isSecure());
        $this->assertSame('api.example.com', $request->psr()->getUri()->getHost());
    }

    public function test_prepended_fake_entries_are_ignored(): void
    {
        // The client sent "X-Forwarded-For: 6.6.6.6"; the proxy appended the
        // real peer. The rightmost untrusted address is the real client.
        $request = $this->seen(['10.0.0.0/8'], '10.0.0.2', ['X-Forwarded-For' => '6.6.6.6, 198.51.100.7, 10.0.0.5']);

        $this->assertSame('198.51.100.7', $request->ip());
    }

    public function test_a_malformed_hop_stops_the_walk(): void
    {
        $request = $this->seen(['10.0.0.0/8'], '10.0.0.2', ['X-Forwarded-For' => 'garbage, 10.0.0.5']);

        $this->assertSame('10.0.0.5', $request->ip());
    }

    public function test_wildcard_trusts_any_peer(): void
    {
        $this->assertSame('198.51.100.7', $this->seen(['*'], '172.16.0.1', ['X-Forwarded-For' => '198.51.100.7'])->ip());
    }

    public function test_no_trusted_proxies_and_no_peer(): void
    {
        $this->assertSame('203.0.113.9', $this->seen([], '203.0.113.9')->ip());
        $this->assertNull($this->seen([], '')->ip());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function ranges(): iterable
    {
        yield 'exact v4' => ['192.168.1.10', '192.168.1.10', true];
        yield 'v4 /24 in' => ['192.168.1.200', '192.168.1.0/24', true];
        yield 'v4 /24 out' => ['192.168.2.1', '192.168.1.0/24', false];
        yield 'v4 /20 boundary in' => ['10.0.15.255', '10.0.0.0/20', true];
        yield 'v4 /20 boundary out' => ['10.0.16.0', '10.0.0.0/20', false];
        yield 'v4 /0' => ['8.8.8.8', '0.0.0.0/0', true];
        yield 'v6 /64 in' => ['2001:db8::1', '2001:db8::/64', true];
        yield 'v6 /64 out' => ['2001:db9::1', '2001:db8::/64', false];
        yield 'v4 vs v6' => ['10.0.0.1', '::/0', false];
        yield 'garbage' => ['not-an-ip', '10.0.0.0/8', false];
        yield 'bad prefix' => ['10.0.0.1', '10.0.0.0/33', false];
    }

    #[DataProvider('ranges')]
    public function test_in_range(string $ip, string $range, bool $expected): void
    {
        $this->assertSame($expected, TrustProxies::inRange($ip, $range));
    }
}
