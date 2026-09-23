<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Config\Repository;
use VtPhp\Cookie\CookieJar;
use VtPhp\Foundation\Application;
use VtPhp\Session\Session;
use VtPhp\Session\SessionManager;

/**
 * Starts (or resumes) the session for the current request, binds it into
 * the container so `session()`/`auth()` can access it, saves it after the
 * response is produced, and queues the session cookie via CookieJar.
 */
final class StartSession implements MiddlewareInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly SessionManager $sessions,
        private readonly CookieJar $cookies,
        private readonly Repository $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieName = (string) $this->config->get('session.cookie', 'vtphp_session');
        $existingId = $request->getCookieParams()[$cookieName] ?? null;

        $session = $this->sessions->start(is_string($existingId) ? $existingId : null);
        $this->app->instance(Session::class, $session);

        $response = $handler->handle($request->withAttribute('session', $session));

        $session->save();

        $domain = $this->config->get('session.domain');

        $this->cookies->queue($this->cookies->make(
            name: $cookieName,
            value: $session->id(),
            minutes: (int) $this->config->get('session.lifetime', 120),
            path: (string) $this->config->get('session.path', '/'),
            domain: is_string($domain) ? $domain : null,
            secure: (bool) $this->config->get('session.secure', false),
            httpOnly: true,
            sameSite: (string) $this->config->get('session.same_site', 'lax'),
        ));

        return $response;
    }
}
