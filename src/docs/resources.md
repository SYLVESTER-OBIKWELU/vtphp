# Resources (API responses)

`JsonResource` shapes a single model/object into a JSON-safe array; wrap a
collection with `ResourceCollection` (or the `::collection()` shortcut).

```php
namespace App\Resources;

use VtPhp\Resources\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
```

Usage from a controller:

```php
return UserResource::make($user);           // single item -> {"data": {...}}
return UserResource::collection($users);    // many items  -> {"data": [...]}
return UserResource::make($user)->status(201);
```

Generate a new resource with:

```powershell
php forge make:resource ProductResource
```

## Pagination

`ResourceCollection::toResponse()` recognizes a
`VtPhp\Pagination\LengthAwarePaginator` and adds `meta`/`links` to the JSON
envelope automatically:

```php
return ProductResource::collection($paginator); // {"data": [...], "meta": {...}, "links": {...}}
```

## Response shape

Both `JsonResource` and `ResourceCollection` ultimately produce a
`VtPhp\Http\JsonResponse`. For responses that aren't tied to a resource, use
the `response()` helper directly:

```php
return response()->json(['message' => 'Logged out.']);
return response()->noContent(); // 204
```
