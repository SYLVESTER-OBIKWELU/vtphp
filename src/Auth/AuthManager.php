<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use VtPhp\Config\Repository;
use VtPhp\Foundation\Application;

final class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
    ) {
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= (string) $this->config->get('auth.default.guard', 'web');

        return $this->guards[$name] ??= $this->resolve($name);
    }

    private function resolve(string $name): GuardInterface
    {
        /** @var array<string, mixed> $guardConfig */
        $guardConfig = (array) $this->config->get("auth.guards.{$name}");
        $driver = (string) ($guardConfig['driver'] ?? '');

        return match ($driver) {
            'session' => new SessionGuard($this->app, $this->app->make(UserProviderInterface::class)),
            default => throw new \InvalidArgumentException("Unsupported auth driver [{$driver}] for guard [{$name}]."),
        };
    }
}
