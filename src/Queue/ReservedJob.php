<?php

declare(strict_types=1);

namespace VtPhp\Queue;

/**
 * A job a worker has taken off the database queue. `attempts` already counts
 * the current attempt.
 */
final readonly class ReservedJob
{
    public function __construct(
        public int $id,
        public string $queue,
        public string $payload,
        public int $attempts,
    ) {
    }
}
