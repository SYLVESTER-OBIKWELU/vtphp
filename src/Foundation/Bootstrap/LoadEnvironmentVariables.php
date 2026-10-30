<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Bootstrap;

use Symfony\Component\Dotenv\Dotenv;
use VtPhp\Foundation\Application;

/**
 * Loads `.env` into the environment, like Laravel:
 *
 * - If APP_ENV is already set (e.g. `testing` from phpunit.xml) and a
 *   `.env.{APP_ENV}` file exists, that file is loaded instead of `.env`, so
 *   tests never read a developer's local settings.
 * - Real environment variables always win. A key already set by the server,
 *   container or test is never overwritten, even when the app boots again in
 *   the same process.
 */
final class LoadEnvironmentVariables
{
    /** @var array<string, true> Keys this process took from an env file. */
    private static array $loadedFromFile = [];

    /**
     * The keys that came from `.env` rather than the real environment, so a
     * child process (e.g. `forge serve`'s PHP server) can be started without
     * them and read the file itself. Otherwise the values from when it was
     * started would count as real env vars and hide later edits to `.env`.
     *
     * @return list<string>
     */
    public static function loadedFromFile(): array
    {
        return array_keys(self::$loadedFromFile);
    }

    public function bootstrap(Application $app): void
    {
        $file = $this->environmentFile($app);

        if ($file === null) {
            return;
        }

        $values = (new Dotenv())->parse((string) file_get_contents($file), $file);

        foreach ($values as $name => $value) {
            if ($this->isDefined($name)) {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            self::$loadedFromFile[$name] = true;

            if (!str_starts_with($name, 'HTTP_')) {
                $_SERVER[$name] = $value;
            }
        }
    }

    private function environmentFile(Application $app): ?string
    {
        $environment = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null);

        if (is_string($environment) && $environment !== '' && is_file($specific = $app->basePath(".env.{$environment}"))) {
            return $specific;
        }

        $default = $app->basePath('.env');

        return is_file($default) ? $default : null;
    }

    private function isDefined(string $name): bool
    {
        return getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER);
    }
}
