<?php

declare(strict_types=1);

namespace VtPhp\Queue;

/**
 * Returned by Job::dispatch() and dispatch(). Options can be chained, and the
 * job is queued when this object goes away (at the end of the statement):
 *
 *     SendInvoice::dispatch($order)->onQueue('emails')->delay(60);
 */
final class PendingDispatch
{
    private int $delay = 0;

    private bool $dispatched = false;

    public function __construct(private readonly Job $job)
    {
    }

    public function onQueue(string $queue): self
    {
        $this->job->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->job->connection = $connection;

        return $this;
    }

    /**
     * Wait this many seconds (or until this time) before the job may run.
     * Ignored by the sync connection, which always runs jobs straight away.
     */
    public function delay(int|\DateTimeInterface $delay): self
    {
        $this->delay = $delay instanceof \DateTimeInterface
            ? max(0, $delay->getTimestamp() - time())
            : max(0, $delay);

        return $this;
    }

    public function __destruct()
    {
        if (!$this->dispatched) {
            $this->dispatched = true;
            app(QueueManager::class)->connection($this->job->connection)->push($this->job, $this->job->queue, $this->delay);
        }
    }
}
