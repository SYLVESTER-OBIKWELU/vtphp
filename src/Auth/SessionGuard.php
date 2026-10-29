<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use VtPhp\Foundation\Application;
use VtPhp\Session\Session;

/**
 * Session-backed authentication guard: stores the authenticated user's id
 * in the current request's Session and re-resolves the user via the
 * bound UserProviderInterface.
 *
 * The session also keeps a fingerprint of the user's password hash, so
 * changing or resetting the password ends every other session (like
 * Laravel's AuthenticateSession): their fingerprint no longer matches.
 */
final class SessionGuard implements GuardInterface
{
    private const SESSION_KEY = 'auth_user_id';
    private const PASSWORD_KEY = 'auth_password_hash';

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
        $user = $id !== null ? $this->provider->retrieveById($id) : null;

        if ($user === null) {
            return $this->user = null;
        }

        $stored = $this->session()->get(self::PASSWORD_KEY);
        $current = $this->passwordFingerprint($user);

        if ($stored === null) {
            // Sessions from before fingerprinting: adopt rather than log out.
            $this->session()->put(self::PASSWORD_KEY, $current);
        } elseif (!is_string($stored) || !hash_equals($stored, $current)) {
            $this->session()->invalidate();

            return $this->user = null;
        }

        return $this->user = $user;
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
        $this->session()->put(self::PASSWORD_KEY, $this->passwordFingerprint($user));
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

    /**
     * A digest of the stored password hash (never the hash itself), or a
     * fixed value for users without a password.
     */
    private function passwordFingerprint(object $user): string
    {
        $hash = $user->password ?? null;

        return hash('sha256', is_string($hash) ? $hash : '');
    }

    private function session(): Session
    {
        return $this->app->make(Session::class);
    }
}
