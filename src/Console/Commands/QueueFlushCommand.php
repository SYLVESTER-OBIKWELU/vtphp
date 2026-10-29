<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Queue\QueueManager;

#[AsCommand(name: 'queue:flush', description: 'Delete all failed jobs')]
final class QueueFlushCommand extends Command
{
    protected bool $destructive = true;

    protected function handle(): int
    {
        $deleted = $this->app->make(QueueManager::class)->failedJobs()->flush();

        $this->info("Deleted {$deleted} failed job(s).");

        return self::SUCCESS;
    }
}
