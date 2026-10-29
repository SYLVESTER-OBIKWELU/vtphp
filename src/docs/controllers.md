# Controllers

A **controller** is a class that holds the code your routes run. Each public
method is an **action**: it receives the request, does the work (usually
with a model), and returns what the caller should get back.

## The quick version

```php
// app/Http/Controllers/HelloController.php
namespace App\Http\Controllers;

final class HelloController
{
    public function show(string $name): array
    {
        return ['message' => "Hello, {$name}!"];
    }
}
```

```php
// routes/api.php
use App\Http\Controllers\HelloController;

$router->get('/hello/{name}', [HelloController::class, 'show']);
```

```http
GET /hello/Ada
```

```json
{"message": "Hello, Ada!"}
```

The route passed `{name}` into the `$name` argument, and the array you
returned became JSON. Controllers don't need to extend any base class.

## Creating a controller

Let the CLI write the file for you:

```powershell
php forge make:controller PostController                    # an empty class
php forge make:controller PostController --api              # the five resource methods, ready to fill in
php forge make:controller PostController --model=Post       # the five methods, already using the Post model
php forge make:controller PostController --model=Post -R -j # ...plus form requests and a JSON resource
```

Controllers live in `app/Http/Controllers/` under the namespace
`App\Http\Controllers`.

## A real controller, step by step

This is the sample app's `UserController`. It's the pattern you'll use most:

```php
// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Http\JsonResponse;
use VtPhp\Resources\ResourceCollection;

final class UserController
{
    public function index(): ResourceCollection
    {
        return UserResource::collection(User::query()->orderBy('id')->get());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        return UserResource::make(User::query()->create($request->validated()))->status(201);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return UserResource::make($user);
    }

    public function destroy(User $user): JsonResponse
    {
        if ((string) auth()->id() !== (string) $user->id) {
            throw new AuthorizationException('You may only delete your own account.');
        }

        $user->delete();

        return response()->noContent();
    }
}
```

One line in `routes/api.php` connects all five:
`$router->apiResource('users', UserController::class);` (see
[Routing](routing.md#resource-routes-five-routes-in-one-line)).

What's going on:

- **`index()`** loads every user with Eloquent (the database layer, see
  [Models](models.md)) and returns them as a list.
- **`store()`** receives a `StoreUserRequest`. By the time your code runs, the
  input has already been checked (see [Validation](validation.md)).
  `validated()` gives you only the checked fields, so it's safe to pass to
  `create()`.
- **`show()`** asks for `User $user`. Because the route has `{user}`, VtPhp
  loads that user for you, or answers `404` if there's no such user.
- **`destroy()`** refuses to delete someone else's account by throwing an
  exception. The framework turns it into a `403` JSON error.

## What an action can ask for

Write the arguments you need; VtPhp fills them in by **type** and **name**:

| You write                               | You get                                                                 |
| --------------------------------------- | ----------------------------------------------------------------------- |
| A model, e.g. `User $user`              | The record whose id is in `{user}`, or a 404 `User not found.`          |
| A form request, e.g. `StoreUserRequest` | The request, already authorized and validated.                          |
| `VtPhp\Http\Request $request`           | The current request: `input()`, `query()`, `user()`, `validate()`...    |
| A plain value, e.g. `string $hash`      | The route parameter with that name. `int`/`float` are converted, and a non-number is a 404. |
| Any other class, e.g. `Mailer $mailer`  | A ready-made object from the framework's service container.             |

The **service container** is the part of VtPhp that builds objects for you.
You can also ask for services in the controller's constructor:

```php
// app/Http/Controllers/ReportController.php
use VtPhp\Mail\Mailer;

final class ReportController
{
    public function __construct(private readonly Mailer $mailer)
    {
    }
}
```

## What an action can return

| You return                                 | The caller receives                                    |
| ------------------------------------------ | ------------------------------------------------------ |
| An array or a string                       | That value as JSON, status 200.                        |
| A `JsonResource` or `ResourceCollection`   | `{"data": ...}`, see [Resources](resources.md).        |
| A model or Eloquent collection             | Its attributes as JSON (hidden fields stay hidden).    |
| `null`                                     | An empty `204 No Content` response.                    |
| A response from `response()->...`          | Exactly that response.                                 |

The `response()` helper builds responses by hand:

```php
return response()->json(['message' => 'Logged out.']);         // 200 with JSON
return response()->json(['message' => 'Nope.'], 401);          // any status code
return response()->noContent();                                // 204, empty body
```

## Sending errors

To stop and send an error, **throw an exception**. You don't build error
responses yourself:

```php
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Exceptions\NotFoundHttpException;

throw new NotFoundHttpException('Order not found.');       // 404 NOT_FOUND
throw new AuthorizationException();                         // 403 FORBIDDEN
```

The caller gets a consistent JSON error:

```json
{"success": false, "error": {"code": "NOT_FOUND", "message": "Order not found."}}
```

See [Exceptions](exceptions.md) for the full list.

## Going further

### Who is logged in?

`auth()->user()` (or `$request->user()`) returns the logged-in user, or
`null`. The sample `AuthController::me()` does exactly this:

```php
// app/Http/Controllers/AuthController.php
public function me(Request $request): UserResource
{
    return UserResource::make($request->user());
}
```

Protect such routes with the `Authenticate` middleware so guests get a 401
first (see [Authentication](authentication.md)).

### Keeping controllers small

Logic that belongs to one model goes on that model. For example,
`PasswordResetToken::issueFor($user)` lives on the model, not in the
controller. Like Laravel, VtPhp doesn't require service or repository
classes; add your own plain classes if a controller grows too big, and ask
for them in the constructor.

## Common mistakes

- **`Unresolvable dependency $name`** (a 500 error). An argument has no
  matching `{name}` in the route, no type the container can build, and no
  default value. Check the spelling against the route.
- **An always-empty model.** The argument name must match the URL part:
  `{post}` needs `Post $post`, not `Post $item`.
- **Returning `void`.** An action that returns nothing sends `204 No
  Content`. Return the data if the caller expects some.
- **Checking input by hand.** Use a form request instead; it also gives you
  the standard 422 error format for free.

## Next steps

- [Validation](validation.md): check input with form requests.
- [Resources](resources.md): control the shape of your JSON.
- [Models](models.md): read and write the database.
- [Your first API](your-first-api.md): put it all together.
