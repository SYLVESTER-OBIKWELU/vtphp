<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Config\Repository;
use VtPhp\Foundation\Application;

/**
 * Stateless authentication with API tokens sent as
 * "Authorization: Bearer {id}|{secret}" (see HasApiTokens). No session or
 * cookie is involved, so it suits mobile apps and server-to-server clients.
 */
final class TokenGuard implements GuardInterface
{
    private ?object $user = null;

    private bool $resolved = false;

    public function __construct(
        private readonly Application $app,
        private readonly UserProviderInterface $provider,
        private readonly Repository $config,
    ) {
    }

    public function user(): ?object
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $plainText = $this->bearerToken();
        $token = $plainText !== null ? PersonalAccessToken::findToken($plainText) : null;

        if ($token === null || $token->isExpired($this->maxLifetimeMinutes())) {
            return $this->user = null;
        }

        $user = $this->provider->retrieveById($token->user_id);

        if ($user === null) {
            return $this->user = null;
        }

        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($token);
        }

        // Record use at most once a minute, not on every request.
        if ($token->last_used_at === null || $token->last_used_at->getTimestamp() < time() - 60) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $this->user = $user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->id;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Check credentials and treat the user as logged in for this request
     * only (no session); used before issuing a token.
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            TimingSafeCheck::burn((string) ($credentials['password'] ?? ''));

            return false;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(object $user): void
    {
        $this->user = $user;
        $this->resolved = true;
    }

    /**
     * Revoke the token this request used.
     */
    public function logout(): void
    {
        $user = $this->user();

        if ($user !== null && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        $this->user = null;
        $this->resolved = true;
    }

    private function bearerToken(): ?string
    {
        if (!$this->app->has(ServerRequestInterface::class)) {
            return null;
        }

        $header = $this->app->make(ServerRequestInterface::class)->getHeaderLine('Authorization');

        return str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;
    }

    private function maxLifetimeMinutes(): ?int
    {
        $minutes = $this->config->get('auth.tokens.expiration');

        return is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : null;
    }
}
