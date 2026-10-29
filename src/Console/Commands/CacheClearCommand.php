<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Cache\CacheManager;
use VtPhp\Console\Command;

#[AsCommand(name: 'cache:clear', description: 'Flush the application cache')]
final class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('store', InputArgument::OPTIONAL, 'The store to flush (defaults to cache.default)');
    }

    protected function handle(): int
    {
        $store = $this->input->getArgument('store');
        $name = is_string($store) ? $store : (string) $this->app->make('config')->get('cache.default');

        if (!$this->app->make(CacheManager::class)->store($name)->clear()) {
            $this->error("Failed to clear the [{$name}] cache store.");

            return self::FAILURE;
        }

        $this->info("Cache store [{$name}] cleared.");

        return self::SUCCESS;
    }
}
