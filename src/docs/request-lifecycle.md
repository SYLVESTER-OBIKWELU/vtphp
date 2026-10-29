# How a request works

When someone calls your API, VtPhp passes the request through a series of
steps before your code runs, and the response back through them afterwards.
Knowing the steps helps you understand where to put your own code and why
you see the errors you see. You don't need this page to build things, but it
makes everything else click.

## The quick version

```text
  Request arrives
        │
        ▼
  1. public/index.php        the single front door
  2. Boot the app            read .env and config, load routes
  3. Global middleware       security, cookies, session... (every request)
  4. Router                  which code handles this URL?
  5. Route middleware        login check, rate limit... (some routes)
  6. Controller              YOUR CODE runs here
  7. Build the response      turn what you returned into JSON
        │
        ▼
  Response goes back out through the same middleware, then to the caller
```

If anything throws an error along the way, it's turned into a tidy JSON error
response, and the response still passes back out through the middleware.

## Step by step

### 1. The front door: `public/index.php`

Your web server sends every request to one file, `public/index.php`. This
"single front door" means all requests get the same security and setup. The
file converts PHP's raw request data into a request object (a standard
format called PSR-7) and hands it to the framework.

### 2. Booting the app

Before handling the request, VtPhp prepares itself (`bootstrap/app.php`):

1. **Load `.env`**, your private settings.
2. **Load `config/*.php`**, the settings files (they read values from `.env`).
3. **Register services**: the framework's own (database, cache, mail...), then
   yours from the `providers` list in `config/app.php`.
4. **Load your routes**: `App\Providers\RouteServiceProvider` reads
   `routes/health.php` and `routes/api.php`.

### 3. Global middleware

**Middleware** is code that runs around every request, like airport security
checks you pass on the way in and again on the way out. These run for every
request, in this order (`src/Foundation/Http/Kernel.php`):

| Middleware | What it does |
| --- | --- |
| `TrustProxies` | Works out the caller's real IP and whether they used HTTPS. |
| `AddSecurityHeaders` | Adds protective headers to every response. |
| `RequestIdMiddleware` | Tags each request with an `X-Request-ID`, to find it in logs. |
| `HandleCors` | Lets browser apps on allowed websites call you (off by default). |
| `AddQueuedCookiesToResponse` | Attaches any cookies you set. |
| `StartSession` | Loads the visitor's session (who's logged in), saves it afterwards. |
| `HandleExceptions` | Catches errors and turns them into JSON error responses. |
| `PreventRequestForgery` | Blocks forged requests from other websites (CSRF). |

### 4. The router

The router compares the URL and method (`GET`, `POST`...) with your routes:

- **A match:** it notes which controller method to run, and pulls values
  out of the URL. `/api/v1/notes/5` gives `{note} = 5`.
- **No route for that URL:** `404 NOT_FOUND`.
- **The URL exists, but not for that method:** `405 METHOD_NOT_ALLOWED`.

### 5. Route middleware

Some middleware is attached to only certain routes in `routes/api.php`:

- `ThrottleRequests` limits how many requests a caller may make
  (`429 TOO_MANY_REQUESTS` when over the limit).
- `Authenticate` refuses visitors who aren't logged in (`401 UNAUTHENTICATED`).

### 6. Your controller runs

Before calling your method, VtPhp fills in its parameters for you:

- `Note $note` with a `{note}` route: VtPhp **looks the note up** in the
  database. If it doesn't exist, the request ends with `404` and your method
  never runs.
- `int $id` turns the URL value into a number. Text like `abc` gives a `404`.
- A **form request** (`StoreNoteRequest $request`) is checked against its
  rules first. Bad input ends the request with `422` and your method never
  runs.
- Anything else, like `Mailer $mailer`, is created for you (see
  [Container](container.md)).

So when your code starts, the record exists and the input is valid.

### 7. Building the response

Whatever your method returns becomes the response:

| You return | The caller gets |
| --- | --- |
| A resource (`NoteResource::make($note)`) | `{"data": {...}}` |
| A collection (`NoteResource::collection(...)`) | `{"data": [...]}` |
| An array or a model | That data as JSON |
| `null` | `204 No Content` |
| `response()->json(...)` or another response | Exactly that |

### On the way out

The response passes back through the middleware in reverse: the session is
saved, cookies and security headers are added, and the response is sent.

## When something goes wrong

Any error, from a failed validation to a bug in your code, is caught by
`HandleExceptions` and returned in one consistent shape:

```json
{"success": false, "error": {"code": "NOT_FOUND", "message": "Note not found."}}
```

Because `HandleExceptions` sits inside the other middleware, error responses
still get the request ID, security headers and cookies. Unexpected errors
become `500 INTERNAL_SERVER_ERROR` and are written to `storage/logs/app.log`.
In production the reply hides the details. See [Errors](exceptions.md).

## Where does my code go?

| You want to... | Put it in |
| --- | --- |
| Add a URL | `routes/api.php` ([Routing](routing.md)) |
| Handle a request | A controller in `app/Http/Controllers` ([Controllers](controllers.md)) |
| Check input | A form request in `app/Http/Requests` ([Validation](validation.md)) |
| Shape the JSON output | A resource in `app/Http/Resources` ([Resources](resources.md)) |
| Work with a database table | A model in `app/Models` ([Models](models.md)) |
| Change the database structure | A migration in `database/migrations` ([Migrations](migrations.md)) |
| Run a check on many routes | Middleware in `app/Http/Middleware` ([Middleware](middleware.md)) |
| Send an email | A mailable in `app/Mail` ([Mail](mail.md)) |
| Set up a shared service | A service provider in `app/Providers` ([Container](container.md)) |

## Next steps

- [Your first API](your-first-api.md): see every step in action.
- [Middleware](middleware.md): write your own checks.
- [Errors](exceptions.md): customize error responses.
