<?php

declare(strict_types=1);

namespace VtPhp\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
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
}
