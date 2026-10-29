<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Queue\DatabaseQueue;
use VtPhp\Queue\QueueManager;

#[AsCommand(name: 'queue:retry', description: 'Put failed jobs back on the queue')]
final class QueueRetryCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Failed job IDs or UUIDs, or "all"');
    }

    protected function handle(): int
    {
        $queues = $this->app->make(QueueManager::class);
        $failed = $queues->failedJobs();

        /** @var list<string> $ids */
        $ids = (array) $this->input->getArgument('id');

        if ($ids === ['all']) {
            $ids = array_map(static fn (object $job): string => (string) $job->id, $failed->all());
        }

        $status = self::SUCCESS;

        foreach ($ids as $id) {
            $job = $failed->find($id);

            if ($job === null) {
                $this->error("No failed job with ID [{$id}].");
                $status = self::FAILURE;

                continue;
            }

            $queue = $queues->connection($job->connection);

            if (!$queue instanceof DatabaseQueue) {
                $this->error("Job [{$id}] came from the [{$job->connection}] connection, which can't hold jobs.");
                $status = self::FAILURE;

                continue;
            }

            // A fresh start: tries are counted again from zero.
            $queue->pushRaw($job->payload, $job->queue);
            $failed->forget($job->id);
            $this->line("Job [{$id}] is back on the [{$job->queue}] queue.");
        }

        return $status;
    }
}
