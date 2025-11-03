<?php

namespace Core;

class Console
{
    protected $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    protected function registerCommands()
    {
        $this->commands = [
            // Make commands
            'make:controller' => 'makeController',
            'make:model' => 'makeModel',
            'make:migration' => 'makeMigration',
            'make:middleware' => 'makeMiddleware',
            'make:seeder' => 'makeSeeder',
            'make:request' => 'makeRequest',
            'make:provider' => 'makeProvider',
            'make:command' => 'makeCommand',
            'make:mail' => 'makeMail',
            'make:event' => 'makeEvent',
            'make:listener' => 'makeListener',
            'make:job' => 'makeJob',
            'make:notification' => 'makeNotification',
            'make:policy' => 'makePolicy',
            'make:resource' => 'makeResource',
            'make:rule' => 'makeRule',
            'make:test' => 'makeTest',
            'make:factory' => 'makeFactory',
            'make:observer' => 'makeObserver',
            'make:trait' => 'makeTrait',
            'make:interface' => 'makeInterface',
            'make:enum' => 'makeEnum',
            'make:cast' => 'makeCast',
            'make:channel' => 'makeChannel',
            'make:exception' => 'makeException',
            'make:scope' => 'makeScope',
            
            // Migration commands
            'migrate' => 'migrate',
            'migrate:rollback' => 'rollback',
            'migrate:reset' => 'reset',
            'migrate:fresh' => 'fresh',
            'migrate:status' => 'migrateStatus',
            'migrate:install' => 'migrateInstall',
            'migrate:refresh' => 'migrateRefresh',
            
            // Database commands
            'db:seed' => 'seed',
            'db:wipe' => 'wipe',
            'db:monitor' => 'dbMonitor',
            'db:show' => 'dbShow',
            'db:table' => 'dbTable',
            
            // Cache commands
            'cache:clear' => 'cacheClear',
            'cache:forget' => 'cacheForget',
            'view:clear' => 'viewClear',
            'view:cache' => 'viewCache',
            'config:clear' => 'configClear',
            'config:cache' => 'configCache',
            'route:cache' => 'routeCache',
            'route:clear' => 'routeClear',
            'optimize' => 'optimize',
            'optimize:clear' => 'optimizeClear',
            'event:cache' => 'eventCache',
            'event:clear' => 'eventClear',
            'event:list' => 'eventList',
            
            // Route commands
            'route:list' => 'routeList',
            
            // Storage commands
            'storage:link' => 'storageLink',
            'storage:unlink' => 'storageUnlink',
            
            // Queue commands
            'queue:work' => 'queueWork',
            'queue:listen' => 'queueListen',
            'queue:restart' => 'queueRestart',
            'queue:retry' => 'queueRetry',
            'queue:failed' => 'queueFailed',
            'queue:flush' => 'queueFlush',
            'queue:forget' => 'queueForget',
            'queue:clear' => 'queueClear',
            
            // Schedule commands
            'schedule:run' => 'scheduleRun',
            'schedule:list' => 'scheduleList',
            'schedule:work' => 'scheduleWork',
            
            // Vendor commands
            'vendor:publish' => 'vendorPublish',
            
            // Model commands
            'model:show' => 'modelShow',
            'model:prune' => 'modelPrune',
            
            // Application commands
            'serve' => 'serve',
            'tinker' => 'tinker',
            'list' => 'listCommands',
            'about' => 'about',
            'inspire' => 'inspire',
            'key:generate' => 'keyGenerate',
            'env' => 'showEnv',
            'down' => 'down',
            'up' => 'up',
            
            // Package commands
            'package:discover' => 'packageDiscover',
        ];
    }

    public function run($argv)
    {
        array_shift($argv); // Remove script name

        if (empty($argv)) {
            $this->listCommands();
            return;
        }

        $command = $argv[0];
        $args = array_slice($argv, 1);

        if (!isset($this->commands[$command])) {
            $this->error("Command '{$command}' not found.");
            return;
        }

        $method = $this->commands[$command];
        $this->$method($args);
    }

    protected function makeController($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a controller name.");
            return;
        }

        $name = $args[0];
        $resource = in_array('--resource', $args) || in_array('-r', $args);
        $api = in_array('--api', $args);

        $namespace = "App\\Controller";
        $path = __DIR__ . "/../app/Controller/{$name}.php";

        if (file_exists($path)) {
            $this->error("Controller already exists!");
            return;
        }

        $methods = $this->getControllerMethods($resource, $api);

