# Controllers

Controllers are plain classes under `app/Controllers/` — there's no required
base class. Actions can:

- Return an array or string, which the framework encodes as JSON.
- Return a `VtPhp\Http\JsonResponse` (usually via the `response()` helper).
- Return a `JsonResource` / `ResourceCollection` (see [Resources](resources.md)).
- Type-hint `VtPhp\Http\Request $request` to have the current request
  auto-injected (resolved by `ControllerDispatcher` from the container).

```php
namespace App\Controllers;

use App\Requests\CreateUserRequest;
use App\Resources\UserResource;
use App\Services\UserService;
use VtPhp\Http\Request;
use VtPhp\Routing\Attributes\Route;

final class UserController
{
    public function __construct(private readonly UserService $service)
    {
    }

    #[Route(method: 'GET', path: '/users', name: 'users.index')]
    public function index(): UserResource
    {
        return UserResource::collection($this->service->all());
    }

    #[Route(method: 'POST', path: '/users', name: 'users.store')]
    public function store(Request $request): UserResource
    {
        $data = CreateUserRequest::fromRequest($request);

        return UserResource::make($this->service->create($data))->status(201);
    }
}
```

Constructor dependencies (like `UserService` above) are resolved through the
container, so anything bound in a service provider — or any concrete class
with a resolvable constructor — can simply be type-hinted.

Generate a new controller with the `forge` CLI:

```powershell
php forge make:controller ProductController
```

See [Requests & validation](validation.md) for the `XxxRequest::fromRequest()`
pattern used above, and [Routing](routing.md) for how `#[Route]` attaches an
action to a URI.
