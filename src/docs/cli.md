# The `forge` command-line tool

`forge` is VtPhp's command-line tool, run from your project folder with
`php forge ...`. It starts a development server, creates files for you,
updates your database and handles other everyday jobs. If you know Laravel,
it's VtPhp's version of Artisan.

## The quick version

```bash
php forge                       # list every command
php forge serve                 # run the app at http://127.0.0.1:8000
php forge make:model Post -a    # create a model plus everything around it
php forge migrate               # update the database
```

Add `--help` to any command to see its options, for example
`php forge make:model --help`.

## Everyday commands

```bash
php forge about          # app name, environment, debug mode, PHP version
php forge serve          # start a development server (Ctrl+C to stop)
php forge route:list     # show every URL your app answers
php forge swagger:generate  # write the API docs (OpenAPI) to storage/api-docs/openapi.json
php forge key:generate   # create a new APP_KEY in .env
```

`serve` uses PHP's built-in web server, which is for development only. Change
where it listens with `--host` and `--port`:

```bash
php forge serve --host=0.0.0.0 --port=8080
```

`route:list` prints each route's method, URL, name and the controller method
it runs. It's the quickest way to check that a route exists.

`key:generate` writes a random secret into `APP_KEY` in your `.env` file.
VtPhp uses it to sign links, such as email-verification links. The `.env`
file must already exist (copy `.env.example`).

## Creating files (`make:` commands)

These commands write a new class from a template, in the right folder with
the right namespace. They never overwrite a file that already exists.

| Command | Creates |
| --- | --- |
| `php forge make:model Post` | `app/Models/Post.php` |
| `php forge make:controller PostController` | `app/Http/Controllers/PostController.php` |
| `php forge make:request StorePostRequest` | `app/Http/Requests/StorePostRequest.php` |
| `php forge make:resource Post` | `app/Http/Resources/PostResource.php` |
| `php forge make:middleware EnsureJson` | `app/Http/Middleware/EnsureJson.php` |
| `php forge make:migration create_posts_table` | `database/migrations/<date>_create_posts_table.php` |
| `php forge make:seeder PostSeeder` | `database/seeders/PostSeeder.php` |
| `php forge make:mail OrderShipped` | `app/Mail/OrderShipped.php` |
| `php forge make:job SendInvoice` | `app/Jobs/SendInvoice.php` |
| `php forge make:policy PostPolicy --model=Post` | `app/Policies/PostPolicy.php` (leave out `--model` for an empty policy) |

### Making a model with everything around it

`make:model` can create the other files a model usually needs. Combine the
short flags freely, for example `-mcr`:

| Flag | Also creates |
| --- | --- |
| `-m`, `--migration` | a migration that creates the table |
| `-s`, `--seed` | a seeder |
| `-c`, `--controller` | an empty controller |
| `-r`, `--resource` (or `--api`) | a full controller with `index`, `store`, `show`, `update` and `destroy`, wired to the model |
| `-R`, `--requests` | `StorePostRequest` and `UpdatePostRequest` form requests |
| `-j`, `--json-resource` | `PostResource`, which shapes the JSON output |
| `-p`, `--policy` | `PostPolicy`, the permission rules (see [Authorization](authorization.md)) |
| `-a`, `--all` | all of the above |

```bash
php forge make:model Post -a
```

When it creates a full controller, the command prints the line to add to
`routes/api.php`:

```php
// routes/api.php
$router->apiResource('posts', PostController::class);
```

Model factories, which Laravel's `-a` also creates, aren't available in
VtPhp yet.

### Controller options

| Option | Result |
| --- | --- |
| *(none)* | an empty controller |
| `--api` | the five resource methods, ready for you to fill in |
| `--model=Post` (or `-m Post`) | the five methods, already using the `Post` model |
| `--model=Post -R` | ...and type-hinted `StorePostRequest` / `UpdatePostRequest`, which it creates |
| `--model=Post -j` | ...and returning `PostResource`, which it creates |

Careful: `-m` means `--model` for `make:controller`, but `--migration` for
`make:model`.

### Migration names

`make:migration` guesses what you want from the name:

- `create_posts_table` starts a file that creates the `posts` table.
- `add_slug_to_posts_table` (or `..._from_..._table`, `..._in_..._table`)
  starts a file that changes `posts`.
- Any other name gives empty `up()` and `down()` methods.

Use `--create=posts` or `--table=posts` to name the table yourself.

### Resource options

`make:resource Post` turns the object into an array with `(array)`. For an
Eloquent model, use `php forge make:resource Post --model=Post`, which uses
the model's own `toArray()`.

