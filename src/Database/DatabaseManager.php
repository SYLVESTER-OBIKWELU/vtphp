<?php

declare(strict_types=1);

namespace VtPhp\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use VtPhp\Config\Repository;

final class DatabaseManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(private readonly Repository $config)
    {
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= (string) $this->config->get('database.default');

        if (!isset($this->connections[$name])) {
            $params = $this->config->get("database.connections.{$name}");

            if ($params === null) {
                throw new \InvalidArgumentException("Database connection [{$name}] is not configured.");
            }

            $this->connections[$name] = DriverManager::getConnection($params);
        }

        return $this->connections[$name];
    }

    public function transaction(\Closure $callback, ?string $name = null): mixed
    {
        return $this->connection($name)->transactional($callback);
    }
}
