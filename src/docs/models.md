# Models

A model is a PHP class that represents one table in your database. Instead of
writing SQL by hand, you ask the model for rows (`User::query()->find(1)`) and
change them through it (`$user->update([...])`). VtPhp uses Eloquent for
this, the same model system Laravel uses.

## The quick version

```php
// anywhere in your app, e.g. a controller
use App\Models\User;

$user = User::query()->create([
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'password' => 'secret-password',   // hashed automatically, see below
]);

$same = User::query()->find($user->id);              // look it up by id
$ada  = User::query()->where('email', 'ada@example.com')->first();

$ada->update(['name' => 'Countess Ada']);            // change it
$ada->delete();                                       // remove it
```

Each row comes back as a `User` object. Columns are properties:
`$user->name`, `$user->email`.

## Before you start: the table must exist

A model only talks to a table; it doesn't create one. Tables are created by
[migrations](migrations.md). The `users` table already exists once you've run:

```bash
php forge migrate
```

## Creating a model

Generate a new model class with the command line tool:

```bash
php forge make:model Post
```

This creates `app/Models/Post.php`:

```php
// app/Models/Post.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        //
    ];
}
```

By convention, a model named `Post` uses a table named `posts` (the plural,
in snake_case). So `BlogPost` uses `blog_posts`.

You usually want more than the model. `make:model` can create the related
files at the same time:

```bash
php forge make:model Post -a
```

`-a` (all) also creates a migration, a seeder, a controller, two form
requests and a JSON resource. Each piece is explained in
[Your first API](your-first-api.md). The individual flags are:

| Flag | Creates |
| --- | --- |
| `-m` | a migration that creates the `posts` table |
| `-s` | a seeder (`database/seeders/PostSeeder.php`) |
| `-c` | an empty controller |
| `-r` (or `--api`) | a full controller with index/store/show/update/destroy, already using the model |
| `-R` | `StorePostRequest` and `UpdatePostRequest` for validation |
| `-j` | a JSON resource (`PostResource`) that shapes the API output |
| `-a` | all of the above |

Flags combine: `php forge make:model Post -mcr` makes the model, a migration
and a resource controller.

## Saving data safely: `$fillable`

`$fillable` lists the columns you're allowed to set in bulk with `create()`
or `update()`. Anything not listed is silently ignored. This protects you
from users sending extra fields like `is_admin` or `id`.

```php
// app/Models/Post.php
protected $fillable = ['title', 'body'];
```

```php
Post::query()->create(['title' => 'Hello', 'body' => '...', 'is_admin' => true]);
// "is_admin" is ignored because it isn't in $fillable.
```

Pair this with a [form request](validation.md): its `validated()` method only
returns fields that have rules, so `create($request->validated())` is safe.

## Reading data

```php
use App\Models\Post;

Post::query()->get();                              // every row
Post::query()->find(5);                            // by id, or null
Post::query()->findOrFail(5);                      // by id, or a 404 response
Post::query()->where('published', true)->get();    // filtered
Post::query()->orderBy('created_at', 'desc')->first();
Post::query()->where('title', 'like', '%php%')->count();
```

`findOrFail()` is handy in controllers: if the row doesn't exist, the user
gets a `404` with the message `Post not found.` and your code stops there.

In controllers you often don't need either. If a route has `{post}` in its
path and your method asks for `Post $post`, the framework looks the row up for
you (see [Routing](routing.md#route-model-binding-get-the-record-not-the-id)).

## Changing and deleting data

```php
$post = Post::query()->findOrFail(5);

$post->update(['title' => 'New title']);   // save new values

$post->title = 'Another title';            // or set properties...
$post->save();                             // ...then save

$post->delete();
```

## Hiding and converting columns

The real `User` model shows the most common settings:

```php
// app/Models/User.php (simplified)
final class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];

    // Never include these when the model is turned into JSON.
    protected $hidden = ['password'];

    // Turn the stored text into a date object when you read it.
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
```

- **`$hidden`** keeps columns out of API responses. Always hide passwords.
- **`$casts`** converts values for you. With `'datetime'`, `$user->email_verified_at`
  is a date object you can format, not a plain string.

Dates use your app's timezone (`APP_TIMEZONE`, `UTC` by default).

## Going further

### Mutators: changing a value as it's saved

A mutator runs every time a column is set. The `User` model uses two:

- **Passwords are hashed automatically.** Setting `'password' => 'secret'` stores
  a secure hash, not the text. Values that are already hashes are left alone.
- **Emails are stored in lowercase**, so `Ada@Example.com` and
  `ada@example.com` are the same account. Changing the email of an existing
  user also clears `email_verified_at`, because the new address hasn't been
  proven yet.

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function password(): Attribute
{
    return Attribute::make(
        set: static fn (?string $value): ?string => $value === null || password_get_info($value)['algoName'] !== 'unknown'
            ? $value
            : password_hash($value, PASSWORD_BCRYPT),
    );
}
```

### Putting logic on the model

Small pieces of logic that belong to one table can live on its model. The app
does this for password resets and email verification:

```php
PasswordResetToken::issueFor($user);   // create a reset token
$record->matches($token);              // check it
$record->isExpired();                  // after 60 minutes
$user->verificationUrl();              // a signed verification link
```

### Relationships

Eloquent's relationships work as normal. For example, if `posts` has a
`user_id` column:

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

```php
$user->posts;                 // that user's posts
$user->posts()->create([...]);
```

See the [Eloquent documentation](https://laravel.com/docs/eloquent) for
everything else: scopes, more cast types, eager loading and so on. VtPhp
doesn't restrict any of it.

### Pagination

Eloquent's `paginate()` method isn't available, because it needs a package
VtPhp doesn't install. Page through results yourself and wrap them in VtPhp's
paginator, which adds `meta` and `links` to the JSON response:

```php
// app/Http/Controllers/PostController.php
use App\Http\Resources\PostResource;
use App\Models\Post;
use VtPhp\Http\Request;
use VtPhp\Pagination\LengthAwarePaginator;
use VtPhp\Resources\ResourceCollection;

public function index(Request $request): ResourceCollection
{
    $page = max(1, (int) $request->query('page', 1));
    $perPage = 20;
    $query = Post::query()->orderBy('id');

    $paginator = new LengthAwarePaginator(
        $query->clone()->forPage($page, $perPage)->get()->all(),
        $query->count(),
        $perPage,
        $page,
        '/api/v1/posts',
    );

    return PostResource::collection($paginator);
}
```

## Common mistakes

- **"Table not found" errors.** The table doesn't exist yet. Create a
  migration and run `php forge migrate`.
- **A value doesn't save.** The column isn't in `$fillable`. Add it.
- **The password shows up in API output.** Add it to `$hidden`, or return a
  [resource](resources.md) that picks the fields you want.
- **PHPStan says `User::create()` is undefined.** Use `User::query()->create()`.
  Both work, but static analysis can only see the second.
- **Looking for model factories** (Laravel's `User::factory()`)? They aren't
  available yet. Create test data with `create()` or a [seeder](migrations.md#seeders).

## Next steps

- [Migrations & seeders](migrations.md): create the tables your models use.
- [Your first API](your-first-api.md): build a full feature around a model.
- [Resources](resources.md): control what your API returns.
