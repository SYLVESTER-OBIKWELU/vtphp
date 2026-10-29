# Migrations & seeders

A migration is a PHP file that describes one change to your database, such
as "create a `posts` table" or "add a `slug` column". Keeping these changes
in files means every copy of your app (yours, a teammate's, the live server)
can build the exact same database with one command. A seeder is a PHP file
that fills tables with starting data.

## The quick version

```bash
php forge make:migration create_posts_table
```

Open the new file in `database/migrations/` and describe the columns:

```php
// database/migrations/2026_10_29_120000_create_posts_table.php
use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();                      // auto-incrementing "id" column
            $table->string('title');
            $table->text('body')->nullable();  // may be empty
            $table->timestamps();              // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

Then apply it:

```bash
php forge migrate
```

The `posts` table now exists.

## How migrations work

Every migration has two methods:

- **`up()`** makes the change.
- **`down()`** undoes it. It's used when you roll back.

The file name starts with a timestamp, so migrations always run in the order
you created them. VtPhp records which ones have run in a `migrations` table.
`php forge migrate` only runs the new ones, so it's always safe to run again.

## Creating a migration

The name you give `make:migration` decides what the file starts with:

| Command | You get |
| --- | --- |
| `php forge make:migration create_posts_table` | `Schema::create('posts', ...)` with `id` and timestamps |
| `php forge make:migration add_slug_to_posts_table` | `Schema::table('posts', ...)` to change an existing table |
| `php forge make:migration backfill_prices` | empty `up()` and `down()` methods |

The table is guessed from the name: `create_x_table` creates `x`, and names
ending in `_to_x_table`, `_from_x_table` or `_in_x_table` change `x`. You can
also say it directly with `--create=posts` or `--table=posts`.

`php forge make:model Post -m` creates the model and its
`create_posts_table` migration together.

## Describing columns

Inside `Schema::create()` or `Schema::table()`, each line adds a column:

```php
$table->id();                              // primary key
$table->string('title');                   // short text (255 characters)
$table->string('email')->unique();         // no two rows may share a value
$table->text('body');                      // long text
$table->integer('views')->default(0);      // whole number, starts at 0
$table->unsignedInteger('price');          // whole number, never negative
$table->boolean('published')->default(false);
$table->timestamp('published_at')->nullable();
$table->json('settings')->nullable();
$table->foreignId('user_id')->nullable();  // points at a row in another table
$table->timestamps();                      // created_at + updated_at
```

`->nullable()` means the column may be empty. `->default(...)` sets the
value used when you don't provide one. These are Laravel's column types, so
anything in the
[Laravel migration docs](https://laravel.com/docs/migrations#available-column-types)
works here too.

## Changing a table later

Never edit a migration that has already run on another machine. Make a new
one instead:

```bash
php forge make:migration add_slug_to_posts_table
```

```php
// database/migrations/2026_10_30_090000_add_slug_to_posts_table.php
public function up(): void
{
    Schema::table('posts', function (Blueprint $table): void {
        $table->string('slug')->nullable();
    });
}

public function down(): void
{
    Schema::table('posts', function (Blueprint $table): void {
        $table->dropColumn('slug');
    });
}
```

## Running and undoing migrations

```bash
php forge migrate            # run every migration that hasn't run yet
php forge migrate:status     # list migrations and whether each has run
php forge migrate:rollback   # undo the most recent batch
```

A batch is the group of migrations that ran together in one `migrate`.
`migrate:rollback` calls `down()` on that group, newest first.

The app ships with migrations for `users`, `password_reset_tokens`,
`sessions` and `cache`. The last two store logins and cached data in the
database, so run `php forge migrate` before you first start the app.

## Seeders

A seeder fills tables with data, such as sample users for development.

```bash
php forge make:seeder PostSeeder
```

```php
// database/seeders/PostSeeder.php
namespace Database\Seeders;

use App\Models\Post;
use VtPhp\Database\Seeder;

final class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::query()->firstOrCreate(['title' => 'Hello world'], ['body' => 'My first post.']);
    }
}
```

`firstOrCreate()` only inserts the row if it isn't there, so running the
seeder twice doesn't create duplicates.

`php forge db:seed` always runs `database/seeders/DatabaseSeeder.php`. Add
your seeder to it:

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call(UserSeeder::class);
    $this->call(PostSeeder::class);
}
```

```bash
php forge db:seed
```

The included `UserSeeder` creates two sample users, `ada@example.com` and
`grace@example.com`, both with the password `password`. It never runs in
production.

## Going further

### Production safety

When `APP_ENV=production`, the commands that change data refuse to run
unless you add `--force`: `migrate`, `migrate:rollback`, `db:seed` and
`key:generate`. This stops accidents on the live server:

```bash
php forge migrate --force
```

### Other `Schema` methods

```php
Schema::rename('posts', 'articles');
Schema::drop('articles');
Schema::hasTable('posts');                  // true or false
Schema::hasColumn('posts', 'slug');         // true or false
Schema::connection('other')->hasTable('x'); // a different database connection
```

### Raw SQL

For anything the schema builder can't express, `$this->db` inside a migration
is a database connection that runs raw SQL:

```php
public function up(): void
{
    $this->db->executeStatement('CREATE INDEX posts_title_idx ON posts (title)');
}
```

### Settings

The tracking table name and the migrations folder are set in
`config/database.php` under `migrations`.

## Common mistakes

- **"Nothing to migrate."** Every migration has already run. Create a new one
  for your change.
- **You edited an old migration and nothing changed.** Migrations run once.
  Roll back first (`migrate:rollback`), or better, add a new migration.
- **"A ... migration already exists."** `make:migration` refuses duplicate
  names. Pick a more specific name.
- **"The application is in production... Re-run with --force."** That's the
  production safety check. Add `--force` if you're sure.
- **The app errors about a missing `sessions` or `cache` table.** Run
  `php forge migrate`.

## Next steps

- [Models](models.md): read and write the tables you've created.
- [Your first API](your-first-api.md): a migration, model and endpoints together.
- [Deploying](deploying.md): running migrations on a live server.
