<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use Illuminate\Database\Connection;

/**
 * Keeps jobs in the `jobs` table until `php forge queue:work` runs them.
 */
final class DatabaseQueue implements Queue
{
    public function __construct(
        private readonly Connection $db,
        private readonly Payload $payloads,
        private readonly string $table = 'jobs',
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 90,
    ) {
    }

    public function push(Job $job, ?string $queue = null, int $delay = 0): void
    {
        $this->pushRaw($this->payloads->encode($job), $queue, $delay);
    }

    public function pushRaw(string $payload, ?string $queue = null, int $delay = 0): void
    {
        $now = time();

        $this->db->table($this->table)->insert([
            'queue' => $this->queueName($queue),
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now + max(0, $delay),
            'created_at' => $now,
        ]);
    }

    /**
     * Take the next job that is due. A job reserved more than `retry_after`
     * seconds ago is assumed lost (its worker crashed) and is handed out again.
     */
    public function pop(?string $queue = null): ?ReservedJob
    {
        $queue = $this->queueName($queue);

        // A few tries in case another worker grabs the same row first.
        for ($try = 0; $try < 5; ++$try) {
            $now = time();
            $expired = $now - $this->retryAfter;

            $row = $this->db->table($this->table)
                ->where('queue', $queue)
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->whereNull('reserved_at')->where('available_at', '<=', $now))
                    ->orWhere('reserved_at', '<=', $expired))
                ->orderBy('id')
                ->first();

            if ($row === null) {
                return null;
            }

            // Claim it only if nobody else has since: works the same on
            // SQLite, MySQL and PostgreSQL without row locks.
            $claimed = $this->db->table($this->table)
                ->where('id', $row->id)
                ->where('attempts', $row->attempts)
                ->update(['reserved_at' => $now, 'attempts' => (int) $row->attempts + 1]);

            if ($claimed === 1) {
                return new ReservedJob((int) $row->id, $queue, (string) $row->payload, (int) $row->attempts + 1);
            }
        }

        return null;
    }

    public function delete(ReservedJob $job): void
    {
        $this->db->table($this->table)->where('id', $job->id)->delete();
    }

    /**
     * Put a job back to be tried again after $delay seconds.
     */
    public function release(ReservedJob $job, int $delay = 0): void
    {
        $this->db->table($this->table)->where('id', $job->id)->update([
            'reserved_at' => null,
            'available_at' => time() + max(0, $delay),
        ]);
    }

    public function size(?string $queue = null): int
    {
        return $this->db->table($this->table)->where('queue', $this->queueName($queue))->count();
    }

    /**
     * Delete every job waiting on a queue.
     */
    public function clear(?string $queue = null): int
    {
        return $this->db->table($this->table)->where('queue', $this->queueName($queue))->delete();
    }

    private function queueName(?string $queue): string
    {
        return $queue ?? $this->defaultQueue;
    }
}
