<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use VtPhp\Config\Repository;
use VtPhp\Foundation\Application;

final class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    private ?string $defaultOverride = null;

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
    ) {
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->defaultGuard();

        return $this->guards[$name] ??= $this->resolve($name);
    }

    public function defaultGuard(): string
    {
        return $this->defaultOverride ?? (string) $this->config->get('auth.default.guard', 'web');
    }

    /**
     * Make `$name` the default guard for the rest of this request, so
     * auth()->user() and $request->user() see whoever it authenticated
     * (Authenticate calls this with the guard that matched).
     */
    public function shouldUse(string $name): void
    {
        $this->defaultOverride = $name;
    }

    /**
     * @return array<int, string> The configured guard names.
     */
    public function guardNames(): array
    {
        return array_keys((array) $this->config->get('auth.guards', []));
    }

    private function resolve(string $name): GuardInterface
    {
        /** @var array<string, mixed> $guardConfig */
        $guardConfig = (array) $this->config->get("auth.guards.{$name}");
        $driver = (string) ($guardConfig['driver'] ?? '');

        return match ($driver) {
            'session' => new SessionGuard($this->app, $this->provider((string) ($guardConfig['provider'] ?? ''))),
            'token' => new TokenGuard($this->app, $this->provider((string) ($guardConfig['provider'] ?? '')), $this->config),
            default => throw new \InvalidArgumentException("Unsupported auth driver [{$driver}] for guard [{$name}]."),
        };
    }

    /**
     * Builds the guard's user provider from `auth.providers.{name}`:
     *
     *     'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class]
     *
     * A provider with no config falls back to a container-bound
     * UserProviderInterface, for fully custom lookups.
     */
    private function provider(string $name): UserProviderInterface
    {
        /** @var array<string, mixed> $providerConfig */
        $providerConfig = (array) $this->config->get("auth.providers.{$name}", []);

        if ($providerConfig === []) {
            return $this->app->make(UserProviderInterface::class);
        }

        $driver = (string) ($providerConfig['driver'] ?? '');

        return match ($driver) {
            'eloquent' => new EloquentUserProvider((string) $providerConfig['model']),
            default => throw new \InvalidArgumentException("Unsupported user provider driver [{$driver}] for provider [{$name}]."),
        };
    }
}
