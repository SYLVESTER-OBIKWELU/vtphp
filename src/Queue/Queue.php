<?php

declare(strict_types=1);

namespace VtPhp\Queue;

interface Queue
{
    /**
     * @param int $delay Seconds before the job may run.
     */
    public function push(Job $job, ?string $queue = null, int $delay = 0): void;

    /**
     * The number of jobs waiting (or running) on a queue.
     */
    public function size(?string $queue = null): int;
}
