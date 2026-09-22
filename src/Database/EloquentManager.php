<?php

declare(strict_types=1);

namespace VtPhp\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use VtPhp\Config\Repository;

/**
 * Boots Eloquent (via the standalone Capsule manager) using the same
 * `database.connections.*` config consumed by the Doctrine-backed
 * DatabaseManager/migration system, translating driver names/keys as needed.
 */
final class EloquentManager
{
    private Capsule $capsule;

    public function __construct(private readonly Repository $config)
    {
        $this->capsule = new Capsule();
    }

    public function boot(): void
    {
        $name = (string) $this->config->get('database.default');

        $this->capsule->addConnection($this->connectionConfig($name), $name);
        $this->capsule->getDatabaseManager()->setDefaultConnection($name);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(string $name): array
    {
        /** @var array<string, mixed> $doctrine */
        $doctrine = (array) $this->config->get("database.connections.{$name}");
        $driver = (string) ($doctrine['driver'] ?? '');

        return match ($driver) {
            'pdo_sqlite' => [
                'driver' => 'sqlite',
                'database' => $doctrine['path'],
                'prefix' => '',
            ],
            'pdo_pgsql' => [
                'driver' => 'pgsql',
                'host' => $doctrine['host'],
                'port' => $doctrine['port'],
                'database' => $doctrine['dbname'],
                'username' => $doctrine['user'],
                'password' => $doctrine['password'],
                'charset' => $doctrine['charset'] ?? 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ],
            default => [
                'driver' => 'mysql',
                'host' => $doctrine['host'],
                'port' => $doctrine['port'],
                'database' => $doctrine['dbname'],
                'username' => $doctrine['user'],
                'password' => $doctrine['password'],
                'charset' => $doctrine['charset'] ?? 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
        };
    }
}
