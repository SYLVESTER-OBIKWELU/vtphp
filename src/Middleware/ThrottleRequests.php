<?php

declare(strict_types=1);

namespace VtPhp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Auth\AuthManager;
use VtPhp\Cache\RateLimiter;
use VtPhp\Config\Repository;
use VtPhp\Exceptions\TooManyRequestsHttpException;
use VtPhp\Http\Request;

/**
 * Applies a named limiter from config/rate_limiting.php, like Laravel's
 * `throttle:` middleware:
 *
 *     $router->post('/login', [AuthController::class, 'login'], 'auth.login', [ThrottleRequests::using('auth')]);
 *
 * Over the limit, the request fails with 429 TOO_MANY_REQUESTS and a
 * Retry-After header; otherwise X-RateLimit-Limit/-Remaining are added.
 */
final class ThrottleRequests implements ConfigurableMiddleware
{
    private string $name = 'api';

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Repository $config,
    ) {
    }

    public static function using(string $limiter): string
    {
        return self::class.':'.$limiter;
    }

    public function withParameters(array $parameters): static
    {
        $clone = clone $this;
        $clone->name = $parameters[0] ?? 'api';

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $settings = $this->config->get("rate_limiting.limiters.{$this->name}");

        if (!is_array($settings)) {
            throw new \InvalidArgumentException("Rate limiter [{$this->name}] is not defined in config/rate_limiting.php.");
        }

        $max = (int) ($settings['max_attempts'] ?? 60);
        $decay = (int) ($settings['decay_seconds'] ?? 60);
        $key = $this->name.'|'.$this->signature(Request::fromPsr($request), (string) ($settings['by'] ?? 'ip'));

        if ($settings['per_route'] ?? false) {
            // Separate buckets per endpoint, so signing up doesn't use up
            // login attempts.
            $key .= '|'.$request->getMethod().' '.$request->getUri()->getPath();
        }

        $result = $this->limiter->hit($key, $max, $decay);
        $headers = [
            'X-RateLimit-Limit' => (string) $max,
            'X-RateLimit-Remaining' => (string) $result['remaining'],
        ];

        if (!$result['allowed']) {
            throw new TooManyRequestsHttpException(
                "Too many attempts. Please try again in {$result['retry_after']} seconds.",
                $result['retry_after'],
                $headers,
            );
        }

        $response = $handler->handle($request);

        // With nested limiters (group `api` + route `auth`), keep the inner,
        // more specific one's headers: that's the limit the client hits first.
        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * The signed-in user under any configured guard (session or token), so
     * token clients get their own bucket too. This runs before Authenticate.
     */
    private function userId(): ?string
    {
        $auth = app(AuthManager::class);

        foreach ($auth->guardNames() as $guard) {
            if (($id = $auth->guard($guard)->id()) !== null) {
                return $guard.':'.$id;
            }
        }

        return null;
    }

    /**
     * Who the limit applies to:
     * - "ip": the client IP (see TrustProxies);
     * - "user": the signed-in user's id, falling back to the IP for guests;
     * - "ip+email": the IP plus the submitted email, so one attacker can't
     *   lock a victim out from elsewhere and one IP can't hammer many accounts
     *   unnoticed (the per-route `api` limit still caps the IP overall).
     */
    private function signature(Request $request, string $by): string
    {
        $ip = $request->ip() ?? 'unknown';

        return match ($by) {
            'user' => ($id = $this->userId()) !== null ? 'user:'.$id : 'ip:'.$ip,
            'ip+email' => 'ip:'.$ip.'|email:'.mb_strtolower(trim((string) (is_scalar($email = $request->input('email')) ? $email : ''))),
            default => 'ip:'.$ip,
        };
    }
}
