<?php

namespace Core;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Log
{
    protected static $logger;

    public static function getLogger()
    {
        if (!self::$logger) {
            self::$logger = new MonologLogger(env('APP_NAME', 'Framework'));
            
            $logPath = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            // Daily rotating log files
            $handler = new RotatingFileHandler(
                $logPath . '/framework.log',
                30, // Keep 30 days
                env('LOG_LEVEL', MonologLogger::DEBUG)
            );

            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                "Y-m-d H:i:s"
            );
            $handler->setFormatter($formatter);

            self::$logger->pushHandler($handler);
        }

        return self::$logger;
    }

    public static function emergency($message, array $context = [])
    {
        self::getLogger()->emergency($message, $context);
    }

    public static function alert($message, array $context = [])
    {
        self::getLogger()->alert($message, $context);
    }

    public static function critical($message, array $context = [])
    {
        self::getLogger()->critical($message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::getLogger()->error($message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::getLogger()->warning($message, $context);
    }

    public static function notice($message, array $context = [])
    {
        self::getLogger()->notice($message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::getLogger()->info($message, $context);
    }

    public static function debug($message, array $context = [])
    {
        self::getLogger()->debug($message, $context);
    }

    public static function log($level, $message, array $context = [])
    {
        self::getLogger()->log($level, $message, $context);
    }
}
