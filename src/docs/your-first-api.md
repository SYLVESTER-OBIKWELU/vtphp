# Your first API: a notes app

In this tutorial you'll build a small API for notes: create, list, read,
update and delete them, then make it require a login. Along the way you'll
meet every main part of VtPhp. It takes about 20 minutes.

**Before you start:** finish [Getting started](getting-started.md), so the
app runs with `php forge serve` and you have an API client (or `curl`) for
sending requests.

## What you'll build

| Request | What it does |
| --- | --- |
| `GET /api/v1/notes` | List every note |
| `POST /api/v1/notes` | Create a note |
| `GET /api/v1/notes/1` | Show note 1 |
| `PATCH /api/v1/notes/1` | Change note 1 |
| `DELETE /api/v1/notes/1` | Delete note 1 |

## Step 1: Generate the files

One command creates everything a note needs:

```bash
php forge make:model Note -a
```

```text
[OK] Model Note created: app/Models/Note.php
[OK] Migration created: database/migrations/2026_10_29_194905_create_notes_table.php
[OK] Seeder NoteSeeder created: database/seeders/NoteSeeder.php
[OK] Controller NoteController created: app/Http/Controllers/NoteController.php
[OK] Request StoreNoteRequest created: app/Http/Requests/StoreNoteRequest.php
[OK] Request UpdateNoteRequest created: app/Http/Requests/UpdateNoteRequest.php
[OK] Resource NoteResource created: app/Http/Resources/NoteResource.php
Register its routes in routes/api.php: $router->apiResource('notes', NoteController::class);
```

Here's what each file is for. You'll fill most of them in below.

| File | Its job |
| --- | --- |
| **Migration** | Describes the `notes` table in the database. |
| **Model** (`Note`) | Your PHP handle on that table: `Note::query()->create([...])` adds a row. |
| **Controller** | The code that runs for each request. It's already written for you. |
| **Form requests** | The rules input must follow, checked before the controller runs. |
| **Resource** | Decides what a note looks like in the JSON you send back. |
| **Seeder** | Optional sample data. We won't need it. |

## Step 2: Describe the table

