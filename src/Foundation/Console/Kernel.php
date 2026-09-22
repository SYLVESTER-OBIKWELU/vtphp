<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Console;

use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VtPhp\Foundation\Application;

final class Kernel
{
    /** @var array<int, class-string> */
    private array $commands = [
        \VtPhp\Console\Commands\AboutCommand::class,
        \VtPhp\Console\Commands\ServeCommand::class,
        \VtPhp\Console\Commands\KeyGenerateCommand::class,
        \VtPhp\Console\Commands\RouteListCommand::class,
        \VtPhp\Console\Commands\MigrateCommand::class,
        \VtPhp\Console\Commands\MigrateRollbackCommand::class,
        \VtPhp\Console\Commands\MigrateStatusCommand::class,
        \VtPhp\Console\Commands\MakeControllerCommand::class,
        \VtPhp\Console\Commands\MakeModelCommand::class,
        \VtPhp\Console\Commands\MakeResourceCommand::class,
        \VtPhp\Console\Commands\MakeMigrationCommand::class,
        \VtPhp\Console\Commands\MakeMiddlewareCommand::class,
        \VtPhp\Console\Commands\MakeSeederCommand::class,
        \VtPhp\Console\Commands\MakeMailCommand::class,
        \VtPhp\Console\Commands\DbSeedCommand::class,
    ];

    public function __construct(private Application $app)
    {
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyConsoleApplication(
            (string) $this->app->make('config')->get('app.name', 'VtPhp'),
        );
        $console->setAutoExit(false);

        foreach ($this->commands as $commandClass) {
            $console->add($this->app->make($commandClass));
        }

        return $console->run($input, $output);
    }
}
