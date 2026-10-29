<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;
use VtPhp\Foundation\Application;
use VtPhp\Http\FormRequest;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;
use VtPhp\Tests\Support\TempDirectory;

final class CliTest extends FeatureTestCase
{
    private const COMMANDS = [
        'about', 'serve', 'key:generate', 'route:list', 'migrate', 'migrate:rollback', 'migrate:status',
        'make:controller', 'make:model', 'make:resource', 'make:migration', 'make:middleware',
        'make:seeder', 'make:mail', 'make:request', 'stub:publish', 'db:seed',
        'cache:clear', 'session:gc', 'tokens:prune', 'make:policy', 'make:job',
        'queue:work', 'queue:failed', 'queue:retry', 'queue:forget', 'queue:flush', 'queue:clear',
    ];

    private ?\Closure $sandboxAutoloader = null;

    protected function tearDown(): void
    {
        if ($this->sandboxAutoloader !== null) {
            spl_autoload_unregister($this->sandboxAutoloader);
        }

        parent::tearDown();
    }

    /**
     * A throwaway copy of the project skeleton so generators and key:generate
     * never touch the real app/, database/ or .env. It has no stubs/ folder,
     * so generators use the framework's src/stubs unless a test publishes
     * some. Generated App\ classes are autoloaded from the sandbox.
     */
    private function sandbox(): Application
    {
        $path = $this->tmp.DIRECTORY_SEPARATOR.'sandbox';
        TempDirectory::copy(self::BASE_PATH.'/config', $path.'/config');

        $this->sandboxAutoloader = static function (string $class) use ($path): void {
            $roots = ['App\\' => '/app/', 'Database\\Seeders\\' => '/database/seeders/'];

            foreach ($roots as $prefix => $dir) {
                $file = $path.$dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (str_starts_with($class, $prefix) && is_file($file)) {
                    require $file;

                    return;
                }
            }
        };
        spl_autoload_register($this->sandboxAutoloader);

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

    private function uniqueName(string $base): string
    {
        return $base.ucfirst(bin2hex(random_bytes(3)));
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
        $this->assertStringContainsString('App\Http\Controllers\UserController@index', $output);
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

    public function test_destructive_commands_need_force_in_production(): void
    {
        $this->setEnvironment(['APP_ENV' => 'production']);
        $app = $this->freshApp();

        foreach (['migrate', 'migrate:rollback', 'db:seed', 'key:generate'] as $command) {
            [$code, $output] = $this->forge(['command' => $command], $app);

            $this->assertSame(1, $code, $command);
            $this->assertStringContainsString('Re-run with --force', $output, $command);
        }

        $this->assertMatchesRegularExpression('/create_users_table\s+Ran/', $this->forge(['command' => 'migrate:status'], $app)[1], 'Nothing was rolled back.');

        [$code, $output] = $this->forge(['command' => 'migrate', '--force' => true], $app);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);

        [$code] = $this->forge(['command' => 'db:seed', '--force' => true], $app);
        $this->assertSame(0, $code);
        $this->assertSame(0, User::query()->count(), 'Sample users with a known password are never seeded in production.');
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

    public function test_make_controller_variants_and_duplicate_protection(): void
    {
        $app = $this->sandbox();

        [$code] = $this->forge(['command' => 'make:controller', 'name' => 'invoice'], $app);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('final class InvoiceController', $this->assertValidPhp($app->appPath('Http/Controllers/InvoiceController.php')));

        [$code, $output] = $this->forge(['command' => 'make:controller', 'name' => 'InvoiceController'], $app);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already exists', $output);

        $this->forge(['command' => 'make:controller', 'name' => 'BlogPostController', '--api' => true], $app);
        $api = $this->assertValidPhp($app->appPath('Http/Controllers/BlogPostController.php'));
        $this->assertStringContainsString('public function show(string $blogPost): array', $api);
        $this->assertStringContainsString('public function destroy(string $blogPost): JsonResponse', $api);
    }

    public function test_make_controller_with_a_model_and_no_extras_validates_inline(): void
    {
        $app = $this->sandbox();

        [$code, $output] = $this->forge(['command' => 'make:controller', 'name' => 'Post', '--model' => 'Post'], $app);

        $this->assertSame(0, $code);
        $source = $this->assertValidPhp($app->appPath('Http/Controllers/PostController.php'));
        $this->assertStringContainsString('use App\Models\Post;', $source);
        $this->assertStringContainsString('@return Collection<int, Post>', $source);
        $this->assertStringContainsString('public function show(Post $post): Post', $source);
        $this->assertStringContainsString('$post = Post::query()->create($request->validate([', $source);
        $this->assertStringContainsString("\$router->apiResource('posts', PostController::class);", $output);
        $this->assertFileDoesNotExist($app->appPath('Http/Requests/StorePostRequest.php'));
    }

    public function test_make_request_resource_middleware_seeder_and_mail(): void
    {
        $app = $this->sandbox();
        $name = $this->uniqueName('Gen');

        $generated = [
            ['make:request', "Store{$name}Request", "Http/Requests/Store{$name}Request.php", "App\\Http\\Requests\\Store{$name}Request", FormRequest::class],
            ['make:resource', $name, "Http/Resources/{$name}Resource.php", "App\\Http\\Resources\\{$name}Resource", \VtPhp\Resources\JsonResource::class],
            ['make:middleware', "{$name}Middleware", "Http/Middleware/{$name}Middleware.php", "App\\Http\\Middleware\\{$name}Middleware", \Psr\Http\Server\MiddlewareInterface::class],
            ['make:mail', "{$name}Mail", "Mail/{$name}Mail.php", "App\\Mail\\{$name}Mail", \VtPhp\Mail\Mailable::class],
        ];

        foreach ($generated as [$command, $argument, $file, $class, $parent]) {
            [$code] = $this->forge(['command' => $command, 'name' => $argument], $app);
            $this->assertSame(0, $code, $command);
            $this->assertValidPhp($app->appPath($file));
            $this->assertTrue(is_subclass_of($class, $parent), "{$command} must produce a {$parent}.");
        }

        $this->assertStringContainsString("view('emails.".\Illuminate\Support\Str::snake("{$name}Mail")."')", (string) file_get_contents($app->appPath("Mail/{$name}Mail.php")));

        [$code] = $this->forge(['command' => 'make:seeder', 'name' => "{$name}Seeder"], $app);
        $this->assertSame(0, $code);
        $this->assertValidPhp($app->databasePath("seeders/{$name}Seeder.php"));
        $this->assertTrue(is_subclass_of("Database\\Seeders\\{$name}Seeder", \VtPhp\Database\Seeder::class));
    }

    public function test_make_migration_infers_the_table(): void
    {
        $app = $this->sandbox();

        $cases = [
            'create_invoices_table' => "Schema::create('invoices'",
            'add_status_to_invoices_table' => "Schema::table('invoices'",
            'backfill_totals' => '//',
        ];

        foreach ($cases as $name => $expected) {
            [$code, $output] = $this->forge(['command' => 'make:migration', 'name' => $name], $app);
            $this->assertSame(0, $code, $name);
            $this->assertMatchesRegularExpression('#database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_'.$name.'\.php#', $output);

            $files = glob($app->databasePath("migrations/*_{$name}.php")) ?: [];
            $this->assertCount(1, $files);
            $this->assertStringContainsString($expected, $this->assertValidPhp($files[0]));
            $this->assertInstanceOf(Migration::class, require $files[0]);
        }

        $this->forge(['command' => 'make:migration', 'name' => 'anything', '--create' => 'widgets'], $app);
        $this->assertStringContainsString("Schema::create('widgets'", (string) file_get_contents((glob($app->databasePath('migrations/*_anything.php')) ?: [''])[0]));

        [$code, $output] = $this->forge(['command' => 'make:migration', 'name' => 'create_invoices_table'], $app);
        $this->assertSame(1, $code, 'Duplicate migration names are refused.');
        $this->assertStringContainsString('already exists', $output);
    }

    public function test_make_model_creates_an_eloquent_model(): void
    {
        $app = $this->sandbox();
        $name = $this->uniqueName('Invoice');

        [$code] = $this->forge(['command' => 'make:model', 'name' => $name], $app);

        $this->assertSame(0, $code);
        $this->assertValidPhp($app->appPath("Models/{$name}.php"));
        $this->assertTrue(is_subclass_of("App\\Models\\{$name}", Model::class));
        $this->assertFileDoesNotExist($app->appPath("Http/Controllers/{$name}Controller.php"), 'No extras without flags.');
        $this->assertSame([], glob($app->databasePath('migrations/*.php')) ?: []);
    }

    public function test_make_model_mc_creates_a_migration_and_a_plain_controller(): void
    {
        $app = $this->sandbox();

        [$code] = $this->forge(['command' => 'make:model', 'name' => 'BlogPost', '-m' => true, '-c' => true], $app);

        $this->assertSame(0, $code);
        $this->assertCount(1, glob($app->databasePath('migrations/*_create_blog_posts_table.php')) ?: []);
        $controller = $this->assertValidPhp($app->appPath('Http/Controllers/BlogPostController.php'));
        $this->assertStringNotContainsString('function store', $controller, '-c alone is a plain controller, like Laravel.');
        $this->assertFileDoesNotExist($app->appPath('Http/Requests/StoreBlogPostRequest.php'));
        $this->assertFileDoesNotExist($app->databasePath('seeders/BlogPostSeeder.php'));
    }

    public function test_make_model_requests_and_json_resource_without_a_controller(): void
    {
        $app = $this->sandbox();

        $this->forge(['command' => 'make:model', 'name' => 'Tag', '-R' => true, '-j' => true], $app);

        $this->assertValidPhp($app->appPath('Http/Requests/StoreTagRequest.php'));
        $this->assertValidPhp($app->appPath('Http/Requests/UpdateTagRequest.php'));
        $this->assertStringContainsString('$this->resource->toArray()', $this->assertValidPhp($app->appPath('Http/Resources/TagResource.php')));
        $this->assertFileDoesNotExist($app->appPath('Http/Controllers/TagController.php'));
    }

    public function test_make_job(): void
    {
        $app = $this->sandbox();
        $name = $this->uniqueName('SendReport');

        [$code] = $this->forge(['command' => 'make:job', 'name' => $name], $app);
        $this->assertSame(0, $code);
        $this->assertStringContainsString("final class {$name} extends Job", $this->assertValidPhp($app->appPath("Jobs/{$name}.php")));

        // The generated job can be dispatched and run as-is.
        $class = "App\\Jobs\\{$name}";
        $class::dispatchSync();

        [$code, $output] = $this->forge(['command' => 'make:job', 'name' => $name], $app);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already exists', $output);
    }

    public function test_make_policy_and_make_model_p(): void
    {
        $app = $this->sandbox();
        $name = $this->uniqueName('Doc');

        [$code] = $this->forge(['command' => 'make:policy', 'name' => 'Plain'], $app);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('final class PlainPolicy', $this->assertValidPhp($app->appPath('Policies/PlainPolicy.php')));

        $this->forge(['command' => 'make:model', 'name' => $name, '-p' => true], $app);
        $policy = $this->assertValidPhp($app->appPath("Policies/{$name}Policy.php"));
        foreach (['viewAny(User $user)', "view(User \$user, {$name} \$", 'create(User $user)', "update(User \$user, {$name} \$", "delete(User \$user, {$name} \$"] as $method) {
            $this->assertStringContainsString($method, $policy);
        }

        // The generated policy is picked up automatically by naming convention.
        $modelClass = "App\\Models\\{$name}";
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->assertTrue(gate()->forUser($user)->allows('view', new $modelClass()), 'view() returns true by default.');
        $this->assertFalse(gate()->forUser($user)->allows('update', new $modelClass()), 'update() denies until you write the rule.');

        [$code, $output] = $this->forge(['command' => 'make:policy', 'name' => 'PlainPolicy'], $app);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already exists', $output);
    }

    public function test_make_model_all_scaffolds_a_working_crud_api(): void
    {
        $app = $this->sandbox();
        $model = $this->uniqueName('Note');
        $table = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly($model));

        [$code, $output] = $this->forge(['command' => 'make:model', 'name' => $model, '-a' => true], $app);
        $this->assertSame(0, $code, $output);

        $files = [
            "app/Models/{$model}.php",
            "app/Http/Controllers/{$model}Controller.php",
            "app/Http/Requests/Store{$model}Request.php",
            "app/Http/Requests/Update{$model}Request.php",
            "app/Http/Resources/{$model}Resource.php",
            "app/Policies/{$model}Policy.php",
            "database/seeders/{$model}Seeder.php",
        ];
        foreach ($files as $file) {
            $this->assertValidPhp($app->basePath($file));
        }
        $migrations = glob($app->databasePath("migrations/*_create_{$table}_table.php")) ?: [];
        $this->assertCount(1, $migrations);

        $controller = (string) file_get_contents($app->basePath("app/Http/Controllers/{$model}Controller.php"));
        $this->assertStringContainsString("public function store(Store{$model}Request \$request): {$model}Resource", $controller);
        $this->assertStringContainsString("{$model}Resource::collection({$model}::query()->get())", $controller);

        // Fill in the scaffold the way a developer would: one `title` column.
        $edit = static function (string $file, string $search, string $replace): void {
            file_put_contents($file, str_replace($search, $replace, (string) file_get_contents($file)));
        };
        $edit($migrations[0], '$table->id();', "\$table->id();\n            \$table->string('title');");
        $edit($app->basePath("app/Models/{$model}.php"), '//', "'title'");
        $edit($app->basePath("app/Http/Requests/Store{$model}Request.php"), '//', "'title' => 'required|string|max:50'");
        $edit($app->basePath("app/Http/Requests/Update{$model}Request.php"), '//', "'title' => 'sometimes|string|max:50'");

        $this->forge(['command' => 'migrate'], $app);
        $this->assertTrue(Schema::hasColumn($table, 'title'));

        // Register the routes exactly as the generator's hint says to.
        $resource = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::kebab($model));
        $this->assertStringContainsString("\$router->apiResource('{$resource}', {$model}Controller::class);", $output);
        $routes = function (Application $routedApp) use ($model, $resource): void {
            $routedApp->make(Router::class)->apiResource($resource, "App\\Http\\Controllers\\{$model}Controller");
        };

        $this->assertErrorCode($this->request('POST', "/{$resource}", ['title' => ''], configure: $routes), 422, 'VALIDATION_ERROR');

        $created = $this->request('POST', "/{$resource}", ['title' => 'First', 'smuggled' => 'x'], configure: $routes);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $note = $this->body($created)['data'];
        $this->assertSame('First', $note['title']);
        $this->assertArrayNotHasKey('smuggled', $note);

        $this->assertSame('First', $this->body($this->request('GET', "/{$resource}/{$note['id']}", configure: $routes))['data']['title']);
        $this->assertSame('Edited', $this->body($this->request('PATCH', "/{$resource}/{$note['id']}", ['title' => 'Edited'], configure: $routes))['data']['title']);
        $this->assertCount(1, $this->body($this->request('GET', "/{$resource}", configure: $routes))['data']);
        $this->assertSame(204, $this->request('DELETE', "/{$resource}/{$note['id']}", configure: $routes)->getStatusCode());
        $this->assertErrorCode($this->request('GET', "/{$resource}/{$note['id']}", configure: $routes), 404, 'NOT_FOUND');
    }

