# Controllers

Controllers are plain classes under `app/Http/Controllers/` (namespace
`App\Http\Controllers`). There's no required base class. Like Laravel, they
work with Eloquent models directly; there's no service or repository layer
to write.

```php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
        $user->delete();

        return response()->noContent();
    }
}
```

Register all five actions with one line in `routes/api.php`:

```php
$router->apiResource('users', UserController::class);
```

## What an action can receive

Parameters are resolved by type and name:

| Parameter                               | What you get                                                                                                                                               |
| --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A model, e.g. `User $user`              | **Route-model binding:** the model whose key matches the `{user}` segment, or a 404 `User not found.` (see [Routing](routing.md#route-model-binding)). |
| A `FormRequest`, e.g. `StoreUserRequest` | The request, already authorized and validated. Invalid input never reaches the action (see [Validation](validation.md)).                                |
| `VtPhp\Http\Request`                    | The current request (`input()`, `query()`, `user()`, `validate()`, …).                                                                                    |
| A scalar named after a segment          | The route parameter, e.g. `string $hash` for `{hash}`, converted to `int`/`float` when declared so (a mismatch is a 404).                                  |
| Any other class                         | Resolved from the container, e.g. `Mailer $mailer`.                                                                                                        |

Constructor dependencies are resolved through the container too.

## What an action can return

- A `JsonResource` / `ResourceCollection` (see [Resources](resources.md)).
- A model or Eloquent collection, encoded with `toArray()` (hidden attributes stay hidden).
- An array or string, encoded as JSON.
- `null`, which becomes `204 No Content`.
- Any PSR-7 response, usually built with `response()->json(...)` or `response()->noContent()`.

## Generating controllers

```powershell
php forge make:controller ProductController                 # empty class
php forge make:controller ProductController --api           # index/store/show/update/destroy stubs
php forge make:controller ProductController --model=Product # bound to the model, validates inline
php forge make:controller ProductController --model=Product -R -j
#   ...also creates StoreProductRequest, UpdateProductRequest and ProductResource and uses them
```

Or scaffold everything at once from the model; see [Models](models.md#generating-models):

```powershell
php forge make:model Product -a
```

Model-bound controllers print the `apiResource()` line to add to `routes/api.php`.
