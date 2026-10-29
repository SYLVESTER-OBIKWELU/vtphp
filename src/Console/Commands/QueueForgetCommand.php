<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Queue\QueueManager;

#[AsCommand(name: 'queue:forget', description: 'Delete one failed job')]
final class QueueForgetCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'The failed job ID or UUID');
    }

    protected function handle(): int
    {
        $id = (string) $this->input->getArgument('id');

        if (!$this->app->make(QueueManager::class)->failedJobs()->forget($id)) {
            $this->error("No failed job with ID [{$id}].");

            return self::FAILURE;
        }

        $this->info("Failed job [{$id}] deleted.");

        return self::SUCCESS;
    }
}
