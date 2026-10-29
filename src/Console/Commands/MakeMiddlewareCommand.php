<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:middleware', description: 'Create a new PSR-15 middleware class')]
final class MakeMiddlewareCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The middleware name');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));

        return $this->generate('middleware.stub', "app/Http/Middleware/{$name}.php", ['class' => $name], "Middleware {$name}")
            ? self::SUCCESS
            : self::FAILURE;
    }
}
