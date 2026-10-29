<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use VtPhp\Container\Container;

/**
 * Runs each job immediately, in the same request. Handy in development and
 * tests: no worker needed. The job still goes through serialize/unserialize,
 * so a job that can't be queued fails here too, not only in production.
 */
final class SyncQueue implements Queue
{
    public function __construct(private readonly Container $container)
    {
    }

    public function push(Job $job, ?string $queue = null, int $delay = 0): void
    {
        /** @var Job $job */
        $job = unserialize(serialize($job));

        try {
            $this->container->call([$job, 'handle']);
        } catch (\Throwable $e) {
            $job->failed($e);

            throw $e;
        }
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }
}
