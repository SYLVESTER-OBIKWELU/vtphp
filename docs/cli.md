# CLI (`forge`)

`forge` is a `symfony/console` application (entry point at project root) for
day-to-day tasks and code generation.

```powershell
php forge                     # list all available commands
php forge about               # application info
php forge serve               # run the PHP built-in dev server
php forge route:list          # list all registered routes
php forge key:generate        # generate and write APP_KEY to .env
```

## Generators (`make:*`)

Each generator writes a stub from `stubs/` into the appropriate `app/`
subdirectory, filling in the class name and namespace.

```powershell
php forge make:controller ProductController
php forge make:model Product
php forge make:resource ProductResource
php forge make:middleware EnsureTokenIsValid
php forge make:migration create_products_table
php forge make:seeder ProductSeeder
php forge make:mail OrderShipped
```

## Database

```powershell
php forge migrate            # run pending migrations
php forge migrate:status     # show ran vs. pending migrations
php forge migrate:rollback   # roll back the last batch
php forge db:seed            # run database seeders
```

See [Migrations & seeders](migrations.md) for what the generated files look
like and how the migration runner works.

## Adding your own command

Add a new class under `src/Console/Commands/` (or `app/Console/Commands/`
for app-specific commands) extending `VtPhp\Console\Command`, annotate it
with `#[AsCommand(name: '...', description: '...')]`, and implement
`handle(): int`:

```php
use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'products:sync', description: 'Sync products from the supplier feed')]
final class SyncProductsCommand extends Command
{
    protected function handle(): int
    {
        $this->line('Syncing...');
        // ...

        return self::SUCCESS;
    }
}
```

Register it in `src/Foundation/Console/Kernel.php`'s command list so
`forge` picks it up.
