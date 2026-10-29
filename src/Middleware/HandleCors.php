<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;

/**
 * Applies the CORS policy from config/cors.php (like Laravel's HandleCors).
 *
 * - Disabled, outside `paths`, or with no Origin header: does nothing.
 * - Preflight (OPTIONS + Access-Control-Request-Method): answered here with
 *   204, without routing, sessions or controllers.
 * - Other requests: CORS headers are added to the response, including error
 *   responses, so browser code can read API errors.
 *
 * Origins not in the policy get no Access-Control-Allow-Origin header, so
 * the browser blocks the response.
 */
final class HandleCors implements MiddlewareInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if (!$this->config->get('cors.enabled', false) || $origin === '' || !$this->pathMatches($request)) {
            return $handler->handle($request);
        }

        if ($request->getMethod() === 'OPTIONS' && $request->hasHeader('Access-Control-Request-Method')) {
            return $this->preflight($request, $origin);
        }

        return $this->addCorsHeaders($handler->handle($request), $origin);
    }

    private function preflight(ServerRequestInterface $request, string $origin): ResponseInterface
    {
        $response = (new Psr17Factory())->createResponse(204)->withHeader('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');

        $allowOrigin = $this->allowOriginHeader($origin);

        if ($allowOrigin === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowed(
                'allowed_methods',
                $request->getHeaderLine('Access-Control-Request-Method'),
            ))
            ->withHeader('Access-Control-Allow-Headers', $this->allowed(
                'allowed_headers',
                $request->getHeaderLine('Access-Control-Request-Headers'),
            ));

        $maxAge = (int) $this->config->get('cors.max_age', 0);

        if ($maxAge > 0) {
            $response = $response->withHeader('Access-Control-Max-Age', (string) $maxAge);
        }

        return $this->withCredentials($response);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $response = $response->withAddedHeader('Vary', 'Origin');
        $allowOrigin = $this->allowOriginHeader($origin);

        if ($allowOrigin === null) {
            return $response;
        }

        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);

        /** @var array<int, string> $exposed */
        $exposed = (array) $this->config->get('cors.exposed_headers', []);

        if ($exposed !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $exposed));
        }

        return $this->withCredentials($response);
    }

    /**
     * The Access-Control-Allow-Origin value for this origin, or null if the
     * policy doesn't allow it.
     */
    private function allowOriginHeader(string $origin): ?string
    {
        /** @var array<int, string> $allowed */
        $allowed = (array) $this->config->get('cors.allowed_origins', []);
        $credentials = (bool) $this->config->get('cors.supports_credentials', false);

        if (in_array('*', $allowed, true)) {
            // Browsers reject `*` with credentials, so echo the origin instead.
            return $credentials ? $origin : '*';
        }

        $normalized = strtolower(rtrim($origin, '/'));

        foreach ($allowed as $candidate) {
            $candidate = strtolower(rtrim(trim($candidate), '/'));

            if ($candidate === $normalized) {
                return $origin;
            }

            // 'https://*.example.com' matches any (nested) subdomain, but not
            // the bare domain or look-alikes such as https://evil-example.com.
            if (str_contains($candidate, '*')) {
                $pattern = '#^'.str_replace('\*', '[a-z0-9-]+(?:\.[a-z0-9-]+)*', preg_quote($candidate, '#')).'$#';

                if (preg_match($pattern, $normalized) === 1) {
                    return $origin;
                }
            }
        }

        return null;
    }

    /**
     * The configured list, or, for ['*'], exactly what the browser asked for.
     */
    private function allowed(string $key, string $requested): string
    {
        /** @var array<int, string> $configured */
        $configured = (array) $this->config->get("cors.{$key}", []);

        return in_array('*', $configured, true) ? $requested : implode(', ', $configured);
    }

    private function withCredentials(ResponseInterface $response): ResponseInterface
    {
        return $this->config->get('cors.supports_credentials', false)
            ? $response->withHeader('Access-Control-Allow-Credentials', 'true')
            : $response;
    }

    private function pathMatches(ServerRequestInterface $request): bool
    {
        $path = ltrim($request->getUri()->getPath(), '/');

        /** @var array<int, string> $paths */
        $paths = (array) $this->config->get('cors.paths', []);

        foreach ($paths as $pattern) {
            $pattern = ltrim($pattern, '/');

            if ($pattern === '*' || preg_match('#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
