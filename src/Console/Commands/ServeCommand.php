<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use VtPhp\Console\Command;
use VtPhp\Foundation\Bootstrap\LoadEnvironmentVariables;

#[AsCommand(name: 'serve', description: 'Serve the application using the PHP built-in server')]
final class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host to serve on', '127.0.0.1');
        $this->addOption('port', null, InputOption::VALUE_REQUIRED, 'The port to serve on', '8000');
    }

    protected function handle(): int
    {
        $host = (string) $this->input->getOption('host');
        $port = (string) $this->input->getOption('port');

        $this->line("Server running: <info>http://{$host}:{$port}</info>");

        $this->line('Changes to .env apply on the next request; no restart needed.');

        $process = new Process(['php', '-S', "{$host}:{$port}", '-t', 'public'], $this->app->basePath(), self::serverEnvironment());
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? self::SUCCESS;
    }

    /**
     * The server's environment: this process's, minus what came from `.env`
     * (false removes a variable). Each request then reads `.env` afresh,
     * instead of being stuck with the values from when `serve` started.
     *
     * @return array<string, false>
     */
    public static function serverEnvironment(): array
    {
        return array_fill_keys(LoadEnvironmentVariables::loadedFromFile(), false);
    }
}
