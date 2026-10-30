<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use VtPhp\Console\Commands\ServeCommand;
use VtPhp\Foundation\Application;
use VtPhp\Foundation\Bootstrap\LoadEnvironmentVariables;
use VtPhp\Tests\Support\TempDirectory;

final class LoadEnvironmentVariablesTest extends TestCase
{
    private string $tmp;

    /** @var list<string> */
    private array $keys = ['VTX_FROM_FILE', 'VTX_REAL', 'VTX_REF', 'VTX_SPECIFIC'];

    private string|false $originalAppEnv;

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-env');
        $this->originalAppEnv = getenv('APP_ENV');
        $this->clear(...$this->keys);
    }

    protected function tearDown(): void
    {
        $this->clear(...$this->keys);
        $this->set('APP_ENV', $this->originalAppEnv === false ? null : $this->originalAppEnv);
        TempDirectory::delete($this->tmp);
    }

    private function set(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->clear($key);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    private function clear(string ...$keys): void
    {
        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function load(): void
    {
        (new LoadEnvironmentVariables())->bootstrap(new Application($this->tmp));
    }

    public function test_file_values_are_loaded_and_references_expanded(): void
    {
        $this->set('APP_ENV', null);
        file_put_contents($this->tmp.'/.env', "VTX_FROM_FILE=hello\nVTX_REF=\"\${VTX_FROM_FILE} world\"\n");

        $this->load();

        $this->assertSame('hello', getenv('VTX_FROM_FILE'));
        $this->assertSame('hello', $_ENV['VTX_FROM_FILE']);
        $this->assertSame('hello world', $_SERVER['VTX_REF']);
    }

    public function test_real_environment_variables_always_win_even_on_reboot(): void
    {
        $this->set('APP_ENV', null);
        file_put_contents($this->tmp.'/.env', "VTX_REAL=from-file\nVTX_FROM_FILE=file\n");
        $this->set('VTX_REAL', 'from-server');

        $this->load();
        $this->assertSame('from-server', getenv('VTX_REAL'));

        // Something changes a loaded value at runtime (e.g. a test); booting
        // again in the same process must not reapply the file over it.
        $this->set('VTX_FROM_FILE', 'changed');
        $this->load();
        $this->assertSame('changed', getenv('VTX_FROM_FILE'));
    }

    public function test_an_environment_specific_file_replaces_dot_env(): void
    {
        file_put_contents($this->tmp.'/.env', "VTX_SPECIFIC=local\nVTX_FROM_FILE=local-only\n");
        file_put_contents($this->tmp.'/.env.testing', "VTX_SPECIFIC=testing\n");
        $this->set('APP_ENV', 'testing');

        $this->load();

        $this->assertSame('testing', getenv('VTX_SPECIFIC'));
        $this->assertFalse(getenv('VTX_FROM_FILE'), '.env is not read when .env.testing exists.');
    }

    public function test_dot_env_is_used_when_no_environment_specific_file_exists(): void
    {
        file_put_contents($this->tmp.'/.env', "VTX_SPECIFIC=local\n");
        $this->set('APP_ENV', 'staging');

        $this->load();

        $this->assertSame('local', getenv('VTX_SPECIFIC'));
    }

    public function test_no_file_is_fine(): void
    {
        $this->set('APP_ENV', null);
        $this->load();

        $this->assertFalse(getenv('VTX_FROM_FILE'));
    }

    public function test_forge_serve_starts_the_server_without_the_dot_env_values(): void
    {
        $this->set('VTX_REAL', 'real');
        file_put_contents($this->tmp.'/.env', "VTX_FROM_FILE=stale\nVTX_REAL=from-file\n");
        $this->load();

        $this->assertContains('VTX_FROM_FILE', LoadEnvironmentVariables::loadedFromFile());
        $this->assertNotContains('VTX_REAL', LoadEnvironmentVariables::loadedFromFile(), 'A real env var is not from the file.');

        // The PHP server forge serve starts must not inherit the values as
        // "real" env vars, or later edits to .env would be ignored.
        $child = new Process(
            [PHP_BINARY, '-r', 'echo json_encode([getenv("VTX_FROM_FILE"), getenv("VTX_REAL")]);'],
            null,
            ServeCommand::serverEnvironment(),
        );
        $child->mustRun();

        $this->assertSame([false, 'real'], json_decode($child->getOutput(), true));
    }
}
