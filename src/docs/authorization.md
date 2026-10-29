# Authorization: who may do what

[Authentication](authentication.md) answers "who are you?". Authorization
answers the next question: "are you allowed to do this?". For example, any
logged-in user may read notes, but only the owner may edit or delete one.

When the answer is no, VtPhp responds with `403 FORBIDDEN`.

## The quick version

1. Create a policy for your model:

   ```bash
   php forge make:policy NotePolicy --model=Note
   ```

2. Write the rule in `app/Policies/NotePolicy.php`:

   ```php
   public function update(User $user, Note $note): bool
   {
       return $user->id === $note->user_id;
   }
   ```

3. Check it in your controller:

   ```php
   public function update(UpdateNoteRequest $request, Note $note): ResponseInterface
   {
       $this->authorize('update', $note); // 403 if it isn't theirs
       // ...
   }
   ```

That's it. VtPhp finds `NotePolicy` by its name, so you don't register it
anywhere.

## Two kinds of rules

| | Use it for | Lives in |
| --- | --- | --- |
| **Policy** | Rules about one kind of record: "may they edit *this* note?" | a class in `app/Policies/` |
| **Gate** | One-off rules not tied to a record: "may they see the reports page?" | a function in `AppServiceProvider` |

Both are checked the same way, and the code that checks them is called the
**gate**.

## Policies

A policy is a plain class with one method per action. Each method gets the
logged-in user (and usually the record) and returns `true` (allowed) or
`false` (denied).

`php forge make:policy NotePolicy --model=Note` gives you a starting point:

```php
// app/Policies/NotePolicy.php
final class NotePolicy
{
    public function viewAny(User $user): bool { return true; }              // list notes
    public function view(User $user, Note $note): bool { return true; }     // see one note
    public function create(User $user): bool { return true; }               // make a note
    public function update(User $user, Note $note): bool { return false; }  // edit one
    public function delete(User $user, Note $note): bool { return false; }  // delete one
}
```

`update` and `delete` start out as `false` on purpose, so nothing is allowed
until you write the rule. Without `--model` you get an empty class to fill in.

`php forge make:model Note -p` (or `-a`) creates the policy together with the
model.

### How VtPhp finds the policy

The policy for `App\Models\Note` is `App\Policies\NotePolicy`. Follow that
naming and it works on its own. If your policy lives somewhere else, register
it in `app/Providers/AppServiceProvider.php`:

```php
use App\Models\Note;
use App\Policies\Admin\NoteRules;

public function boot(): void
{
    gate()->policy(Note::class, NoteRules::class);
}
```

### Actions without a record

Some actions don't have a record yet, such as creating one. Pass the class
name instead:

```php
$this->authorize('create', Note::class);
```

The class name only tells the gate which policy to use. Your `create()`
method receives just the user.

## Gates

For a rule with no model behind it, define a gate in `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php
use App\Models\User;

public function boot(): void
{
    gate()->define('viewReports', fn (User $user): bool => $user->is_admin);
}
```

Gates can take extra values too:

```php
gate()->define('publish', fn (User $user, string $channel): bool => $channel !== 'homepage' || $user->is_admin);

gate()->allows('publish', 'homepage');
```

## Checking a rule

Pick whichever reads best where you are:

```php
// In a controller (it uses the AuthorizesRequests trait): throws a 403
$this->authorize('update', $note);

// Anywhere: true or false
gate()->allows('update', $note);
gate()->denies('update', $note);

// Anywhere: throws a 403 when denied
gate()->authorize('viewReports');

// On a user, e.g. someone other than the logged-in user
$user->can('update', $note);
$user->cannot('delete', $note);
```

To use `$this->authorize()`, add the trait to your controller:

```php
use VtPhp\Auth\Access\AuthorizesRequests;

final class NoteController
{
    use AuthorizesRequests;
    // ...
}
```

`$user->can()` needs the `Authorizable` trait on the model. The sample
`User` model already has it.

### In a form request

A form request's `authorize()` method runs before validation. Returning
`false` gives a `403`:

```php
// app/Http/Requests/UpdateNoteRequest.php
public function authorize(): bool
{
    $note = Note::query()->find($this->route('note'));

    return $note !== null && gate()->allows('update', $note);
}
```

### For a whole route

The `Can` middleware checks a gate before the controller runs. It's handy
for rules that don't need a record:

```php
// routes/api.php
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\Can;

$router->get('/reports', [ReportController::class, 'index'], 'reports', [
    Authenticate::class,
    Can::using('viewReports'),
]);
```

## Guests

Rules are about logged-in users, so a guest is denied automatically. To let
guests through, make the user parameter nullable with `?`:

```php
public function view(?User $user, Note $note): bool
{
    return $note->is_public || $user?->id === $note->user_id;
}
```

## Admins: deciding before the rules run

A `before` check runs ahead of every other rule. Return `true` or `false` to
decide on the spot, or `null` to carry on as normal:

```php
// app/Providers/AppServiceProvider.php: admins may do everything
gate()->before(fn (?User $user, string $ability): ?bool => $user?->is_admin ? true : null);
```

A policy can have its own `before()` method that applies only to that model:

```php
final class NotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_moderator ? true : null;
    }
    // ...
}
```

## What the sample app does

`app/Policies/UserPolicy.php` lets people edit and delete only their own
account. `UpdateUserRequest::authorize()` checks `update`, and
`UserController::destroy()` calls `$this->authorize('delete', $user)`.
Everyone else gets `403 FORBIDDEN`.

## Common mistakes

- **Everything is denied.** Check the method name matches the ability:
  `authorize('update', ...)` calls `update()`. An ability with no matching
  method or gate is always denied. Abilities written like `view-any` are
  turned into `viewAny`.
- **A guest is denied by a rule that should be public.** Make the user
  parameter nullable: `?User $user`.
- **The policy isn't found.** It must be named `{Model}Policy` in
  `App\Policies`, or registered with `gate()->policy()`.
- **`401` instead of `403`.** A `401` means "not logged in" and comes from the
  `Authenticate` middleware, before any rule is checked.
- **API tokens and abilities.** Token abilities (`RequireTokenAbility`) limit
  what a *token* may do. Policies limit what a *user* may do. Use both when
  you need both. See [Authentication](authentication.md#api-tokens-for-mobile-apps-scripts-and-other-servers).

## Next steps

- [Authentication](authentication.md): logging users in.
- [Middleware](middleware.md): run checks on many routes at once.
- [Testing](testing.md): test that the wrong user gets a `403`.
