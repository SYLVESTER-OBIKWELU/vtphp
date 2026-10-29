<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Foundation\Application;
use VtPhp\Tests\Support\FeatureTestCase;
use VtPhp\Tests\Support\TempDirectory;

final class CliTest extends FeatureTestCase
{
    private const COMMANDS = [
        'about', 'serve', 'key:generate', 'route:list', 'migrate', 'migrate:rollback', 'migrate:status',
        'make:controller', 'make:model', 'make:resource', 'make:migration', 'make:middleware',
        'make:seeder', 'make:mail', 'db:seed',
    ];

    /**
     * A throwaway copy of the project skeleton so generators and key:generate
     * never touch the real app/, database/ or .env.
     */
    private function sandbox(): Application
    {
        $path = $this->tmp.DIRECTORY_SEPARATOR.'sandbox';
        TempDirectory::copy(self::BASE_PATH.'/config', $path.'/config');
        TempDirectory::copy(self::BASE_PATH.'/stubs', $path.'/stubs');

        return $this->freshApp($path);
    }

    private function assertValidPhp(string $file): string
    {
        $this->assertFileExists($file);
        $code = (string) file_get_contents($file);
        $this->assertStringNotContainsString('{{', $code, 'Unreplaced stub placeholder.');
        token_get_all($code, TOKEN_PARSE); // Throws ParseError on invalid syntax.

        return $code;
    }

    public function test_every_command_is_registered(): void
    {
        [$code, $output] = $this->forge(['command' => 'list']);

        $this->assertSame(0, $code);
        foreach (self::COMMANDS as $command) {
            $this->assertMatchesRegularExpression('/^\s+'.preg_quote($command, '/').'\s/m', $output, $command);
        }
    }

