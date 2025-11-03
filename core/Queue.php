<?php

namespace Core;

/**
 * Queue System
 * Simple queue implementation with sync and database drivers
 */
class Queue
{
    protected $driver;
    protected $connection;

    public function __construct($driver = null)
    {
        $config = require __DIR__ . '/../config/queue.php';
        $this->driver = $driver ?? $config['default'];
        $this->connection = $config['connections'][$this->driver] ?? [];
    }

    /**
     * Push a new job onto the queue
     */
    public function push($job, $data = [], $queue = 'default')
    {
        if ($this->driver === 'sync') {
            return $this->processJob($job, $data);
        }

        if ($this->driver === 'database') {
            return $this->pushToDatabase($job, $data, $queue);
        }

        if ($this->driver === 'redis') {
            return $this->pushToRedis($job, $data, $queue);
        }

        throw new \Exception("Unsupported queue driver: {$this->driver}");
    }

    /**
     * Push to database queue
     */
    protected function pushToDatabase($job, $data, $queue)
    {
        $payload = json_encode([
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
        ]);

        $db = Database::getInstance();
        $db->query(
            "INSERT INTO jobs (queue, payload, available_at, created_at) VALUES (?, ?, ?, ?)",
            [$queue, $payload, time(), time()]
        );

        return true;
    }

    /**
     * Push to Redis queue
     */
    protected function pushToRedis($job, $data, $queue)
    {
        $payload = json_encode([
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
        ]);

        $redis = Cache::getRedis();
        $redis->rpush("queue:{$queue}", $payload);

        return true;
    }

    /**
     * Process job immediately (sync)
     */
    protected function processJob($job, $data)
    {
        if (is_string($job) && class_exists($job)) {
            $instance = new $job($data);
            if (method_exists($instance, 'handle')) {
                return $instance->handle();
            }
        }

        if (is_callable($job)) {
            return call_user_func($job, $data);
        }

        throw new \Exception("Invalid job format");
    }

    /**
     * Work on the queue
     */
    public function work($queue = 'default', $maxJobs = null)
    {
        $processed = 0;

        while ($maxJobs === null || $processed < $maxJobs) {
            $job = $this->getNextJob($queue);

            if (!$job) {
                sleep(1);
                continue;
            }

            try {
                $this->processJob($job['job'], $job['data']);
                $this->deleteJob($job['id']);
                $processed++;
            } catch (\Exception $e) {
                $this->failJob($job['id'], $e);
            }
        }
    }

    /**
     * Get next job from queue
     */
    protected function getNextJob($queue)
    {
        if ($this->driver === 'database') {
            $db = Database::getInstance();
            $result = $db->query(
                "SELECT * FROM jobs WHERE queue = ? AND available_at <= ? ORDER BY id LIMIT 1",
                [$queue, time()]
            );

            if ($result && count($result) > 0) {
                $job = $result[0];
                $payload = json_decode($job['payload'], true);
                return array_merge($payload, ['id' => $job['id']]);
            }
        }

        return null;
    }

    /**
     * Delete completed job
     */
    protected function deleteJob($id)
    {
        if ($this->driver === 'database') {
            $db = Database::getInstance();
            $db->query("DELETE FROM jobs WHERE id = ?", [$id]);
        }
    }

    /**
     * Mark job as failed
     */
    protected function failJob($id, $exception)
    {
        if ($this->driver === 'database') {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO failed_jobs (queue, payload, exception, failed_at) 
                 SELECT queue, payload, ?, ? FROM jobs WHERE id = ?",
                [$exception->getMessage(), time(), $id]
            );
            $this->deleteJob($id);
        }
    }

    /**
     * Static factory method
     */
    public static function make($driver = null)
    {
        return new static($driver);
    }

    /**
     * Dispatch a job
     */
    public static function dispatch($job, $data = [])
    {
        return static::make()->push($job, $data);
    }
}
