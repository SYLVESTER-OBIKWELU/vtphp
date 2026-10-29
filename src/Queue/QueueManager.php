<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use VtPhp\Config\Repository;
use VtPhp\Container\Container;
use VtPhp\Database\EloquentManager;
use VtPhp\Support\AppKey;

/**
 * Builds the queue connections in config/queue.php: "sync" (run now) and
 * "database" (store in the jobs table for a worker).
 */
final class QueueManager
{
    /** @var array<string, Queue> */
    private array $connections = [];

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {
    }

    public function connection(?string $name = null): Queue
    {
        $name ??= $this->defaultConnection();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    public function defaultConnection(): string
    {
        return (string) $this->config->get('queue.default', 'sync');
    }

    /**
     * Queue a job on its own connection and queue (or the defaults).
     */
    public function push(Job $job, int $delay = 0): void
    {
        $this->connection($job->connection)->push($job, $job->queue, $delay);
    }

    public function failedJobs(): FailedJobs
    {
        return new FailedJobs(
            $this->container->make(EloquentManager::class)->connection(),
            (string) $this->config->get('queue.failed.table', 'failed_jobs'),
        );
    }

    public function payloads(): Payload
    {
        return new Payload(AppKey::bytes($this->config));
    }

    private function resolve(string $name): Queue
    {
        $settings = $this->config->get("queue.connections.{$name}");

        if (!is_array($settings)) {
            throw new \InvalidArgumentException("Queue connection [{$name}] is not defined in config/queue.php.");
        }

        return match ($settings['driver'] ?? null) {
            'sync' => new SyncQueue($this->container),
            'database' => new DatabaseQueue(
                $this->container->make(EloquentManager::class)->connection(),
                $this->payloads(),
                (string) ($settings['table'] ?? 'jobs'),
                (string) ($settings['queue'] ?? 'default'),
                (int) ($settings['retry_after'] ?? 90),
            ),
            default => throw new \InvalidArgumentException(
                "Queue driver [".(string) ($settings['driver'] ?? '')."] for connection [{$name}] is not supported. Use \"sync\" or \"database\".",
            ),
        };
    }
}
