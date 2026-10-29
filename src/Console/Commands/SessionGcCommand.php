<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Session\SessionManager;

/**
 * Deletes expired sessions now, e.g. from cron if you set the
 * `session.lottery` chance to 0.
 */
#[AsCommand(name: 'session:gc', description: 'Delete expired sessions')]
final class SessionGcCommand extends Command
{
    protected function handle(): int
    {
        $config = $this->app->make('config');

        $this->app->make(SessionManager::class)->store()->gc((int) $config->get('session.lifetime', 120) * 60);

        $this->info('Expired sessions deleted from the ['.$config->get('session.driver').'] store.');

        return self::SUCCESS;
    }
}
