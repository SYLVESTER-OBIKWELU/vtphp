<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use VtPhp\Foundation\Application;
use VtPhp\Session\Session;

/**
 * Session-backed authentication guard: stores the authenticated user's id
 * in the current request's Session and re-resolves the user via the
 * bound UserProviderInterface.
 */
final class SessionGuard implements GuardInterface
{
    private const SESSION_KEY = 'auth_user_id';

    private ?object $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly Application $app,
        private readonly UserProviderInterface $provider,
    ) {
    }

    public function user(): ?object
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->session()->get(self::SESSION_KEY);

        return $this->user = ($id !== null) ? $this->provider->retrieveById($id) : null;
    }

    public function id(): int|string|null
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        // The framework layer only knows the resolved user as a generic
        // object; concrete user models (e.g. App\Models\User) always
        // expose an `id` property.
        return $user->id;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(object $user): void
    {
        $this->session()->put(self::SESSION_KEY, $user->id);
        $this->session()->regenerate();
        $this->user = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $this->session()->invalidate();
        $this->user = null;
        $this->resolved = true;
    }

    private function session(): Session
    {
        return $this->app->make(Session::class);
    }
}