    public function test_about(): void
    {
        [$code, $output] = $this->forge(['command' => 'about']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('VtPhp', $output);
        $this->assertStringContainsString('testing', $output);
        $this->assertStringContainsString(PHP_VERSION, $output);
    }

    public function test_route_list_shows_every_route(): void
    {
        [$code, $output] = $this->forge(['command' => 'route:list']);

        $this->assertSame(0, $code);
        foreach (['health', 'users.index', 'users.show', 'users.store', 'users.update', 'users.destroy',
            'password.forgot', 'password.reset', 'verification.send', 'verification.verify',
            'auth.login', 'auth.logout', 'auth.me'] as $name) {
            $this->assertStringContainsString($name, $output);
        }
        $this->assertStringContainsString('App\Controllers\UserController@index', $output);
    }

    public function test_migrate_status_rollback_and_migrate_again(): void
    {
        [, $status] = $this->forge(['command' => 'migrate:status']);
        $this->assertMatchesRegularExpression('/create_users_table\s+Ran/', $status);

        [, $nothing] = $this->forge(['command' => 'migrate']);
        $this->assertStringContainsString('Nothing to migrate.', $nothing);

        [$code, $rolledBack] = $this->forge(['command' => 'migrate:rollback']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Rolled back: 2026_09_23_000001_create_password_reset_tokens_table', $rolledBack);
        $this->assertStringContainsString('Rolled back: 2026_09_22_000001_create_users_table', $rolledBack);

        [, $pending] = $this->forge(['command' => 'migrate:status']);
        $this->assertMatchesRegularExpression('/create_users_table\s+Pending/', $pending);

        [, $nothingLeft] = $this->forge(['command' => 'migrate:rollback']);
        $this->assertStringContainsString('Nothing to rollback.', $nothingLeft);

        [$code, $migrated] = $this->forge(['command' => 'migrate']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Migrated: 2026_09_22_000001_create_users_table', $migrated);
    }

    public function test_db_seed_is_idempotent(): void
    {
        [$code, $output] = $this->forge(['command' => 'db:seed']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Database seeded successfully.', $output);
        $this->forge(['command' => 'db:seed']);

        $this->assertSame(['ada@example.com', 'grace@example.com'], User::query()->orderBy('id')->pluck('email')->all());
        $this->assertSame(200, $this->login('ada@example.com', 'password')->getStatusCode(), 'Seeded users can log in.');
    }

    public function test_make_controller_and_duplicate_protection(): void
    {
        $app = $this->sandbox();

        [$code] = $this->forge(['command' => 'make:controller', 'name' => 'invoice'], $app);
        $this->assertSame(0, $code);
        $source = $this->assertValidPhp($app->appPath('Controllers/InvoiceController.php'));
        $this->assertStringContainsString('final class InvoiceController', $source);

        [$code, $output] = $this->forge(['command' => 'make:controller', 'name' => 'InvoiceController'], $app);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already exists', $output);
    }

    public function test_make_resource_middleware_seeder_and_mail_generate_working_classes(): void
    {
        $app = $this->sandbox();
        $suffix = bin2hex(random_bytes(3));

        $generated = [
            ['make:resource', "Gen{$suffix}", "Resources/Gen{$suffix}Resource.php", "App\\Resources\\Gen{$suffix}Resource", \VtPhp\Resources\JsonResource::class],
            ['make:middleware', "Gen{$suffix}Middleware", "Middleware/Gen{$suffix}Middleware.php", "App\\Middleware\\Gen{$suffix}Middleware", \Psr\Http\Server\MiddlewareInterface::class],
            ['make:mail', "Gen{$suffix}Mail", "Mail/Gen{$suffix}Mail.php", "App\\Mail\\Gen{$suffix}Mail", \VtPhp\Mail\Mailable::class],
        ];

        foreach ($generated as [$command, $name, $file, $class, $parent]) {
            [$code] = $this->forge(['command' => $command, 'name' => $name], $app);
            $this->assertSame(0, $code, $command);
            $path = $app->appPath($file);
            $this->assertValidPhp($path);
            require_once $path;
            $this->assertTrue(is_subclass_of($class, $parent), "{$command} must produce a {$parent}.");
        }

        $this->assertStringContainsString("view('emails.gen{$suffix}_mail')", (string) file_get_contents($app->appPath("Mail/Gen{$suffix}Mail.php")));

        [$code] = $this->forge(['command' => 'make:seeder', 'name' => "Gen{$suffix}Seeder"], $app);
        $this->assertSame(0, $code);
        $seeder = $app->databasePath("seeders/Gen{$suffix}Seeder.php");
        $this->assertValidPhp($seeder);
        require_once $seeder;
        $this->assertTrue(is_subclass_of("Database\\Seeders\\Gen{$suffix}Seeder", \VtPhp\Database\Seeder::class));
    }

    public function test_make_migration_generates_a_runnable_migration(): void
    {
        $app = $this->sandbox();

        [$code, $output] = $this->forge(['command' => 'make:migration', 'name' => 'create_invoices_table'], $app);
        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('#database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_invoices_table\.php#', $output);

        $files = glob($app->databasePath('migrations/*_create_invoices_table.php')) ?: [];
        $this->assertCount(1, $files);
        $this->assertValidPhp($files[0]);
        $this->assertInstanceOf(Migration::class, require $files[0]);
    }

    public function test_make_model_creates_a_class_in_app_models(): void
    {
        $app = $this->sandbox();

        [$code] = $this->forge(['command' => 'make:model', 'name' => 'invoice'], $app);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('final class Invoice', $this->assertValidPhp($app->appPath('Models/Invoice.php')));
    }

    #[Group('defect')]
    public function test_make_model_generates_an_eloquent_model(): void
    {
        // Every model in the framework is Eloquent, but the stub produces a
        // plain class with no persistence.
        $app = $this->sandbox();
        $name = 'GenModel'.bin2hex(random_bytes(3));
        $this->forge(['command' => 'make:model', 'name' => $name], $app);
        require_once $app->appPath("Models/{$name}.php");

        $this->assertTrue(is_subclass_of("App\\Models\\{$name}", Model::class));
    }

    public function test_key_generate(): void
    {
        $app = $this->sandbox();
        $env = $app->basePath('.env');

        [$code, $output] = $this->forge(['command' => 'key:generate'], $app);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('.env file not found', $output);

        file_put_contents($env, "APP_NAME=VtPhp\nAPP_KEY=\nAPP_DEBUG=true\n");
        [$code] = $this->forge(['command' => 'key:generate'], $app);
        $this->assertSame(0, $code);
        $contents = (string) file_get_contents($env);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $contents);
        preg_match('/^APP_KEY=base64:(\S+)$/m', $contents, $key);
        $this->assertSame(32, strlen((string) base64_decode($key[1], true)));
        $this->assertStringContainsString("APP_NAME=VtPhp\n", $contents, 'Other lines are preserved.');
        $this->assertStringContainsString("APP_DEBUG=true\n", $contents);

        $this->forge(['command' => 'key:generate'], $app);
        $this->assertNotSame($contents, file_get_contents($env), 'Regenerating replaces the key.');
        $this->assertSame(1, preg_match_all('/^APP_KEY=/m', (string) file_get_contents($env)));

        file_put_contents($env, "APP_NAME=VtPhp\n");
        $this->forge(['command' => 'key:generate'], $app);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', (string) file_get_contents($env), 'A missing key line is appended.');
    }
}