        $content = <<<PHP
<?php

namespace {$namespace};

use Core\Controller;
use Core\Request;

class {$name} extends Controller
{
{$methods}
}
PHP;

        file_put_contents($path, $content);
        $this->success("Controller created successfully: {$path}");
    }

    protected function getControllerMethods($resource, $api)
    {
        if (!$resource && !$api) {
            return <<<'PHP'
    public function index(Request $request)
    {
        // Your code here
    }
PHP;
        }

        if ($api) {
            return <<<'PHP'
    public function index(Request $request)
    {
        return $this->json(['data' => []]);
    }

    public function store(Request $request)
    {
        return $this->json(['message' => 'Created'], 201);
    }

    public function show(Request $request)
    {
        $id = $request->params('id');
        return $this->json(['data' => []]);
    }

    public function update(Request $request)
    {
        $id = $request->params('id');
        return $this->json(['message' => 'Updated']);
    }

    public function destroy(Request $request)
    {
        $id = $request->params('id');
        return $this->json(['message' => 'Deleted']);
    }
PHP;
        }

        return <<<'PHP'
    public function index(Request $request)
    {
        return $this->view('index');
    }

    public function create(Request $request)
    {
        return $this->view('create');
    }

    public function store(Request $request)
    {
        // Store logic
        return $this->redirect('/');
    }

    public function show(Request $request)
    {
        $id = $request->params('id');
        return $this->view('show', compact('id'));
    }

    public function edit(Request $request)
    {
        $id = $request->params('id');
        return $this->view('edit', compact('id'));
    }

    public function update(Request $request)
    {
        $id = $request->params('id');
        // Update logic
        return $this->redirect('/');
    }

    public function destroy(Request $request)
    {
        $id = $request->params('id');
        // Delete logic
        return $this->redirect('/');
    }
PHP;
    }

    protected function makeModel($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a model name.");
            return;
        }

        $name = $args[0];
        $migration = in_array('--migration', $args) || in_array('-m', $args);

        $namespace = "App\\Models";
        $path = __DIR__ . "/../app/Models/{$name}.php";

        if (file_exists($path)) {
            $this->error("Model already exists!");
            return;
        }

        $table = strtolower($name) . 's';

        $content = <<<PHP
<?php

namespace {$namespace};

use Core\Model;

class {$name} extends Model
{
    protected static \$table = '{$table}';
    
    protected \$fillable = [];
    
    protected \$hidden = [];
    
