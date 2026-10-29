<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;
use VtPhp\Queue\Worker;

/**
 * Runs queued jobs until stopped. In production keep it running with a
 * process manager (systemd, Supervisor) and restart it after each deploy so
 * it picks up the new code.
 */
#[AsCommand(name: 'queue:work', description: 'Run jobs from the queue')]
final class QueueWorkCommand extends Command
{
    private bool $shouldQuit = false;

    protected function configure(): void
    {
        $this->addArgument('connection', InputArgument::OPTIONAL, 'The connection to work (defaults to queue.default)');
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queues to work, highest priority first, e.g. emails,default');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run a single job, then stop');
        $this->addOption('stop-when-empty', null, InputOption::VALUE_NONE, 'Stop when the queue is empty');
        $this->addOption('tries', null, InputOption::VALUE_REQUIRED, "Tries for jobs that don't set their own", '1');
        $this->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to wait when there are no jobs', '3');
        $this->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Stop after this many jobs (0 = no limit)', '0');
        $this->addOption('max-time', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds (0 = no limit)', '0');
    }

    protected function handle(): int
    {
        $config = $this->app->make('config');
        $connectionArgument = $this->input->getArgument('connection');
        $connection = is_string($connectionArgument) ? $connectionArgument : (string) $config->get('queue.default');
        $queueOption = $this->input->getOption('queue');
        $queueList = is_string($queueOption) ? $queueOption : (string) $config->get("queue.connections.{$connection}.queue", 'default');
        $queues = array_values(array_filter(array_map('trim', explode(',', $queueList)), static fn (string $name): bool => $name !== ''));

        $once = (bool) $this->input->getOption('once');
        $stopWhenEmpty = (bool) $this->input->getOption('stop-when-empty');
        $tries = max(1, (int) $this->input->getOption('tries'));
        $sleep = max(0, (int) $this->input->getOption('sleep'));
        $maxJobs = max(0, (int) $this->input->getOption('max-jobs'));
        $maxTime = max(0, (int) $this->input->getOption('max-time'));

        $this->listenForSignals();
        $worker = $this->worker();
        $started = time();
        $processed = 0;

        $this->line(sprintf('Working the [%s] connection, queue(s): %s. Press Ctrl+C to stop.', $connection, implode(', ', $queues)));

        while (!$this->shouldQuit) {
            try {
                $result = $worker->runNextJob($connection, $queues, $tries);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if ($result !== null) {
                ++$processed;
                $this->line(sprintf(
                    '%s %s %s (%d ms)',
                    date('Y-m-d H:i:s'),
                    $result['job'],
                    match ($result['status']) {
                        'done' => '<info>DONE</info>',
                        'released' => '<comment>WILL RETRY</comment>',
                        'failed' => '<error>FAILED</error>',
                    },
                    (int) round($result['seconds'] * 1000),
                ));
            }

            if (($once && $result !== null)
                || ($result === null && ($once || $stopWhenEmpty))
                || ($maxJobs > 0 && $processed >= $maxJobs)
                || ($maxTime > 0 && time() - $started >= $maxTime)) {
                break;
            }

            if ($result === null && $sleep > 0) {
                $this->pause($sleep);
            }
        }

        return self::SUCCESS;
    }

    private function worker(): Worker
    {
        return $this->app->make(Worker::class);
    }

    /**
     * On Linux/macOS, Ctrl+C or a stop from systemd/Supervisor lets the
     * current job finish before the worker exits.
     */
    private function listenForSignals(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM, SIGQUIT] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->shouldQuit = true;
            });
        }
    }

    private function pause(int $seconds): void
    {
        // Short naps so a stop signal is noticed quickly.
        for ($i = 0; $i < $seconds * 4 && !$this->shouldQuit; ++$i) {
            usleep(250_000);
        }
    }
}
