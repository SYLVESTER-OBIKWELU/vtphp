<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:job', description: 'Create a new queued job class')]
final class MakeJobCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The job class name, e.g. SendInvoice');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));

        return $this->generate('job.stub', "app/Jobs/{$name}.php", ['class' => $name], "Job {$name}")
            ? self::SUCCESS
            : self::FAILURE;
    }
}
