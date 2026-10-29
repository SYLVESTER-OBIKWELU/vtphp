<?php

declare(strict_types=1);

namespace VtPhp\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use VtPhp\Foundation\Application;

abstract class Command extends SymfonyCommand
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected SymfonyStyle $io;

    /**
     * Commands that change or destroy data in place (migrations, seeding,
     * key rotation). They get a --force option and refuse to run when
     * APP_ENV=production unless it's given, like Laravel's.
     */
    protected bool $destructive = false;

    public function __construct(protected Application $app)
    {
        parent::__construct();

        // Added after configure() so a subclass's own configure() can't drop it.
        if ($this->destructive && !$this->getDefinition()->hasOption('force')) {
            $this->addOption('force', null, InputOption::VALUE_NONE, 'Run even when APP_ENV=production');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        if ($this->destructive && $this->app->environment() === 'production' && !$input->getOption('force')) {
            $this->error('The application is in production (APP_ENV=production). Re-run with --force if you really mean it.');

            return self::FAILURE;
        }

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
     * Render a stub to {$relativePath} (relative to the base path), replacing
     * each `{{ key }}` placeholder. Refuses to overwrite.
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

        $contents = (string) file_get_contents($this->stubPath($stub));

        foreach ($replacements as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("{$label} created: {$relativePath}");

        return true;
    }

    /**
     * The app's published copy (stubs/{$stub}, see `forge stub:publish`)
     * wins over the framework's own src/stubs/{$stub}.
     */
    protected function stubPath(string $stub): string
    {
        $published = $this->app->basePath("stubs/{$stub}");

        return is_file($published) ? $published : self::frameworkStubsPath()."/{$stub}";
    }

    public static function frameworkStubsPath(): string
    {
        return dirname(__DIR__).'/stubs';
    }

    protected function exists(string $relativePath): bool
    {
        return is_file($this->app->basePath($relativePath));
    }
}
