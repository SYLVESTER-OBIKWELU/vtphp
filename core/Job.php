<?php

namespace Core;

/**
 * Job Base Class
 * Base class for queueable jobs
 */
abstract class Job
{
    /**
     * The queue connection to use
     */
    public $connection = null;

    /**
     * The queue to push the job to
     */
    public $queue = 'default';

    /**
     * Number of times to retry
     */
    public $tries = 3;

    /**
     * Number of seconds before retry
     */
    public $retryAfter = 90;

    /**
     * Job data
     */
    protected $data;

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Execute the job - Must be implemented by child classes
     */
    abstract public function handle();

    /**
     * Dispatch job to queue
     */
    public static function dispatch(...$args)
    {
        $job = new static(...$args);
        return Queue::dispatch($job, $job->data);
    }

    /**
     * Dispatch job after delay
     */
    public static function dispatchAfter($delay, ...$args)
    {
        $job = new static(...$args);
        // Implementation for delayed dispatch
        return Queue::dispatch($job, $job->data);
    }

    /**
     * Dispatch job synchronously
     */
    public static function dispatchSync(...$args)
    {
        $job = new static(...$args);
        return $job->handle();
    }

    /**
     * Called when job fails
     */
    public function failed(\Exception $exception)
    {
        Log::error("Job failed: " . get_class($this), [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get job data
     */
    public function getData($key = null, $default = null)
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? $default;
    }
}
