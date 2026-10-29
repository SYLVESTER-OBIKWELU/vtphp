<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;

/**
 * Resolves the real client IP and scheme behind trusted proxies
 * (`http.trusted_proxies`), so rate limiting, logs and Secure cookies see
 * the client rather than the load balancer.
 *
 * The client IP is stored in the `client_ip` request attribute (read it with
 * $request->ip()). X-Forwarded-For is walked from the right, skipping trusted
 * proxies, so a client can't spoof its IP by prepending fake entries. Headers
 * from untrusted peers are ignored entirely.
 */
final class TrustProxies implements MiddlewareInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remote = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');

        if ($remote === '' || !$this->isTrusted($remote)) {
            return $handler->handle($request->withAttribute('client_ip', $remote !== '' ? $remote : null));
        }

        $proto = strtolower(trim(explode(',', $request->getHeaderLine('X-Forwarded-Proto'))[0]));

        if ($proto === 'https' || $proto === 'http') {
            $port = $request->getHeaderLine('X-Forwarded-Port');
            $uri = $request->getUri()->withScheme($proto)->withPort(ctype_digit($port) ? (int) $port : null);
            $request = $request->withUri($uri, preserveHost: true);
        }

        return $handler->handle($request->withAttribute('client_ip', $this->clientIp($request, $remote)));
    }

    private function clientIp(ServerRequestInterface $request, string $remote): string
    {
        $chain = array_values(array_filter(array_map('trim', explode(',', $request->getHeaderLine('X-Forwarded-For')))));

        foreach (array_reverse($chain) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                break; // A malformed hop: stop trusting anything further left.
            }

            if (!$this->isTrusted($ip)) {
                return $ip;
            }

            $remote = $ip;
        }

        return $remote;
    }

    private function isTrusted(string $ip): bool
    {
        /** @var array<int, string> $proxies */
        $proxies = (array) $this->config->get('http.trusted_proxies', []);

        foreach ($proxies as $proxy) {
            if ($proxy === '*' || self::inRange($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $ip equals $range or falls within it (CIDR, IPv4 or IPv6).
     */
    public static function inRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];

        $ipBytes = @inet_pton($ip);
        $subnetBytes = @inet_pton($subnet);

        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $maxBits = strlen($ipBytes) * 8;
        $bits = $bits === null ? $maxBits : (int) $bits;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($ipBytes, 0, $fullBytes) !== substr($subnetBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($subnetBytes[$fullBytes]) & $mask);
    }
}