## Database commands

```bash
php forge migrate            # run new migrations
php forge migrate:status     # list migrations and whether each has run
php forge migrate:rollback   # undo the most recent batch of migrations
php forge db:seed            # fill the database using DatabaseSeeder
```

See [Migrations and seeders](migrations.md).

## Cache and session commands

```bash
php forge cache:clear        # empty the default cache
php forge cache:clear file   # empty one named cache store
php forge session:gc         # delete expired sessions now
```

Expired sessions are also cleaned up automatically on a small share of
requests. See [Sessions, cookies and cache](sessions-cookies-cache.md).

## Queue commands

```bash
php forge queue:work         # run queued jobs until stopped (Ctrl+C)
php forge queue:work --queue=emails,default --tries=3
php forge queue:failed       # list jobs that failed every try
php forge queue:retry 5      # put failed job 5 back on the queue (or: all)
php forge queue:forget 5     # delete failed job 5
php forge queue:flush        # delete all failed jobs
php forge queue:clear        # delete jobs still waiting on the queue
```

`queue:work` options: `--queue`, `--tries`, `--sleep`, `--once`,
`--stop-when-empty`, `--max-jobs` and `--max-time`. See [Queues](queues.md).

## API token commands

```bash
php forge tokens:prune       # delete expired API tokens
```

Run it regularly, for example daily from cron. See
[Authentication](authentication.md#api-tokens-for-mobile-apps-scripts-and-other-servers).

## Production safety: `--force`

When `APP_ENV=production`, commands that change or destroy data stop and
ask you to confirm by adding `--force`:

```bash
php forge migrate --force
```

This applies to `migrate`, `migrate:rollback`, `db:seed`, `key:generate`,
`queue:flush` and `queue:clear`. Without `--force` you'll see:

```text
The application is in production (APP_ENV=production). Re-run with --force if you really mean it.
```

## Going further

### Customizing the templates

The `make:` commands build files from templates (called stubs) stored in
the framework's `src/stubs/` folder. To change them for your project:

```bash
php forge stub:publish
```

This copies every stub into a `stubs/` folder in your project. Edit those
copies; `make:` commands use your version from then on. Running
`stub:publish` again keeps your edits. Add `--force` to replace them with
fresh copies.

### Writing your own command

A command is a class that extends `VtPhp\Console\Command`, with an
`#[AsCommand]` attribute and a `handle()` method:

```php
// app/Console/Commands/SyncProductsCommand.php
namespace App\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'products:sync', description: 'Sync products from the supplier feed')]
final class SyncProductsCommand extends Command
{
    protected function handle(): int
    {
        $this->line('Syncing...');
        // ...your code...
        $this->info('Done.');

        return self::SUCCESS;
    }
}
```

Inside a command you can use:

- `$this->line('text')` for plain output, `$this->info('text')` for a success
  box and `$this->error('text')` for an error box;
- `$this->io` for richer output such as tables
  (`$this->io->table($headers, $rows)`);
- `$this->input` to read arguments and options;
- `$this->call('cache:clear')` to run another command;
- `$this->app` to reach the container.

To add arguments or options, define `configure()`:

```php
// app/Console/Commands/SyncProductsCommand.php
use Symfony\Component\Console\Input\InputArgument;

protected function configure(): void
{
    $this->addArgument('supplier', InputArgument::REQUIRED, 'Which supplier to sync');
}
```

To protect your command like `migrate`, add
`protected bool $destructive = true;`. It then gets a `--force` option
automatically and refuses to run in production without it.

**Registering the command.** There's no automatic discovery yet. Add your
class to the `$commands` list in `src/Foundation/Console/Kernel.php`:

```php
// src/Foundation/Console/Kernel.php
private array $commands = [
    // ...the built-in commands...
    \App\Console\Commands\SyncProductsCommand::class,
];
```

## Common mistakes

- **"... already exists."** `make:` commands never overwrite. Delete the old
  file first, or pick another name.
- **A new `make:migration` name is refused.** Migration names must be
  unique. Use a more specific name.
- **`key:generate` says `.env` is missing.** Copy `.env.example` to `.env`
  first.
- **"Re-run with --force".** You're in production. Add `--force` only if you
  really mean it.
- **Your own command doesn't appear in `php forge`.** Register it in
  `src/Foundation/Console/Kernel.php`.

## Next steps

- [Getting started](getting-started.md): install and run the app.
- [Your first API](your-first-api.md): use `make:model -a` end to end.
- [Migrations and seeders](migrations.md): what the database commands do.
- [Testing](testing.md): check your code automatically.
