<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

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

        return $this->generate('mail.stub', "app/Mail/{$name}.php", ['class' => $name, 'view' => Str::snake($name)], "Mailable {$name}")
            ? self::SUCCESS
            : self::FAILURE;
    }
}
