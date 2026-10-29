<?php

declare(strict_types=1);

namespace VtPhp\Queue;

/**
 * Base class for background jobs, e.g. `App\Jobs\SendInvoice`.
 *
 * Write a handle() method; its parameters are filled in by the container:
 *
 *     public function handle(Mailer $mailer): void { ... }
 *
 * Then queue it with `SendInvoice::dispatch($order)`. Whatever the
 * constructor stores is serialized onto the queue, with Eloquent models
 * reduced to their id and loaded fresh when the job runs.
 */
abstract class Job
{
    use SerializesModels;

    /** How many times to try before giving up. Null = the worker's --tries. */
    public ?int $tries = null;

    /**
     * Seconds to wait before retrying a failed attempt. A list sets each
     * retry separately: [10, 60, 300] waits 10 s, then 60 s, then 300 s.
     *
     * @var int|list<int>
     */
    public int|array $backoff = 0;

    /** The queue name, or null for the connection's default. */
    public ?string $queue = null;

    /** The connection (config/queue.php), or null for the default. */
    public ?string $connection = null;

    /**
     * Queue a new job. The arguments go to the constructor.
     */
    public static function dispatch(mixed ...$arguments): PendingDispatch
    {
        // @phpstan-ignore new.static
        return new PendingDispatch(new static(...$arguments));
    }

    /**
     * Run a new job right now, in this process, skipping the queue.
     */
    public static function dispatchSync(mixed ...$arguments): void
    {
        // @phpstan-ignore new.static
        (new SyncQueue(app()))->push(new static(...$arguments));
    }

    /**
     * Called once, after the last attempt has failed. Override it to clean up
     * or tell someone.
     */
    public function failed(\Throwable $exception): void
    {
    }

    /**
     * The name shown by `queue:work` and `queue:failed`.
     */
    public function displayName(): string
    {
        return static::class;
    }

    /**
     * Seconds to wait before attempt number $attempt + 1.
     */
    public function backoffFor(int $attempt): int
    {
        if (is_int($this->backoff)) {
            return max(0, $this->backoff);
        }

        if ($this->backoff === []) {
            return 0;
        }

        return max(0, $this->backoff[min($attempt, count($this->backoff)) - 1] ?? 0);
    }
}
