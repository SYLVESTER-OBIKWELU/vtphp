<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;

/**
 * Adds the defensive headers from `http.security_headers` to every response
 * (including errors and CORS preflights), plus Strict-Transport-Security on
 * HTTPS requests. Headers the response already sets are left untouched, so
 * a controller can override any of them.
 */
final class AddSecurityHeaders implements MiddlewareInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /** @var array<string, string|null> $headers */
        $headers = (array) $this->config->get('http.security_headers', []);

        if ($request->getUri()->getScheme() === 'https' && $this->config->get('http.hsts.enabled', true)) {
            $hsts = 'max-age='.(int) $this->config->get('http.hsts.max_age', 31536000);
            $headers['Strict-Transport-Security'] = $this->config->get('http.hsts.include_subdomains', false)
                ? $hsts.'; includeSubDomains'
                : $hsts;
        }

        foreach ($headers as $name => $value) {
            if ($value !== null && $value !== '' && !$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
