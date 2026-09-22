<?php

declare(strict_types=1);

namespace VtPhp\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use VtPhp\Config\Repository;

final class LogManager
{
    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    public function __construct(private readonly Repository $config)
    {
    }

    public function channel(?string $name = null): LoggerInterface
    {
        $name ??= (string) $this->config->get('logging.default', 'single');

        return $this->channels[$name] ??= $this->resolve($name);
    }

    private function resolve(string $name): LoggerInterface
    {
        $channelConfig = $this->config->get("logging.channels.{$name}");

        if ($channelConfig === null) {
            throw new \InvalidArgumentException("Log channel [{$name}] is not configured.");
        }

        if ($channelConfig['driver'] === 'stack') {
            $logger = new Logger($name);

            foreach ($channelConfig['channels'] as $child) {
                $childLogger = $this->resolve($child);

                if ($childLogger instanceof Logger) {
                    foreach ($childLogger->getHandlers() as $handler) {
                        $logger->pushHandler($handler);
                    }
                }
            }

            return $logger;
        }

        $logger = new Logger($name);
        $level = Level::fromName(strtoupper($channelConfig['level'] ?? 'debug'));

        $handler = match ($channelConfig['driver']) {
            'single' => new StreamHandler($channelConfig['path'], $level),
            'stderr' => new StreamHandler('php://stderr', $level),
            default => new StreamHandler('php://stderr', $level),
        };

        $logger->pushHandler($handler);

        return $logger;
    }
}
