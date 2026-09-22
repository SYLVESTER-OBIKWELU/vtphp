<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:mail', description: 'Create a new Mailable class')]
final class MakeMailCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The mailable class name, e.g. WelcomeEmail');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $path = $this->app->appPath("Mail/{$name}.php");

        if (is_file($path)) {
            $this->error("Mailable {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/mail.stub'));
        $contents = str_replace(
            ['{{ class }}', '{{ view }}'],
            [$name, Str::snake($name)],
            $stub,
        );

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Mailable created: app/Mail/{$name}.php");

        return self::SUCCESS;
    }
}
