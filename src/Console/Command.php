<?php

declare(strict_types=1);

namespace VtPhp\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use VtPhp\Foundation\Application;

abstract class Command extends SymfonyCommand
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected SymfonyStyle $io;

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        return $this->handle();
    }

    abstract protected function handle(): int;

    protected function info(string $message): void
    {
        $this->io->success($message);
    }

    protected function line(string $message): void
    {
        $this->io->writeln($message);
    }

    protected function error(string $message): void
    {
        $this->io->error($message);
    }

    /**
     * Run another forge command with this command's output, like Laravel's
     * $this->call('make:controller', ['name' => …, '--api' => true]).
     *
     * @param array<string, mixed> $arguments
     */
    protected function call(string $command, array $arguments = []): int
    {
        $application = $this->getApplication() ?? throw new \LogicException('Command is not attached to a console application.');

        return $application->find($command)->run(new ArrayInput(['command' => $command] + $arguments), $this->output);
    }

    /**
     * Render stubs/{$stub} to {$relativePath} (relative to the base path),
     * replacing each `{{ key }}` placeholder. Refuses to overwrite.
     *
     * @param array<string, string> $replacements
     */
    protected function generate(string $stub, string $relativePath, array $replacements, string $label): bool
    {
        $path = $this->app->basePath($relativePath);

        if (is_file($path)) {
            $this->error("{$label} already exists.");

            return false;
        }

        $contents = (string) file_get_contents($this->app->basePath("stubs/{$stub}"));

        foreach ($replacements as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("{$label} created: {$relativePath}");

        return true;
    }

    protected function exists(string $relativePath): bool
    {
        return is_file($this->app->basePath($relativePath));
    }
}