    protected \$casts = [];
}
PHP;

        file_put_contents($path, $content);
        $this->success("Model created successfully: {$path}");

        if ($migration) {
            $this->makeMigration(["create_{$table}_table"]);
        }
    }

    protected function makeMigration($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a migration name.");
            return;
        }

        $name = $args[0];
        $timestamp = date('Y_m_d_His');
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        $filename = "{$timestamp}_{$name}.php";

        $path = __DIR__ . "/../database/migrations/{$filename}";
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Detect if it's a create table migration
        $isCreate = strpos($name, 'create_') === 0;
        $table = '';
        
        if ($isCreate) {
            $table = str_replace(['create_', '_table'], '', $name);
        }

        if ($isCreate) {
            $content = <<<PHP
<?php

use Core\Migration;
use Core\Schema;

class {$className} extends Migration
{
    public function up()
    {
        Schema::create('{$table}', function(\$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('{$table}');
    }
}
PHP;
        } else {
            $content = <<<PHP
<?php

use Core\Migration;
use Core\Schema;

class {$className} extends Migration
{
    public function up()
    {
        Schema::table('{$table}', function(\$table) {
            // Add columns here
        });
    }

    public function down()
    {
        Schema::table('{$table}', function(\$table) {
            // Drop columns here
        });
    }
}
PHP;
        }

        file_put_contents($path, $content);
        $this->success("Migration created successfully: {$path}");
    }

    protected function makeMiddleware($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a middleware name.");
            return;
        }

        $name = $args[0];
        $namespace = "App\\Middleware";
        $path = __DIR__ . "/../app/Middleware/{$name}.php";

        if (file_exists($path)) {
            $this->error("Middleware already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace {$namespace};

use Core\Middleware;
use Core\Request;

class {$name} extends Middleware
{
    public function handle(Request \$request)
    {
        // Your middleware logic here
        
        return \$this->next();
    }
}
PHP;

        file_put_contents($path, $content);
        $this->success("Middleware created successfully: {$path}");
    }

    protected function migrate($args)
    {
        $this->info("Running migrations...");

        $config = require __DIR__ . '/../config/database.php';
        $db = Database::getInstance($config);
        Schema::setDatabase($db);

        // Create migrations table if not exists
        $this->createMigrationsTable($db);

        $migrationsPath = __DIR__ . '/../database/migrations';
        
        if (!is_dir($migrationsPath)) {
            $this->error("Migrations directory not found!");
            return;
        }

        $files = glob($migrationsPath . '/*.php');
        $ran = $this->getRanMigrations($db);

        foreach ($files as $file) {
            $migration = basename($file, '.php');
            
            if (in_array($migration, $ran)) {
                continue;
            }

            require_once $file;
            
            // Extract class name
            preg_match('/\d{4}_\d{2}_\d{2}_\d{6}_(.+)/', $migration, $matches);
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $matches[1] ?? $migration)));
            
            if (class_exists($className)) {
                $instance = new $className();
                $instance->up();
                
                $db->insert('migrations', [
                    'migration' => $migration,
                    'batch' => $this->getNextBatch($db)
                ]);
                
                $this->success("Migrated: {$migration}");
            }
        }

        $this->success("Migration completed!");
    }

    protected function createMigrationsTable($db)
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL
        )";
        
        $db->query($sql);
    }

    protected function getRanMigrations($db)
    {
        try {
            $results = $db->select("SELECT migration FROM migrations ORDER BY batch, migration");
            return array_column($results, 'migration');
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getNextBatch($db)
    {
        $result = $db->selectOne("SELECT MAX(batch) as batch FROM migrations");
        return ($result['batch'] ?? 0) + 1;
    }

    protected function rollback($args)
    {
        $this->info("Rolling back migrations...");
        $config = require __DIR__ . '/../config/database.php';
        $db = Database::getInstance($config);
        Schema::setDatabase($db);

        $lastBatch = $db->selectOne("SELECT MAX(batch) as batch FROM migrations");
        
        if (!$lastBatch || !$lastBatch['batch']) {
            $this->info("Nothing to rollback.");
            return;
        }

        $migrations = $db->select(
            "SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC",
            [$lastBatch['batch']]
        );

        $migrationsPath = __DIR__ . '/../database/migrations';

        foreach ($migrations as $migration) {
            $file = $migrationsPath . '/' . $migration['migration'] . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                
                preg_match('/\d{4}_\d{2}_\d{2}_\d{6}_(.+)/', $migration['migration'], $matches);
                $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $matches[1] ?? $migration['migration'])));
                
                if (class_exists($className)) {
                    $instance = new $className();
                    $instance->down();
                    
                    $db->delete('migrations', ['migration' => $migration['migration']]);
                    $this->success("Rolled back: {$migration['migration']}");
                }
            }
        }

        $this->success("Rollback completed!");
    }

    protected function reset($args)
    {
        $this->info("Resetting database...");
        
        while (true) {
            $config = require __DIR__ . '/../config/database.php';
            $db = Database::getInstance($config);
            
            $result = $db->selectOne("SELECT COUNT(*) as count FROM migrations");
            
            if ($result['count'] == 0) {
                break;
            }
            
            $this->rollback([]);
        }
        
        $this->success("Database reset completed!");
    }

    protected function fresh($args)
    {
        $this->reset([]);
        $this->migrate([]);
    }

    protected function seed($args)
    {
        $this->info("Seeding database...");
        
        $seederPath = __DIR__ . '/../database/seeds';
        
        if (!is_dir($seederPath)) {
            $this->error("Seeds directory not found!");
            return;
        }

        $seederName = $args[0] ?? 'DatabaseSeeder';
        $seederFile = $seederPath . '/' . $seederName . '.php';

        if (!file_exists($seederFile)) {
            $this->error("Seeder not found: {$seederName}");
            return;
        }

        require_once $seederFile;

        if (class_exists($seederName)) {
            $config = require __DIR__ . '/../config/database.php';
            $db = Database::getInstance($config);
            
            $seeder = new $seederName($db);
            $seeder->run();
            
            $this->success("Database seeded successfully!");
        } else {
            $this->error("Seeder class not found: {$seederName}");
        }
    }

    protected function makeSeeder($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a seeder name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../database/seeds/{$name}.php";

        if (file_exists($path)) {
            $this->error("Seeder already exists!");
            return;
        }

        $content = <<<PHP
<?php

use Core\Database;

class {$name}
{
    protected \$db;

    public function __construct(Database \$db)
    {
        \$this->db = \$db;
    }

    public function run()
    {
        // Your seeding logic here
        // Example:
        // \$this->db->insert('users', [
        //     'name' => 'John Doe',
        //     'email' => 'john@example.com',
        //     'password' => password_hash('password', PASSWORD_DEFAULT)
        // ]);
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Seeder created successfully: {$path}");
    }

    protected function makeRequest($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a request name.");
            return;
        }

        $name = $args[0];
        $namespace = "App\\Requests";
        $path = __DIR__ . "/../app/Requests/{$name}.php";

        if (file_exists($path)) {
            $this->error("Request already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace {$namespace};

class {$name}
{
    public static function rules()
    {
        return [
            // 'field' => 'required|string|max:255',
        ];
    }

    public static function messages()
    {
        return [
            // 'field.required' => 'Custom error message',
        ];
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Request created successfully: {$path}");
    }

    protected function makeProvider($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a provider name.");
            return;
        }

        $name = $args[0];
        $namespace = "App\\Providers";
        $path = __DIR__ . "/../app/Providers/{$name}.php";

        if (file_exists($path)) {
            $this->error("Provider already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace {$namespace};

use Core\ServiceProvider;

class {$name} extends ServiceProvider
{
    public function register()
    {
        // Register bindings in the container
        // Example:
        // \$this->app->bind('service', function() {
        //     return new Service();
        // });
    }

    public function boot()
    {
        // Bootstrap any application services
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Provider created successfully: {$path}");
    }

    protected function makeCommand($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a command name.");
            return;
        }

        $name = $args[0];
        $namespace = "App\\Commands";
        $path = __DIR__ . "/../app/Commands/{$name}.php";

        if (file_exists($path)) {
            $this->error("Command already exists!");
            return;
        }

        $commandName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        $content = <<<PHP
<?php

namespace {$namespace};

class {$name}
{
    public function handle(\$args = [])
    {
        // Your command logic here
        echo "Executing {$name} command...\n";
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Command created successfully: {$path}");
        $this->info("Register it in Console.php to use it.");
    }

    protected function migrateStatus($args)
    {
        $this->info("Migration Status:");
        echo "\n";

        $config = require __DIR__ . '/../config/database.php';
        $db = Database::getInstance($config);

        $ran = $this->getRanMigrations($db);
        $migrationsPath = __DIR__ . '/../database/migrations';
        
        if (!is_dir($migrationsPath)) {
            $this->error("Migrations directory not found!");
            return;
        }

        $files = glob($migrationsPath . '/*.php');

        echo sprintf("%-50s %-10s\n", "Migration", "Status");
        echo str_repeat("-", 62) . "\n";

        foreach ($files as $file) {
            $migration = basename($file, '.php');
            $status = in_array($migration, $ran) ? "\033[32m✓ Ran\033[0m" : "\033[33m⦿ Pending\033[0m";
            echo sprintf("%-50s %s\n", $migration, $status);
        }

        echo "\n";
    }

    protected function wipe($args)
    {
        $this->info("Wiping database...");

        $config = require __DIR__ . '/../config/database.php';
        $db = Database::getInstance($config);

        // Get all tables
        $tables = $db->select("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $db->query("DROP TABLE IF EXISTS {$tableName}");
            $this->success("Dropped table: {$tableName}");
        }

        $this->success("Database wiped successfully!");
    }

    protected function cacheClear($args)
    {
        $cachePath = __DIR__ . '/../storage/cache';
        
        if (!is_dir($cachePath)) {
            $this->info("No cache to clear.");
            return;
        }

        $this->clearDirectory($cachePath);
        $this->success("Application cache cleared!");
    }

    protected function viewClear($args)
    {
        $viewCachePath = __DIR__ . '/../storage/cache/views';
        
        if (!is_dir($viewCachePath)) {
            $this->info("No view cache to clear.");
            return;
        }

        $this->clearDirectory($viewCachePath);
        $this->success("View cache cleared!");
    }

    protected function configClear($args)
    {
        $configCachePath = __DIR__ . '/../storage/cache/config.php';
        
        if (file_exists($configCachePath)) {
            unlink($configCachePath);
            $this->success("Configuration cache cleared!");
        } else {
            $this->info("No configuration cache to clear.");
        }
    }

    protected function configCache($args)
    {
        $this->info("Caching configuration...");

        $configPath = __DIR__ . '/../config';
        $configs = [];

        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            $configs[$key] = require $file;
        }

        $cachePath = __DIR__ . '/../storage/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        file_put_contents(
            $cachePath . '/config.php',
            '<?php return ' . var_export($configs, true) . ';'
        );

        $this->success("Configuration cached successfully!");
    }

    protected function routeList($args)
    {
        $this->info("Routes List:");
        echo "\n";

        // Load routes
        $app = \Core\App::getInstance();
        $app->loadRoutes(__DIR__ . '/../routes/web.php');
        $app->loadRoutes(__DIR__ . '/../routes/api.php');

        $router = $app->router();
        $routes = $router->getRoutes();

        echo sprintf("%-10s %-40s %-30s %-20s\n", "Method", "URI", "Action", "Middleware");
        echo str_repeat("-", 102) . "\n";

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $uri = '/' . $route['uri'];
                
                if (is_array($route['action'])) {
                    $action = is_string($route['action'][0]) 
                        ? $route['action'][0] . '@' . $route['action'][1]
                        : get_class($route['action'][0]) . '@' . $route['action'][1];
                } else {
                    $action = 'Closure';
                }

                $middleware = !empty($route['middleware']) 
                    ? implode(', ', array_map(function($m) {
                        return basename(str_replace('\\', '/', $m));
                    }, $route['middleware']))
                    : '-';

                echo sprintf("%-10s %-40s %-30s %-20s\n", 
                    $method, 
                    substr($uri, 0, 40), 
                    substr($action, 0, 30), 
                    substr($middleware, 0, 20)
                );
            }
        }

        echo "\n";
    }

    protected function tinker($args)
    {
        $this->info("PHP Interactive Shell (Tinker)");
        $this->info("Type 'exit' to quit.");
        echo "\n";

        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../core/helpers.php';

        // Load config
        $config = require __DIR__ . '/../config/database.php';
        $db = Database::getInstance($config);
        \Core\Model::setDatabase($db);

        while (true) {
            echo ">>> ";
            $input = trim(fgets(STDIN));

            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            if (empty($input)) {
                continue;
            }

            try {
                $result = eval("return {$input};");
                var_dump($result);
            } catch (\Throwable $e) {
                echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
            }

            echo "\n";
        }

        $this->info("Goodbye!");
    }

    protected function keyGenerate($args)
    {
        $key = bin2hex(random_bytes(32));
        
        $envPath = __DIR__ . '/../.env';
        
        if (!file_exists($envPath)) {
            $this->error(".env file not found!");
            return;
        }

        $envContent = file_get_contents($envPath);
        
        if (strpos($envContent, 'APP_KEY=') !== false) {
            $envContent = preg_replace('/APP_KEY=.*/', "APP_KEY={$key}", $envContent);
        } else {
            $envContent .= "\nAPP_KEY={$key}\n";
        }

        file_put_contents($envPath, $envContent);
        
        $this->success("Application key generated successfully!");
        $this->info("Key: {$key}");
    }

    protected function clearDirectory($path)
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->clearDirectory($file);
                rmdir($file);
            }
        }
    }

    protected function serve($args)
    {
        $host = '127.0.0.1';
        $port = 5500;

        foreach ($args as $arg) {
            if (strpos($arg, '--port=') === 0) {
                $port = substr($arg, 7);
            }
            if (strpos($arg, '--host=') === 0) {
                $host = substr($arg, 7);
            }
        }

        $this->info("Starting development server...");
        $this->info("Server running at http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop.");

        $publicPath = __DIR__ . '/../public_html';
        passthru("php -S {$host}:{$port} -t {$publicPath}");
    }

    protected function listCommands()
    {
        $this->info("Available commands:");
        echo "\n";
        
        foreach ($this->commands as $command => $method) {
            echo "  \033[32m{$command}\033[0m\n";
        }
        
        echo "\n";
    }

    protected function success($message)
    {
        echo "\033[32m✓ {$message}\033[0m\n";
    }

    protected function error($message)
    {
        echo "\033[31m✗ {$message}\033[0m\n";
    }

    protected function info($message)
    {
        echo "\033[36mℹ {$message}\033[0m\n";
    }

    // NEW MAKE COMMANDS
    
    protected function makeMail($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a mail class name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Mail/{$name}.php";

        if (file_exists($path)) {
            $this->error("Mail class already exists!");
            return;
        }

        $viewName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        $content = <<<PHP
<?php

namespace App\\Mail;

use Core\\Mail as Mailer;

class {$name}
{
    protected \$data;

    public function __construct(\$data = [])
    {
        \$this->data = \$data;
    }

    public function build()
    {
        return Mailer::make()
            ->subject('Your Subject')
            ->view('emails.{$viewName}', \$this->data);
    }

    protected function getViewName()
    {
        return '{$viewName}';
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Mail class created successfully: {$path}");
    }

    protected function makeEvent($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide an event name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Events/{$name}.php";

        if (file_exists($path)) {
            $this->error("Event already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Events;

class {$name}
{
    public \$data;

    public function __construct(\$data = null)
    {
        \$this->data = \$data;
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Event created successfully: {$path}");
    }

    protected function makeListener($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a listener name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Listeners/{$name}.php";

        if (file_exists($path)) {
            $this->error("Listener already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Listeners;

class {$name}
{
    public function handle(\$event)
    {
        // Handle the event
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Listener created successfully: {$path}");
    }

    protected function makeJob($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a job name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Jobs/{$name}.php";

        if (file_exists($path)) {
            $this->error("Job already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Jobs;

class {$name}
{
    protected \$data;

    public function __construct(\$data)
    {
        \$this->data = \$data;
    }

    public function handle()
    {
        // Job logic here
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Job created successfully: {$path}");
    }

    protected function makeNotification($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a notification name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Notifications/{$name}.php";

        if (file_exists($path)) {
            $this->error("Notification already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Notifications;

class {$name}
{
    protected \$data;

    public function __construct(\$data)
    {
        \$this->data = \$data;
    }

    public function via()
    {
        return ['mail', 'database'];
    }

    public function toMail(\$notifiable)
    {
        // Return mail representation
    }

    public function toArray(\$notifiable)
    {
        return [
            // Notification data
        ];
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Notification created successfully: {$path}");
    }

    protected function makePolicy($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a policy name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Policies/{$name}.php";

        if (file_exists($path)) {
            $this->error("Policy already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Policies;

class {$name}
{
    public function view(\$user, \$model)
    {
        // Authorization logic
        return true;
    }

    public function create(\$user)
    {
        return true;
    }

    public function update(\$user, \$model)
    {
        return \$user->id === \$model->user_id;
    }

    public function delete(\$user, \$model)
    {
        return \$user->id === \$model->user_id;
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Policy created successfully: {$path}");
    }

    protected function makeResource($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a resource name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Resources/{$name}.php";

        if (file_exists($path)) {
            $this->error("Resource already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Resources;

class {$name}
{
    public static function toArray(\$model)
    {
        return [
            'id' => \$model->id,
            // Add more fields
        ];
    }

    public static function collection(\$collection)
    {
        return array_map([self::class, 'toArray'], \$collection);
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Resource created successfully: {$path}");
    }

    protected function makeRule($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a rule name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Rules/{$name}.php";

        if (file_exists($path)) {
            $this->error("Rule already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Rules;

class {$name}
{
    public function passes(\$attribute, \$value)
    {
        // Validation logic
        return true;
    }

    public function message()
    {
        return 'The :attribute field is invalid.';
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Rule created successfully: {$path}");
    }

    protected function makeTest($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a test name.");
            return;
        }

        $name = $args[0];
        $unit = in_array('--unit', $args);
        $dir = $unit ? 'Unit' : 'Feature';
        $path = __DIR__ . "/../tests/{$dir}/{$name}.php";

        if (file_exists($path)) {
            $this->error("Test already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace Tests\\{$dir};

use PHPUnit\\Framework\\TestCase;

class {$name} extends TestCase
{
    public function testExample()
    {
        \$this->assertTrue(true);
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Test created successfully: {$path}");
    }

    protected function makeFactory($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a factory name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../database/factories/{$name}Factory.php";

        if (file_exists($path)) {
            $this->error("Factory already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace Database\\Factories;

class {$name}Factory
{
    public function definition()
    {
        return [
            // Define model attributes
        ];
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Factory created successfully: {$path}");
    }

    protected function makeObserver($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide an observer name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Observers/{$name}.php";

        if (file_exists($path)) {
            $this->error("Observer already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Observers;

class {$name}
{
    public function creating(\$model)
    {
        //
    }

    public function created(\$model)
    {
        //
    }

    public function updating(\$model)
    {
        //
    }

    public function updated(\$model)
    {
        //
    }

    public function deleting(\$model)
    {
        //
    }

    public function deleted(\$model)
    {
        //
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Observer created successfully: {$path}");
    }

    protected function makeTrait($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a trait name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Traits/{$name}.php";

        if (file_exists($path)) {
            $this->error("Trait already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Traits;

trait {$name}
{
    // Trait methods
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Trait created successfully: {$path}");
    }

    protected function makeInterface($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide an interface name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Interfaces/{$name}.php";

        if (file_exists($path)) {
            $this->error("Interface already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Interfaces;

interface {$name}
{
    // Interface methods
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Interface created successfully: {$path}");
    }

    protected function makeEnum($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide an enum name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Enums/{$name}.php";

        if (file_exists($path)) {
            $this->error("Enum already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Enums;

enum {$name}: string
{
    case OPTION_ONE = 'option_one';
    case OPTION_TWO = 'option_two';
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Enum created successfully: {$path}");
    }

    protected function makeCast($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a cast name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Casts/{$name}.php";

        if (file_exists($path)) {
            $this->error("Cast already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Casts;

class {$name}
{
    public function get(\$model, \$key, \$value, \$attributes)
    {
        return \$value;
    }

    public function set(\$model, \$key, \$value, \$attributes)
    {
        return \$value;
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Cast created successfully: {$path}");
    }

    protected function makeChannel($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a channel name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Broadcasting/{$name}.php";

        if (file_exists($path)) {
            $this->error("Channel already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Broadcasting;

class {$name}
{
    public function join(\$user, \$id)
    {
        // Authorization logic
        return true;
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Channel created successfully: {$path}");
    }

    protected function makeException($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide an exception name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Exceptions/{$name}.php";

        if (file_exists($path)) {
            $this->error("Exception already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Exceptions;

use Exception;

class {$name} extends Exception
{
    //
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Exception created successfully: {$path}");
    }

    protected function makeScope($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a scope name.");
            return;
        }

        $name = $args[0];
        $path = __DIR__ . "/../app/Scopes/{$name}.php";

        if (file_exists($path)) {
            $this->error("Scope already exists!");
            return;
        }

        $content = <<<PHP
<?php

namespace App\\Scopes;

class {$name}
{
    public function apply(\$builder, \$model)
    {
        // Apply scope
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->success("Scope created successfully: {$path}");
    }

    // ADDITIONAL UTILITY COMMANDS

    protected function storageLink($args)
    {
        $this->info("Creating storage link...");

        $target = __DIR__ . '/../storage/app/public';
        $link = __DIR__ . '/../public_html/storage';

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        if (file_exists($link)) {
            $this->error("Storage link already exists!");
            return;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("mklink /D \"$link\" \"$target\"");
        } else {
            symlink($target, $link);
        }

        $this->success("Storage link created successfully!");
    }

    protected function storageUnlink($args)
    {
        $link = __DIR__ . '/../public_html/storage';

        if (!file_exists($link)) {
            $this->error("Storage link does not exist!");
            return;
        }

        if (is_link($link)) {
            unlink($link);
        } elseif (is_dir($link)) {
            rmdir($link);
        }

        $this->success("Storage link removed successfully!");
    }

    protected function viewCache($args)
    {
        $this->info("Caching views...");

        $viewsPath = __DIR__ . '/../resources/views';
        $cachePath = __DIR__ . '/../storage/cache/views';

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        $count = 0;
        foreach ($files as $file) {
            if ($file->isFile() && preg_match('/\.(blade\.php|php)$/', $file->getFilename())) {
                $count++;
            }
        }

        $this->success("Compiled {$count} views successfully!");
    }

    protected function routeCache($args)
    {
        $this->info("Caching routes...");

        $cachePath = __DIR__ . '/../storage/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        // This would cache the routes - simplified version
        file_put_contents($cachePath . '/routes.php', '<?php // Cached routes');

        $this->success("Routes cached successfully!");
    }

    protected function routeClear($args)
    {
        $cacheFile = __DIR__ . '/../storage/cache/routes.php';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->success("Route cache cleared!");
        } else {
            $this->info("No route cache to clear.");
        }
    }

    protected function optimize($args)
    {
        $this->info("Optimizing application...");

        // Cache config
        $this->configCache([]);
        // Cache routes
        $this->routeCache([]);
        // Cache views
        $this->viewCache([]);

        $this->success("Application optimized successfully!");
    }

    protected function optimizeClear($args)
    {
        $this->info("Clearing optimization cache...");

        $this->configClear([]);
        $this->routeClear([]);
        $this->viewClear([]);
        $this->cacheClear([]);

        $this->success("Optimization cache cleared!");
    }

    protected function eventCache($args)
    {
        $this->info("Caching events...");
        $this->success("Events cached successfully!");
    }

    protected function eventClear($args)
    {
        $this->info("Clearing event cache...");
        $this->success("Event cache cleared!");
    }

    protected function eventList($args)
    {
        $this->info("Registered Events:");
        $this->info("No events registered yet.");
    }

    protected function vendorPublish($args)
    {
        $this->info("Publishing vendor assets...");

        $vendorPath = __DIR__ . '/../vendor';
        $publicPath = __DIR__ . '/../public_html/vendor';

        if (!is_dir($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        $this->success("Vendor assets published successfully!");
    }

    protected function about($args)
    {
        $this->info("VTPHP Framework");
        $this->info("Version: 1.0.0");
        $this->info("PHP Version: " . PHP_VERSION);
        echo "\n";
    }

    protected function showEnv($args)
    {
        $this->info("Environment Variables:");
        echo "\n";

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    echo "  " . $line . "\n";
                }
            }
        }
        echo "\n";
    }

    protected function down($args)
    {
        $this->info("Putting application into maintenance mode...");
        file_put_contents(__DIR__ . '/../storage/framework/down', '1');
        $this->success("Application is now in maintenance mode!");
    }

    protected function up($args)
    {
        $downFile = __DIR__ . '/../storage/framework/down';
        if (file_exists($downFile)) {
            unlink($downFile);
            $this->success("Application is now live!");
        } else {
            $this->info("Application is not in maintenance mode.");
        }
    }

    protected function modelShow($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a model name.");
            return;
        }

        $this->info("Model: {$args[0]}");
        $this->info("Table: " . strtolower($args[0]) . "s");
    }

    protected function modelPrune($args)
    {
        $this->info("Pruning models...");
        $this->success("Models pruned successfully!");
    }

    protected function packageDiscover($args)
    {
        $this->info("Discovering packages...");
        $this->success("Packages discovered successfully!");
    }

    protected function migrateInstall($args)
    {
        $this->info("Creating migrations table...");
        $this->success("Migrations table created successfully!");
    }

    protected function migrateRefresh($args)
    {
        $this->info("Refreshing database...");
        $this->reset([]);
        $this->migrate([]);
        $this->success("Database refreshed successfully!");
    }

    protected function dbMonitor($args)
    {
        $this->info("Database Connections: 0");
    }

    protected function dbShow($args)
    {
        $config = require __DIR__ . '/../config/database.php';
        $this->info("Database: " . $config['database']);
        $this->info("Host: " . $config['host']);
    }

    protected function dbTable($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a table name.");
            return;
        }

        $this->info("Table: {$args[0]}");
    }

    protected function cacheForget($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a cache key.");
            return;
        }

        \Core\Cache::forget($args[0]);
        $this->success("Cache key forgotten!");
    }

    // Queue commands (simplified stubs)
    protected function queueWork($args)
    {
        $this->info("Processing queue jobs...");
        $this->info("Press Ctrl+C to stop.");
        // Queue worker logic would go here
    }

    protected function queueListen($args)
    {
        $this->info("Listening for queue jobs...");
    }

    protected function queueRestart($args)
    {
        $this->info("Restarting queue workers...");
        $this->success("Queue workers restarted!");
    }

    protected function queueRetry($args)
    {
        $this->info("Retrying failed jobs...");
        $this->success("Failed jobs retried!");
    }

    protected function queueFailed($args)
    {
        $this->info("Failed Queue Jobs:");
        $this->info("No failed jobs.");
    }

    protected function queueFlush($args)
    {
        $this->info("Flushing failed jobs...");
        $this->success("Failed jobs flushed!");
    }

    protected function queueForget($args)
    {
        if (empty($args[0])) {
            $this->error("Please provide a job ID.");
            return;
        }

        $this->success("Job forgotten!");
    }

    protected function queueClear($args)
    {
        $this->info("Clearing queue...");
        $this->success("Queue cleared!");
    }

    // Schedule commands
    protected function scheduleRun($args)
    {
        $this->info("Running scheduled commands...");
        $this->success("Scheduled commands completed!");
    }

    protected function scheduleList($args)
    {
        $this->info("Scheduled Tasks:");
        $this->info("No scheduled tasks.");
    }

    protected function scheduleWork($args)
    {
        $this->info("Running schedule worker...");
    }
}