    public function test_stub_publish_copies_stubs_and_published_stubs_take_precedence(): void
    {
        $app = $this->sandbox();
        $stubCount = count(glob(self::BASE_PATH.'/src/stubs/*.stub') ?: []);

        [$code, $output] = $this->forge(['command' => 'stub:publish'], $app);
        $this->assertSame(0, $code);
        $this->assertStringContainsString("Published {$stubCount} stub(s)", $output);
        $this->assertCount($stubCount, glob($app->basePath('stubs/*.stub')) ?: []);

        file_put_contents($app->basePath('stubs/controller.stub'), "<?php\n\nnamespace App\\Http\\Controllers;\n\n// customized\nfinal class {{ class }}\n{\n}\n");

        [, $again] = $this->forge(['command' => 'stub:publish'], $app);
        $this->assertStringContainsString('Published 0 stub(s)', $again, 'Customized stubs are not overwritten.');

        $this->forge(['command' => 'make:controller', 'name' => 'Custom'], $app);
        $this->assertStringContainsString('// customized', (string) file_get_contents($app->appPath('Http/Controllers/CustomController.php')));

        [, $forced] = $this->forge(['command' => 'stub:publish', '--force' => true], $app);
        $this->assertStringContainsString("Published {$stubCount} stub(s)", $forced);
        $this->assertStringNotContainsString('// customized', (string) file_get_contents($app->basePath('stubs/controller.stub')));
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
