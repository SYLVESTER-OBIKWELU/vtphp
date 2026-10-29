# Models & Eloquent

Models use `illuminate/database`'s Eloquent ORM
(`Illuminate\Database\Eloquent\Model`), the same ActiveRecord API as
Laravel. `VtPhp\Database\EloquentManager` boots the Eloquent connection from
`config/database.php` at startup, so models work with no extra setup.

Like Laravel, controllers use models directly. There are no repository
interfaces or service classes to write.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
    protected $casts = ['email_verified_at' => 'datetime'];

    // Hash plain-text passwords on assignment (like Laravel's `hashed` cast).
    protected function password(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null || password_get_info($value)['algoName'] !== 'unknown'
                ? $value
                : password_hash($value, PASSWORD_BCRYPT),
        );
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
}
```

Domain logic that belongs to one model goes on the model, Laravel's "fat
model" style. For example, `User::verificationUrl()` and
`PasswordResetToken::issueFor($user)` / `->matches($token)` / `->isExpired()`.

## Querying

Every Eloquent feature is available: `User::query()->where(...)->get()`,
`->find($id)`, `->findOrFail($id)` (rendered as a 404), `->firstOrCreate(...)`,
`$user->update([...])`, relationships (`hasMany`/`belongsTo`/…), scopes,
casts, and so on. See the
[Eloquent documentation](https://laravel.com/docs/eloquent) for the full API.
This framework doesn't wrap or restrict it.

The examples use `User::query()->create(...)` rather than `User::create(...)`.
Both work at runtime, but PHPStan can't see Eloquent's static magic methods
without an extension, so `query()` keeps static analysis clean.

`create()` and `update()` only fill attributes listed in `$fillable`. Pair
them with a form request's `validated()`, which only contains fields that
have rules (see [Validation](validation.md)).

Timestamps use the app's timezone (`APP_TIMEZONE`, default `UTC`). The
timezone is set during bootstrap, so Eloquent's `datetime` casts agree with
the `now()` helper.

## Generating models

`make:model` creates an Eloquent model and, like Laravel, can scaffold
everything around it:

```powershell
php forge make:model Product          # app/Models/Product.php
php forge make:model Product -a       # everything below
php forge make:model Product -mcr     # migration + resource controller
```

| Flag                  | Generates                                                                                      |
| --------------------- | ---------------------------------------------------------------------------------------------- |
| `-m`, `--migration`   | `database/migrations/…_create_products_table.php`                                              |
| `-s`, `--seed`        | `database/seeders/ProductSeeder.php`                                                           |
| `-c`, `--controller`  | `app/Http/Controllers/ProductController.php` (an empty controller)                              |
| `-r`, `--resource`    | Makes the controller an API resource controller bound to the model (also `--api`)             |
| `-R`, `--requests`    | `app/Http/Requests/StoreProductRequest.php` and `UpdateProductRequest.php`                      |
| `-j`, `--json-resource` | `app/Http/Resources/ProductResource.php`                                                     |
| `-a`, `--all`         | All of the above: migration, seeder, resource controller, requests and JSON resource            |

With `-r` (or `-a`), the generated controller already type-hints the
generated requests and returns the generated resource. The command prints
the line to add to `routes/api.php`:

```php
$router->apiResource('products', ProductController::class);
```

Fill in the migration columns, `$fillable`, and the request `rules()`, then
run `php forge migrate`. Factories and policies (part of Laravel's `-a`)
aren't available yet.
