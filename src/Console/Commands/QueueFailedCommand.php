<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Queue\Payload;
use VtPhp\Queue\QueueManager;

#[AsCommand(name: 'queue:failed', description: 'List jobs that failed every try')]
final class QueueFailedCommand extends Command
{
    protected function handle(): int
    {
        $failed = $this->app->make(QueueManager::class)->failedJobs()->all();

        if ($failed === []) {
            $this->info('No failed jobs.');

            return self::SUCCESS;
        }

        $this->io->table(
            ['ID', 'Job', 'Queue', 'Failed at', 'Error'],
            array_map(static fn (object $job): array => [
                $job->id,
                (string) (Payload::parse($job->payload)['displayName'] ?? '?'),
                $job->connection.':'.$job->queue,
                $job->failed_at,
                mb_strimwidth(explode("\n", $job->exception)[0], 0, 80, '...'),
            ], $failed),
        );

        $this->line('Retry with `php forge queue:retry <ID>` (or `all`), or delete with `php forge queue:forget <ID>`.');

        return self::SUCCESS;
    }
}
