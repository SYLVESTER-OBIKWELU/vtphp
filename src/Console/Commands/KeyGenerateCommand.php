<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'key:generate', description: 'Generate a new application key')]
final class KeyGenerateCommand extends Command
{
    protected function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $envFile = $this->app->basePath('.env');

        if (!is_file($envFile)) {
            $this->error('.env file not found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($envFile);

        $contents = preg_match('/^APP_KEY=.*$/m', $contents)
            ? (string) preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $contents)
            : $contents."\nAPP_KEY={$key}\n";

        file_put_contents($envFile, $contents);

        $this->info("Application key set: {$key}");

        return self::SUCCESS;
    }
}
