# Migrations & seeders

## Creating a migration

```powershell
php forge make:migration create_products_table          # Schema::create('products', …)
php forge make:migration add_price_to_products_table    # Schema::table('products', …)
php forge make:migration backfill_prices                # empty up()/down()
php forge make:migration anything --create=products     # explicit table
```

Like Laravel, the table is inferred from the name: `create_x_table` creates
`x`, and `…_to_x_table` / `…_from_x_table` / `…_in_x_table` alter it.
`make:model Product -m` creates `create_products_table` for you.

The file in `database/migrations/` returns an anonymous class using
Laravel's schema builder through `VtPhp\Database\Schema`:

```php
use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->foreignId('category_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

Every `Blueprint` column type and modifier from Laravel is available
(`string`, `text`, `boolean`, `json`, `timestamp`, `->nullable()`,
`->unique()`, `->default()`, indexes, foreign keys, …). `Schema` also has
`table()`, `rename()`, `drop()`, `hasTable()`, `hasColumn()` and
`connection($name)`.

For anything the builder can't express, `$this->db` is a Doctrine DBAL
`Connection`, so you can run raw SQL with `$this->db->executeStatement(...)`.

## Running migrations

```powershell
php forge migrate            # run all pending migrations
php forge migrate:status     # show ran vs. pending
php forge migrate:rollback   # roll back the last batch
```

Migrations are tracked by file name in a `migrations` table (configurable via
`config/database.php`'s `migrations.table`), grouped into batches. A rollback
reverts the most recent batch in reverse order.

## Seeders

```powershell
php forge make:seeder ProductSeeder     # or: php forge make:model Product -s
```

Seeders extend `VtPhp\Database\Seeder` and use Eloquent models directly:

```php
namespace Database\Seeders;

use App\Models\Product;
use VtPhp\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->firstOrCreate(['name' => 'Widget'], ['price' => 999]);
    }
}
```

Run them with `php forge db:seed`. That calls
`database/seeders/DatabaseSeeder.php`, so register new seeders there with
`$this->call(ProductSeeder::class)`.
