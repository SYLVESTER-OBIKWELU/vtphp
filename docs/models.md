# Models & Eloquent

Models use `illuminate/database`'s Eloquent ORM
(`Illuminate\Database\Eloquent\Model`) — the same ActiveRecord-style API as
Laravel. `VtPhp\Database\EloquentManager` boots the Eloquent "Capsule"
connection from `config/database.php` at application start, so models work
out of the box with no extra setup.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
final class User extends Model
{
    /** @var array<int, string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var array<int, string> */
    protected $hidden = ['password'];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
}
```

Generate a new model with:

```powershell
php forge make:model Product
```

## Repository pattern

Controllers/services don't usually touch Eloquent models directly — the
sample code wraps each model behind a repository interface (e.g.
`App\Repositories\UserRepositoryInterface`, bound to
`App\Repositories\EloquentUserRepository` in `AppServiceProvider`). This
keeps `app/Services/*` and `app/Controllers/*` decoupled from the concrete
ORM, and is the recommended pattern for new models too:

```php
interface ProductRepositoryInterface
{
    public function find(int $id): ?Product;
    public function all(): array;
    public function create(array $attributes): Product;
}
```

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(ProductRepositoryInterface::class, EloquentProductRepository::class);
```

## Querying

Standard Eloquent query builder methods are all available:
`User::query()->where(...)->get()`, `User::find($id)`,
`User::query()->firstOrCreate([...], [...])`, relationships
(`hasMany`/`belongsTo`/...), etc. — see the
[Eloquent documentation](https://laravel.com/docs/eloquent) for the full API,
since this framework doesn't wrap or restrict it.

Timestamps use the app's configured timezone (`APP_TIMEZONE`, default `UTC`)
— `date_default_timezone_set()` is called during bootstrap specifically so
Eloquent's `datetime` casts (`Illuminate\Support\Carbon`) agree with the
framework's own `now()` helper.
