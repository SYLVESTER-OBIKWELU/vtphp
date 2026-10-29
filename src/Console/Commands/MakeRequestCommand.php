<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:request', description: 'Create a new form request class')]
final class MakeRequestCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The form request name, e.g. StorePostRequest');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));

        return $this->generate('request.stub', "app/Http/Requests/{$name}.php", ['class' => $name], "Request {$name}")
            ? self::SUCCESS
            : self::FAILURE;
    }
}
