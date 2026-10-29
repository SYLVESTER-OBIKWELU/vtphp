<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

/**
 * Copies the framework's generator stubs into the app's stubs/ folder, where
 * the make:* commands pick them up instead of the defaults.
 */
#[AsCommand(name: 'stub:publish', description: 'Publish the generator stubs for customization')]
final class StubPublishCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite stubs that were already published');
    }

    protected function handle(): int
    {
        $target = $this->app->basePath('stubs');
        @mkdir($target, 0777, true);

        $published = 0;

        foreach (glob(self::frameworkStubsPath().'/*.stub') ?: [] as $stub) {
            $destination = $target.'/'.basename($stub);

            if (is_file($destination) && !$this->input->getOption('force')) {
                continue;
            }

            copy($stub, $destination);
            ++$published;
        }

        $this->info("Published {$published} stub(s) to stubs/.");

        return self::SUCCESS;
    }
}
