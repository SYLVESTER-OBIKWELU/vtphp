# Migrations & seeders

Migrations use a small Doctrine DBAL-based runner (`VtPhp\Database\Migrations\Migrator`)
— independent of Eloquent, which is only used for querying/models (see
[Models & Eloquent](models.md)).

## Creating a migration

```powershell
php forge make:migration create_products_table
```

This creates a timestamped file in `database/migrations/` returning an
anonymous class that extends `VtPhp\Database\Migrations\Migration`:

```php
use Doctrine\DBAL\Schema\Schema;
use VtPhp\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $schema = new Schema();
        $table = $schema->createTable('products');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('price', 'integer');
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        foreach ($schema->toSql($this->db->getDatabasePlatform()) as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    public function down(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS products');
    }
};
```

`$this->db` is a Doctrine DBAL `Connection` (set via `setConnection()` before
`up()`/`down()` run), so any DBAL `Schema`/`executeStatement()` call is fair
game — indexes, foreign keys, column changes, raw SQL, etc.

## Running migrations

```powershell
php forge migrate            # run all pending migrations
php forge migrate:status     # show ran vs. pending
php forge migrate:rollback   # roll back the last batch
```

Migrations are tracked in a `migrations` table (configurable via
`config/database.php`'s `migrations.table`).

## Seeders

```powershell
php forge make:seeder ProductSeeder
```

Seeders are plain classes extending `VtPhp\Database\Seeder`, and use
Eloquent models directly (seeding is data population, not schema
management):

```php
namespace Database\Seeders;

use App\Models\Product;
use VtPhp\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->firstOrCreate(
            ['name' => 'Widget'],
            ['price' => 999],
        );
    }
}
```

Call other seeders from within `run()` via `$this->call(OtherSeeder::class)`.
Run all seeders with:

```powershell
php forge db:seed
```

`database/seeders/DatabaseSeeder.php` (or whichever seeder `db:seed` is
configured to call) is the entry point — register new seeders there via
`$this->call(...)` if you want them to run as part of the default `db:seed`.
