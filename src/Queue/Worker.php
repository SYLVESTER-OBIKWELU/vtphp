<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use Psr\Log\LoggerInterface;
use VtPhp\Container\Container;

/**
 * Takes jobs off a database queue and runs them: deletes a job that
 * succeeds, retries one that throws (after its backoff), and moves it to
 * failed_jobs once it has used up its tries.
 */
final class Worker
{
    public function __construct(
        private readonly QueueManager $queues,
        private readonly Container $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Run the next due job from the first of $queues that has one.
     *
     * @param list<string> $queues In priority order.
     * @param int $tries Used when the job doesn't set its own $tries.
     * @return array{job: string, status: 'done'|'released'|'failed', seconds: float}|null Null when there was nothing to do.
     */
    public function runNextJob(string $connection, array $queues, int $tries = 1): ?array
    {
        $queue = $this->queues->connection($connection);

        if (!$queue instanceof DatabaseQueue) {
            throw new \InvalidArgumentException("The [{$connection}] connection runs jobs as soon as they're dispatched, so there is nothing for a worker to do.");
        }

        foreach ($queues as $name) {
            $reserved = $queue->pop($name);

            if ($reserved !== null) {
                return $this->process($connection, $queue, $reserved, $tries);
            }
        }

        return null;
    }

    /**
     * @return array{job: string, status: 'done'|'released'|'failed', seconds: float}
     */
    private function process(string $connection, DatabaseQueue $queue, ReservedJob $reserved, int $tries): array
    {
        $started = microtime(true);
        $name = (string) (Payload::parse($reserved->payload)['displayName'] ?? 'Unknown job');
        $result = fn (string $status): array => ['job' => $name, 'status' => $status, 'seconds' => microtime(true) - $started];
        $job = null;

        try {
            $job = $this->queues->payloads()->decode($reserved->payload);
            $this->container->call([$job, 'handle']);
            $queue->delete($reserved);

            return $result('done');
        } catch (\Throwable $e) {
            // A payload that can't be trusted or rebuilt (e.g. its model was
            // deleted) will never succeed, so don't retry it.
            if ($job !== null && $reserved->attempts < max(1, $job->tries ?? $tries)) {
                $queue->release($reserved, $job->backoffFor($reserved->attempts));

                return $result('released');
            }

            $this->fail($connection, $queue, $reserved, $job, $e, $name);

            return $result('failed');
        }
    }

    private function fail(string $connection, DatabaseQueue $queue, ReservedJob $reserved, ?Job $job, \Throwable $e, string $name): void
    {
        $queue->delete($reserved);
        $this->queues->failedJobs()->log($connection, $reserved->queue, $reserved->payload, $e);

        $this->logger->error("Queued job {$name} failed: ".$e->getMessage(), [
            'exception' => $e::class,
            'attempts' => $reserved->attempts,
            'queue' => $reserved->queue,
        ]);

        try {
            $job?->failed($e);
        } catch (\Throwable $hookError) {
            $this->logger->error("The failed() method of {$name} threw: ".$hookError->getMessage());
        }
    }
}
