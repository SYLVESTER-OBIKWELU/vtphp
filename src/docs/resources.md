# Resources (API responses)

`JsonResource` shapes a single model/object into a JSON-safe array; wrap a
collection with `ResourceCollection` (or the `::collection()` shortcut).

```php
namespace App\Http\Resources;

use VtPhp\Resources\JsonResource;

/**
 * @property \App\Models\User $resource
 */
final class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'email_verified' => $this->resource->hasVerifiedEmail(),
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}
```

Resources live in `app/Http/Resources/`. `collection()` accepts an Eloquent
collection, any iterable, or a paginator.

Usage from a controller:

```php
return UserResource::make($user);           // single item -> {"data": {...}}
return UserResource::collection($users);    // many items  -> {"data": [...]}
return UserResource::make($user)->status(201);
```

Generate a new resource with:

```powershell
php forge make:resource Product                  # returns (array) $this->resource
php forge make:resource Product --model=Product  # returns $this->resource->toArray()
```

`make:model Product -j` (or `-a`) generates the model-bound version.

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
