<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\BufferedOutput;
use VtPhp\Config\Repository;
use VtPhp\Console\Command;
use VtPhp\Foundation\Application;

final class ConsoleCommandTest extends TestCase
{
    private function app(string $env): Application
    {
        $app = new Application(sys_get_temp_dir());
        $app->instance('config', new Repository(['app' => ['env' => $env]]));

        return $app;
    }

    private function destructiveCommandWithOwnConfigure(Application $app): Command
    {
        // Defines configure() without calling parent::configure().
        return new class ($app) extends Command {
            protected bool $destructive = true;

            protected function configure(): void
            {
                $this->setName('demo:wipe')->addArgument('table', InputArgument::OPTIONAL);
            }

            protected function handle(): int
            {
                $this->line('wiped');

                return self::SUCCESS;
            }
        };
    }

    public function test_a_destructive_command_always_gets_force_even_with_its_own_configure(): void
    {
        $command = $this->destructiveCommandWithOwnConfigure($this->app('production'));

        $this->assertTrue($command->getDefinition()->hasOption('force'));
        $this->assertTrue($command->getDefinition()->hasArgument('table'), 'Its own definition is kept.');
    }

    public function test_production_needs_force_and_other_environments_do_not(): void
    {
        $output = new BufferedOutput();
        $prod = $this->destructiveCommandWithOwnConfigure($this->app('production'));

        $this->assertSame(Command::FAILURE, $prod->run(new ArrayInput([]), $output));
        $this->assertStringContainsString('Re-run with --force', $output->fetch());

        $this->assertSame(Command::SUCCESS, $prod->run(new ArrayInput(['--force' => true]), $output));
        $this->assertStringContainsString('wiped', $output->fetch());

        $local = $this->destructiveCommandWithOwnConfigure($this->app('local'));
        $this->assertSame(Command::SUCCESS, $local->run(new ArrayInput([]), new BufferedOutput()));
    }

    public function test_ordinary_commands_have_no_force_option(): void
    {
        $command = new class ($this->app('production')) extends Command {
            protected function configure(): void
            {
                $this->setName('demo:hello');
            }

            protected function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $this->assertFalse($command->getDefinition()->hasOption('force'));
        $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([]), new BufferedOutput()), 'Never blocked in production.');
    }
}
