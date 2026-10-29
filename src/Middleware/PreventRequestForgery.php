<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;
use VtPhp\Exceptions\HttpException;

/**
 * CSRF protection for cookie-authenticated API calls.
 *
 * A browser can send a cross-site request with the user's cookies and no
 * CORS preflight only as a "simple" request: an HTML form, a text/plain
 * body, or a body-less POST. So state-changing requests (POST, PUT, PATCH,
 * DELETE) that carry the session cookie must either be JSON
 * (Content-Type: application/json) or send an X-Requested-With header.
 * Either one forces a preflight, which CORS refuses for origins that aren't
 * allowed. Requests without the session cookie (server-to-server, first
 * login) carry no ambient credentials and are unaffected.
 */
final class PreventRequestForgery implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Repository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            !$this->config->get('http.csrf.enabled', true)
            || in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)
            || !$this->hasSessionCookie($request)
            || $this->isExcepted($request)
            || $this->cannotBeForged($request)
        ) {
            return $handler->handle($request);
        }

        throw new HttpException(
            403,
            'Cross-site request rejected. Send a JSON body (Content-Type: application/json) or an X-Requested-With header.',
            'CSRF_REJECTED',
        );
    }

    private function hasSessionCookie(ServerRequestInterface $request): bool
    {
        $name = (string) $this->config->get('session.cookie', 'vtphp_session');

        return isset($request->getCookieParams()[$name]);
    }

    private function cannotBeForged(ServerRequestInterface $request): bool
    {
        if ($request->getHeaderLine('X-Requested-With') !== '') {
            return true;
        }

        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));

        return $type === 'application/json' || str_ends_with($type, '+json');
    }

    private function isExcepted(ServerRequestInterface $request): bool
    {
        $path = ltrim($request->getUri()->getPath(), '/');

        /** @var array<int, string> $except */
        $except = (array) $this->config->get('http.csrf.except', []);

        foreach ($except as $pattern) {
            if (preg_match('#^'.str_replace('\*', '.*', preg_quote(ltrim($pattern, '/'), '#')).'$#', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
