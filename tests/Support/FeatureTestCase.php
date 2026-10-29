<?php

declare(strict_types=1);

namespace VtPhp\Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\Migrations\Migrator;
use VtPhp\Foundation\Application;
use VtPhp\Foundation\Console\Kernel as ConsoleKernel;
use VtPhp\Foundation\Http\Kernel as HttpKernel;

/**
 * Boots the real application against a throwaway SQLite database, file
 * sessions and log-driver mail, all inside a per-test temp directory.
 *
 * Every request() boots a *fresh* Application, mirroring PHP-FPM's
 * shared-nothing model: nothing survives between requests except what the
 * framework itself persists (database rows, session files, cookies).
 */
abstract class FeatureTestCase extends TestCase
{
    protected const BASE_PATH = __DIR__.'/../..';

    protected string $tmp;

    protected TestLogger $logs;

    protected Application $app;

    /** @var array<string, string> Cookies carried between requests, like a browser jar. */
    protected array $cookies = [];

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-feature');
        $this->logs = new TestLogger();

        $this->setEnvironment([
            'APP_NAME' => 'VtPhp',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'UTC',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->tmp.DIRECTORY_SEPARATOR.'database.sqlite',
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => 'false',
            'CACHE_DRIVER' => 'array',
            'MAIL_MAILER' => 'log',
            'LOG_CHANNEL' => 'single',
            'LOG_PATH' => $this->tmp.DIRECTORY_SEPARATOR.'app.log',
        ]);

        $this->app = $this->freshApp();

        (new Migrator(
            $this->app->make(DatabaseManager::class),
            $this->app->databasePath('migrations'),
        ))->run();
    }

    protected function tearDown(): void
    {
        $this->disconnect();
        unset($this->app);
        gc_collect_cycles();

        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }

        TempDirectory::delete($this->tmp);
    }

    /**
     * @param array<string, string> $values
     */
    protected function setEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $this->originalEnv)) {
                $this->originalEnv[$key] = getenv($key);
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    protected function freshApp(?string $basePath = null): Application
    {
        $this->disconnect();

        $app = Application::bootstrap($basePath ?? self::BASE_PATH);
        $app->instance(LoggerInterface::class, $this->logs);

        $config = $app->make('config');
        $config->set('session.files', $this->tmp.DIRECTORY_SEPARATOR.'sessions');
        $config->set('view.compiled', $this->tmp.DIRECTORY_SEPARATOR.'views');

        return $this->app = $app;
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     * @param (\Closure(Application): void)|null $configure Runs after boot, before dispatch (e.g. to add a route).
     * @param array<string, string> $server Server params; REMOTE_ADDR defaults to a documentation-range client IP.
     */
    protected function request(string $method, string $uri, ?array $json = null, array $headers = [], ?\Closure $configure = null, array $server = []): ResponseInterface
    {
        $app = $this->freshApp();

        if ($configure !== null) {
            $configure($app);
        }

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, 'http://localhost'.$uri, $server + ['REMOTE_ADDR' => '203.0.113.10']);

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query)->withCookieParams($this->cookies);

        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode($json, JSON_THROW_ON_ERROR)));
        } elseif (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            // Like a real JSON API client (and as PreventRequestForgery
            // requires for cookie-authenticated writes).
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $app->make(HttpKernel::class)->handle($request);

        foreach ($response->getHeader('Set-Cookie') as $header) {
            [$pair] = explode(';', $header, 2);
            [$name, $value] = explode('=', $pair, 2);
            $this->cookies[rawurldecode($name)] = rawurldecode($value);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function body(ResponseInterface $response): array
    {
        $body = $response->getBody();
        $body->rewind();
        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: int, 1: string}
     */
    protected function forge(array $input, ?Application $app = null): array
    {
        $output = new BufferedOutput();
        $code = ($app ?? $this->app)->make(ConsoleKernel::class)->handle(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function createUser(string $email = 'ada@example.com', string $password = 'password123', string $name = 'Ada Lovelace'): array
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response)['data'];
    }

    protected function login(string $email = 'ada@example.com', string $password = 'password123'): ResponseInterface
    {
        return $this->request('POST', '/api/v1/login', ['email' => $email, 'password' => $password]);
    }

    protected function assertErrorCode(ResponseInterface $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);
        self::assertFalse($body['success'] ?? null);
        self::assertSame($code, $body['error']['code'] ?? null);
    }

    private function disconnect(): void
    {
        if (isset($this->app)) {
            $this->app->make(DatabaseManager::class)->connection()->close();
        }

        try {
            Capsule::connection()->disconnect();
        } catch (\Throwable) {
            // No global capsule yet.
        }
    }
}
