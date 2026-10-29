# CLI (`forge`)

`forge` is a `symfony/console` application (entry point at the project root)
for day-to-day tasks and code generation, modeled on Laravel's Artisan.

```powershell
php forge                     # list all available commands
php forge about               # application info
php forge serve               # run the PHP built-in dev server
php forge route:list          # list all registered routes
php forge key:generate        # generate and write APP_KEY to .env
```

## Generators (`make:*`)

```powershell
php forge make:model Product -a                 # model + migration, seeder, resource controller,
                                                # form requests and JSON resource (see Models)
php forge make:controller ProductController     # also --api, --model=Product, -R, -j (see Controllers)
php forge make:request StoreProductRequest      # app/Http/Requests
php forge make:resource Product                 # app/Http/Resources/ProductResource.php (--model=Product)
php forge make:middleware EnsureJson            # app/Http/Middleware
php forge make:migration create_products_table  # infers Schema::create/table (see Migrations)
php forge make:seeder ProductSeeder             # database/seeders
php forge make:mail OrderShipped                # app/Mail
```

Generators never overwrite an existing file. They print an error and exit
with status 1 instead.

Each generator renders a stub from the framework's `src/stubs/`. Run
`php forge stub:publish` to copy the stubs into your project's `stubs/`
folder. Generators use a published stub instead of the framework default, so
you can customize them. `stub:publish --force` re-publishes over your changes.

## Database

```powershell
php forge migrate            # run pending migrations
php forge migrate:status     # show ran vs. pending migrations
php forge migrate:rollback   # roll back the last batch
php forge db:seed            # run database seeders
```

See [Migrations & seeders](migrations.md).

With `APP_ENV=production`, `migrate`, `migrate:rollback`, `db:seed` and
`key:generate` refuse to run unless you add `--force`. To protect a command
of your own the same way, set `protected bool $destructive = true;` on it.

## Cache & sessions

```powershell
php forge cache:clear        # flush the default cache store
php forge cache:clear file   # flush a named store
php forge session:gc         # delete expired sessions now
```

See [Sessions, cookies & cache](sessions-cookies-cache.md).

## Adding your own command

Extend `VtPhp\Console\Command`, annotate the class with
`#[AsCommand(name: '...', description: '...')]`, and implement `handle(): int`:

```php
use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'products:sync', description: 'Sync products from the supplier feed')]
final class SyncProductsCommand extends Command
{
    protected function handle(): int
    {
        $this->line('Syncing...');
        $this->call('db:seed');   // run another command, like Laravel's $this->call()

        return self::SUCCESS;
    }
}
```

The base class gives you `$this->input`, `$this->output`, `$this->io` (a
`SymfonyStyle`), `info()`, `line()`, `error()`, `call()`, and `generate()` for
rendering a stub into a file. Register the command in
`src/Foundation/Console/Kernel.php`'s command list so `forge` picks it up.