Open the new migration in `database/migrations/` (the file name starts with
today's date) and add the columns a note has: a title and an optional body.

```php
// database/migrations/2026_10_29_194905_create_notes_table.php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();                      // a unique number for each note
            $table->string('title');           // short text, required
            $table->text('body')->nullable();  // long text, may be empty
            $table->timestamps();              // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
```

`up()` creates the table; `down()` removes it again if you ever roll back.
Now create it:

```bash
php forge migrate
```

```text
Migrated: 2026_10_29_194905_create_notes_table
```

## Step 3: Say which fields can be saved

For safety, a model only accepts the fields you list in `$fillable`. This
stops a caller from setting fields they shouldn't, like `id`. Edit the model:

```php
// app/Models/Note.php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Note extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'title',
        'body',
    ];
}
```

## Step 4: Write the validation rules

**Validation** checks input before you use it. Each form request lists rules
for each field. Creating a note needs a title:

```php
// app/Http/Requests/StoreNoteRequest.php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
        ];
    }
}
```

Read `required|string|max:200` as: "must be sent, must be text, at most 200
characters". `nullable` means the body may be empty.

When updating, every field is optional, so the caller can change just the
title. `sometimes` means "only check this if it was sent":

```php
// app/Http/Requests/UpdateNoteRequest.php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:200',
            'body' => 'nullable|string',
        ];
    }
}
```

`authorize()` returning `true` means anyone may make this request. You'll
tighten access in step 8.

## Step 5: Choose what the JSON looks like

The resource turns a note into the JSON you send back. Listing the fields
yourself means you never leak a column by accident:

```php
// app/Http/Resources/NoteResource.php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use VtPhp\Resources\JsonResource;

/**
 * @property \App\Models\Note $resource
 */
final class NoteResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}
```

## Step 6: Connect the URLs

A **route** connects a URL to the code that answers it. Open `routes/api.php`
and add the line `make:model` printed, inside the `/api/v1` group. Also add
the `use` line at the top:

```php
// routes/api.php
use App\Http\Controllers\NoteController;

$router->group(['prefix' => '/api/v1', 'middleware' => [ThrottleRequests::using('api')]], function (Router $router): void {
    // ...the existing routes stay as they are...

    $router->apiResource('notes', NoteController::class);
});
```

That single `apiResource()` line creates all five routes. Check them:

```bash
php forge route:list
```

```text
GET         /api/v1/notes          notes.index     App\Http\Controllers\NoteController@index
POST        /api/v1/notes          notes.store     App\Http\Controllers\NoteController@store
GET         /api/v1/notes/{note}   notes.show      App\Http\Controllers\NoteController@show
PUT|PATCH   /api/v1/notes/{note}   notes.update    App\Http\Controllers\NoteController@update
DELETE      /api/v1/notes/{note}   notes.destroy   App\Http\Controllers\NoteController@destroy
```

`{note}` is a placeholder for a note's id, such as `/api/v1/notes/1`.

## Step 7: Try it

Start the app with `php forge serve` (if it isn't running already) and send
these requests from your API client.

**Create a note:**

```http
POST /api/v1/notes
Content-Type: application/json

{"title": "Buy milk", "body": "Two litres, semi-skimmed."}
```

```json
{"data": {"id": 1, "title": "Buy milk", "body": "Two litres, semi-skimmed.", "created_at": "2026-10-29T19:50:17+00:00"}}
```

The status is `201 Created`. Create a second one with just a title:
`{"title": "Call the bank"}`.

**List them:**

```http
GET /api/v1/notes
```

```json
{"data": [
  {"id": 1, "title": "Buy milk", "body": "Two litres, semi-skimmed.", "created_at": "2026-10-29T19:50:17+00:00"},
  {"id": 2, "title": "Call the bank", "body": null, "created_at": "2026-10-29T19:50:18+00:00"}
]}
```

**Read one:** `GET /api/v1/notes/1`.

**Change one:** only the title is sent, so only the title changes.

```http
PATCH /api/v1/notes/1
Content-Type: application/json

{"title": "Buy oat milk"}
```

```json
{"data": {"id": 1, "title": "Buy oat milk", "body": "Two litres, semi-skimmed.", "created_at": "2026-10-29T19:50:17+00:00"}}
```

**Delete one:** `DELETE /api/v1/notes/2` answers `204 No Content`, which means
"done, nothing to send back".

### When things go wrong

Send a note without a title:

```http
POST /api/v1/notes
Content-Type: application/json

{"body": "no title"}
```

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "errors": {"title": ["The title field is required."]}
  }
}
```

The status is `422`, and the controller never ran. Ask for the note you
deleted, `GET /api/v1/notes/2`, and you get `404` with
`{"code": "NOT_FOUND", "message": "Note not found."}`.

While developing you'll also see `exception` and a long `trace` in error
replies. That's because `APP_DEBUG=true` in your `.env`. Production hides
them.

Finally, try to sneak in an extra field: `{"title": "Sneaky", "id": 99}`. The
note is created with the next normal id. The rules only list `title` and
`body`, so anything else is dropped.

## Step 8: Require a login

Right now anyone can read and change notes. To allow only logged-in users,
add the `Authenticate` **middleware** (code that runs before your controller)
to the notes routes:

```php
// routes/api.php
$router->apiResource('notes', NoteController::class, ['middleware' => [Authenticate::class]]);
```

`Authenticate` is already imported at the top of `routes/api.php`. Now:

1. `GET /api/v1/notes` without logging in returns `401` with
   `"code": "UNAUTHENTICATED"`.
2. Log in as the sample user (run `php forge db:seed` first if you haven't):

   ```http
   POST /api/v1/login
   Content-Type: application/json

   {"email": "ada@example.com", "password": "password"}
   ```

   The response sets a **session cookie**, a small token your API client
   stores and sends back automatically, which proves who you are.
3. `GET /api/v1/notes` now works again.

One rule to know: when a request carries that login cookie, any `POST`,
`PUT`, `PATCH` or `DELETE` must be sent as JSON (with
`Content-Type: application/json`), or it's refused with `403`. This protects
your users from malicious websites (see [Security](security.md)). API clients
send JSON when you pick a JSON body, so you rarely notice.

## What you've learned

- `make:model Note -a` creates every file a resource needs.
- A **migration** describes a table; `php forge migrate` creates it.
- A **model** reads and writes rows; `$fillable` says which fields can be set.
- **Form requests** reject bad input with a `422` before your code runs.
- A **resource** shapes the JSON you send back.
- `apiResource()` connects five URLs to the controller in one line.
- **Middleware** like `Authenticate` guards routes.

## Ideas to try next

- Give each note an owner: add `$table->foreignId('user_id')` in a new
  migration, save `auth()->id()` when creating, and only list the user's own
  notes. See [Models](models.md) and [Authentication](authentication.md).
- Add a `pinned` true/false column and a `boolean` rule. See
  [Migrations](migrations.md) and [Validation](validation.md).
- Write tests for your notes API. See [Testing](testing.md).

## Next steps

- [How a request works](request-lifecycle.md): the journey from URL to response.
- [Routing](routing.md), [Controllers](controllers.md),
  [Validation](validation.md): each step of this tutorial in depth.
