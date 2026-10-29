# API documentation (Swagger)

VtPhp can build documentation for your API automatically and show it as an
interactive web page. You'll see every endpoint, what to send it and what
comes back, and you can try each one from the browser.

It reads your routes, controllers and form requests, so there's nothing to
write by hand and the docs never go out of date.

The page uses **Swagger UI**, and the description behind it is an **OpenAPI**
document: the standard format that tools such as Postman can import.

## The quick version

1. Switch it on in `.env`:

   ```dotenv
   SWAGGER_ENABLED=true
   ```

2. Start the app (`php forge serve`) and open
   [http://localhost:8000/api-docs](http://localhost:8000/api-docs).

3. Group your routes into sections with a `tag`:

   ```php
   // routes/api.php
   $router->group(['tag' => 'Notes'], function (Router $router): void {
       $router->get('/notes', [NoteController::class, 'index'], 'notes.index');
       $router->post('/notes', [NoteController::class, 'store'], 'notes.store');
   });
   ```

That's it. The raw OpenAPI document is at `/api-docs/openapi.json`.

## Settings

| `.env` setting | Default | Meaning |
| --- | --- | --- |
| `SWAGGER_ENABLED` | `false` | Show the docs. |
| `SWAGGER_PATH` | `/api-docs` | Where the page lives. The JSON is at `{path}/openapi.json`. |
| `SWAGGER_TITLE` | `{APP_NAME} API` | The heading on the page. |
| `SWAGGER_VERSION` | `1.0.0` | Your API's version number. |
| `SWAGGER_DESCRIPTION` | *(empty)* | An introduction shown at the top. |
| `SWAGGER_GENERATE_ALWAYS` | `true` | Rebuild on every visit. See [Saving the docs to a file](#saving-the-docs-to-a-file). |

More options, such as section descriptions and routes to leave out, are in
`config/swagger.php`.

## What VtPhp reads automatically

Here's where each part of the page comes from, using a typical controller:

```php
// app/Http/Controllers/NoteController.php
final class NoteController
{
    /**
     * Create a note.                       ← the summary
     *
     * The note belongs to whoever is       ← the description
     * logged in.
     */
    public function store(StoreNoteRequest $request): NoteResource   // ← request body + response
    {
        return NoteResource::make(Note::query()->create($request->validated()))->status(201);  // ← 201
    }
}
```

| On the page | Comes from |
| --- | --- |
| **Section** | The `tag` of the route group. Without one, the controller's name (`NoteController` → "Notes"). |
| **Summary and description** | The method's doc comment: the first line, then the rest. Without one, a sensible default ("List notes", "Create a note"...). |
| **URL parameters** | `{note}` in the route. A model parameter (`Note $note`) or `int $id` is shown as a number. |
| **Request body** | The form request's `rules()`, see below. For `GET` routes these become query parameters (`?q=...`). |
| **Success response** | The resource you return (`NoteResource::make`, `::collection`), `response()->json([...])`, `noContent()` or the return type. `->status(201)` and `json(..., 201)` set the code. |
| **Pages** | `->paginate()` adds `?page=`, `$request->perPage()` adds `?per_page=`, and the response shows `meta` and `links`. |
| **Errors** | `401` from `Authenticate`, `403` from `Can`, `authorize()` and form requests with their own `authorize()`, `404` for URL parameters, `422` for form requests, `429` from `ThrottleRequests`. |
| **Login** | `Authenticate` adds a lock icon: a session cookie, a bearer token, or either. |

### How rules become the request body

| Rule | Shown as |
| --- | --- |
| `required` | a required field (marked with a red star) |
| `string`, `integer`, `numeric`, `boolean`, `array` | the field's type |
| `email`, `date` | a format (`user@example.com`, a date-time) |
| `min:3`, `max:100` | length limits for text, value limits for numbers, item limits for arrays |
| `in:draft,published` | a list of allowed values |
| `nullable` | may be `null` |
| `confirmed` | adds the matching `*_confirmation` field |
| `unique`, `exists`, `max_bytes` | a note in the field's description |
| `address.city`, `tags.*` | nested objects and lists |

### How resources become the response

VtPhp fills an empty model into your resource and looks at the fields it
returns. Tell it which model with a comment above the class (`make:resource
--model` adds it for you):

```php
/**
 * @property \App\Models\Note $resource
 */
final class NoteResource extends JsonResource
```

Field types come from the model's `$casts`, and otherwise from the name:
`id` and `*_id` are numbers, `*_at` are dates, `is_*` and `has_*` are
true/false, `email` is an email address, and anything else is text.

## Sections (tags)

Every route inside a group with a `tag` is listed under that heading. Groups
can be nested, and an inner tag replaces the outer one:

```php
$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    $router->group(['tag' => 'Notes'], function (Router $router): void {
        // ...
    });

    // apiResource() takes a tag too:
    $router->apiResource('projects', ProjectController::class, ['tag' => 'Projects']);
});
```

A group can carry only a tag, with no prefix or middleware. Sections appear
in the order your routes are registered. To add a description under a
section's heading, list it in `config/swagger.php`:

```php
'tags' => [
    'Notes' => 'Create, read, update and delete your notes.',
],
```

## Adding or changing details: `#[ApiDoc]`

Everything above is automatic. When you want to say something different,
add the `ApiDoc` attribute to the method:

```php
use VtPhp\OpenApi\Attributes\ApiDoc;

#[ApiDoc(
    summary: 'Archive a note',
    description: 'Archived notes are hidden from the list but not deleted.',
    tags: ['Archive'],
    deprecated: true,                       // shown crossed out
    responses: [409 => 'Already archived.'],
)]
public function archive(Note $note): NoteResource
```

To leave a route out of the docs:

```php
#[ApiDoc(hidden: true)]
public function internalStats(): JsonResponse
```

Put `#[ApiDoc(...)]` on the controller class to apply it to every method in
it. To leave out whole areas by URL, list them in `config/swagger.php`:
`'exclude' => ['/internal']`.

## Trying requests from the page

Click an endpoint, then **Try it out**, fill in the fields and press
**Execute**.

- **Logged in with a cookie.** Run `POST /api/v1/login` from the page first.
  Your browser keeps the session cookie and sends it with later requests.
- **With an API token.** Run `POST /api/v1/tokens`, copy the `token` from the
  response, click **Authorize** at the top and paste it under `bearerAuth`.

The page adds the `X-Requested-With` header for you, so requests pass VtPhp's
CSRF check.

## Saving the docs to a file

```bash
php forge swagger:generate
```

This writes the OpenAPI document to `storage/api-docs/openapi.json`. Use it
to import your API into Postman or Insomnia, or to generate client code.

In production you can serve this file instead of rebuilding the docs on each
visit. Set `SWAGGER_GENERATE_ALWAYS=false` and run `swagger:generate` as part
of every deploy. If the file is missing, VtPhp builds the docs as usual.

## In production

The docs show every endpoint and field your API has. That's fine for a
public API, but for a private one keep them switched off in production
(`SWAGGER_ENABLED=false`, the default), or allow only logged-in users by
adding middleware in `config/swagger.php`:

```php
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\Can;

'middleware' => [Authenticate::class, Can::using('viewApiDocs')],
```

The page loads Swagger UI from the jsDelivr CDN, at a fixed version. To use
another copy, change `ui_cdn` in `config/swagger.php`.

## Common mistakes

- **`/api-docs` is a 404.** `SWAGGER_ENABLED` isn't `true`, or you changed
  `SWAGGER_PATH`.
- **A field is missing from the request body.** It isn't in the form request's
  `rules()`. Fields you read with `$request->input()` alone can't be seen.
- **The response shows an empty object.** The method returns something VtPhp
  can't inspect, such as a variable built elsewhere. Return the resource
  directly, add a `@return array{...}` comment, or describe it with
  `#[ApiDoc(responses: [...])]`.
- **Everything is under the controller's name.** Wrap the routes in
  `$router->group(['tag' => '...'], ...)`.
- **Changes don't appear.** `SWAGGER_GENERATE_ALWAYS=false` serves the saved
  file. Run `php forge swagger:generate` again, or set it back to `true`.
- **"Try it out" gets `403 CSRF_REJECTED`.** You're calling the API on a
  different address from the page. Open the docs on the same address as the
  API, or list the address in `servers` in `config/swagger.php` and enable
  [CORS](cors.md).

## Next steps

- [Routing](routing.md): groups and `apiResource()`.
- [Validation](validation.md): the rules the docs read.
- [Resources](resources.md): the responses the docs show.
