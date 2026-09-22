<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'about', description: 'Display basic information about the application')]
final class AboutCommand extends Command
{
    protected function handle(): int
    {
        $config = $this->app->make('config');

        $this->io->table(['Property', 'Value'], [
            ['Name', (string) $config->get('app.name')],
            ['Environment', (string) $config->get('app.env')],
            ['Debug', $config->get('app.debug') ? 'true' : 'false'],
            ['PHP Version', PHP_VERSION],
            ['Base Path', $this->app->basePath()],
        ]);

        return self::SUCCESS;
    }
}
