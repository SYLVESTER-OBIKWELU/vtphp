# Resources: shaping your JSON

A **resource** is a small class that decides what a record looks like when
it's sent as JSON. It lets you pick which fields to show, rename them, add
computed values, and keep secrets like password hashes out of your API.

## The quick version

Create a resource for your model:

```powershell
php forge make:resource Post --model=Post
```

Choose the fields:

```php
// app/Http/Resources/PostResource.php
namespace App\Http\Resources;

use VtPhp\Resources\JsonResource;

/**
 * @property \App\Models\Post $resource
 */
final class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}
```

Return it from a controller:

```php
// app/Http/Controllers/PostController.php
public function show(Post $post): PostResource
{
    return PostResource::make($post);
}
```

```http
GET /api/v1/posts/1
```

```json
{"data": {"id": 1, "title": "Hello world", "created_at": "2026-10-29T12:00:00+00:00"}}
```

The fields you returned from `toArray()` are wrapped in a `data` key. That
wrapper gives every response the same predictable shape.

## Why use a resource at all?

You *can* return a model directly and get all its columns as JSON. A resource
is better because:

- **You control the contract.** Adding a database column doesn't
  accidentally change what your API sends.
- **You can add computed fields**, like `email_verified` below.
- **One place to change.** Every endpoint that returns a post uses the same
  shape.

The sample app's `UserResource` adds a computed field and leaves the password
out entirely:

```php
// app/Http/Resources/UserResource.php
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
```

Inside the class, `$this->resource` is the record you passed to `make()`.
The `@property` comment above the class just tells your editor its type.

## Lists of records

Use `collection()` for many records:

```php
// app/Http/Controllers/PostController.php
use VtPhp\Resources\ResourceCollection;

public function index(): ResourceCollection
{
    return PostResource::collection(Post::query()->orderBy('id')->get());
}
```

```json
{"data": [{"id": 1, "title": "Hello world", "created_at": "..."}, {"id": 2, "title": "Second post", "created_at": "..."}]}
```

Each item goes through your `toArray()`. `collection()` accepts an Eloquent
result, any array or list, or a page of results from `paginate()`
(below).

## Status codes and headers

A response's **status code** tells the caller what happened: `200` means
OK, and `201` means "created". Set it, and any extra headers, on the
resource:

```php
// app/Http/Controllers/PostController.php
public function store(StorePostRequest $request): PostResource
{
    $post = Post::query()->create($request->validated());

    return PostResource::make($post)
        ->status(201)
        ->header('Location', "/api/v1/posts/{$post->id}");
}
```

Collections support `->status()` too.

## Going further

### Pages of results (pagination)

Sending thousands of records at once is slow. **Pagination** sends one page
at a time. Call Eloquent's `paginate()` instead of `get()` and pass the result
to `collection()`:

```php
// app/Http/Controllers/PostController.php
use VtPhp\Http\Request;
use VtPhp\Resources\ResourceCollection;

public function index(Request $request): ResourceCollection
{
    return PostResource::collection(
        Post::query()->orderBy('id')->paginate($request->perPage())
    );
}
```

The caller picks the page with `?page=` and, thanks to `$request->perPage()`,
the page size with `?per_page=`:

```http
GET /api/v1/posts?page=2&per_page=20
```

The response then also says which page this is and how to get the others:

```json
{
  "data": [{"id": 21, "title": "..."}],
  "meta": {"current_page": 2, "per_page": 20, "total": 45, "last_page": 3, "from": 21, "to": 40},
  "links": {
    "first": "/api/v1/posts?page=1",
    "last": "/api/v1/posts?page=3",
    "prev": "/api/v1/posts?page=1",
    "next": "/api/v1/posts?page=3"
  }
}
```

- `prev` is `null` on the first page and `next` is `null` on the last.
- A missing or nonsense page (`?page=abc`, `?page=0`) means page 1. A page
  past the end returns an empty `data` list, not an error.
- `$request->perPage()` uses 15 unless the caller asks otherwise, and never
  goes above 100. Change both with `$request->perPage(25, 50)`. Or skip it
  and use a fixed size: `->paginate(20)`.
- `from` and `to` are the positions of the first and last record on this
  page (`null` on an empty page).

**Keeping filters in the links.** If the URL has other options, such as
`?status=draft&page=2`, add `->withQueryString()` so the links keep them:

```php
Post::query()->where('status', $request->query('status'))->paginate(20)->withQueryString();
```

**Other kinds of paging:**

| Method | Good for | Response |
| --- | --- | --- |
| `paginate()` | most lists | `meta` has `total` and `last_page` |
| `simplePaginate()` | big tables where counting every row is slow | no `total`, `last_page` or `last` link; just previous and next |
| `cursorPaginate()` | infinite scroll and feeds that change while people read | `?cursor=...` instead of `?page=`; `meta` has `next_cursor` and `prev_cursor` |

The sample app's `GET /api/v1/users` uses `paginate()`.

> Building pages from something that isn't an Eloquent query, like an API
> result? Wrap them in `VtPhp\Pagination\LengthAwarePaginator`:
> `new LengthAwarePaginator($items, $total, $perPage, $page, '/api/v1/things')`.
### Responses without a resource

For a simple message, skip the resource and use the `response()` helper:

```php
return response()->json(['message' => 'Logged out.']);           // 200
return response()->json(['message' => 'Try again later.'], 503);  // any status
return response()->noContent();                                  // 204, empty body
```

### Model or plain resource?

`php forge make:resource Post --model=Post` starts `toArray()` as
`return $this->resource->toArray();` (every visible column). Without
`--model`, it starts as `return (array) $this->resource;`, for wrapping plain
arrays. Either way, replace it with the fields you want.
`php forge make:model Post -j` (or `-a`) creates the model version for you.

## Common mistakes

- **Leaking fields.** `return $this->resource->toArray();` sends every column
  that isn't in the model's `$hidden` list. List fields explicitly for
  anything public.
- **Forgetting the `?->` on dates.** `created_at` can be `null` on a model
  that hasn't been saved. `?->toAtomString()` avoids an error.
- **Expecting Laravel's `toArray($request)`.** In VtPhp, `toArray()` takes no
  arguments. Use `auth()->user()` if you need the current user inside it.
- **`header()` on a collection.** Only single resources have `->header()`;
  collections support `->status()`.

## Next steps

- [Controllers](controllers.md): where resources are returned.
- [Models](models.md): the records resources wrap.
- [Validation](validation.md): checking input before you save it.
