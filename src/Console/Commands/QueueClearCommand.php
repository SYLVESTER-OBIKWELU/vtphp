<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;
use VtPhp\Queue\DatabaseQueue;
use VtPhp\Queue\QueueManager;

#[AsCommand(name: 'queue:clear', description: 'Delete every job waiting on a queue')]
final class QueueClearCommand extends Command
{
    protected bool $destructive = true;

    protected function configure(): void
    {
        $this->addArgument('connection', InputArgument::OPTIONAL, 'The connection (defaults to queue.default)');
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, "The queue to clear (defaults to the connection's queue)");
    }

    protected function handle(): int
    {
        $name = $this->input->getArgument('connection');
        $queue = $this->app->make(QueueManager::class)->connection(is_string($name) ? $name : null);
        $queueName = $this->input->getOption('queue');

        if (!$queue instanceof DatabaseQueue) {
            $this->error('This connection runs jobs immediately, so there is nothing to clear.');

            return self::FAILURE;
        }

        $deleted = $queue->clear(is_string($queueName) ? $queueName : null);
        $this->info("Deleted {$deleted} waiting job(s).");

        return self::SUCCESS;
    }
}
