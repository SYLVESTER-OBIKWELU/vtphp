# Views (Blade templates)

A view is a template file that produces HTML: mostly normal HTML, with
placeholders for your data. VtPhp uses Blade, the same template syntax as
Laravel. In an API, you'll mainly use views for email bodies.

## The quick version

Create a template:

```blade
{{-- resources/views/greeting.blade.php --}}
<h1>Hello, {{ $name }}!</h1>
```

Render it with the `view()` helper:

```php
$html = view('greeting', ['name' => 'Ada']);
// "<h1>Hello, Ada!</h1>"
```

`view()` returns the finished HTML as a string. It doesn't send anything to
the browser by itself.

## Where templates live

Templates are files ending in `.blade.php` inside `resources/views/`. You
refer to them by name, with dots instead of slashes and without the
extension:

| Name you pass to `view()` | File |
| --- | --- |
| `'greeting'` | `resources/views/greeting.blade.php` |
| `'emails.welcome'` | `resources/views/emails/welcome.blade.php` |

The app ships with three email templates in `resources/views/emails/`:
`welcome`, `reset-password` and `verify-email`.

## Showing data

The second argument of `view()` is an array. Each key becomes a variable in
the template:

```php
view('emails.welcome', ['user' => $user]);
```

```blade
{{-- resources/views/emails/welcome.blade.php --}}
<h1>Welcome, {{ $user->name }}!</h1>
<p>Your account was created with the email address <strong>{{ $user->email }}</strong>.</p>
```

`{{ $value }}` prints a value **safely**. Characters like `<` and `>` are
escaped, so a user who names themselves `<script>` can't inject code into
your page or email. Use this almost everywhere.

`{!! $value !!}` prints a value **without** escaping. Only use it for HTML
you produced yourself and trust completely.

## Conditions and loops

```blade
@if ($user->email)
    <p>We're glad to have you on board.</p>
@else
    <p>Please add an email address.</p>
@endif

<ul>
@foreach ($items as $item)
    <li>{{ $item }}</li>
@endforeach
</ul>
```

Put each directive (the words starting with `@`) on its own line, as shown,
so they're always recognised.

Templates can also include other templates (`@include('emails.footer')`) and
share a layout (`@extends`, `@section`, `@yield`). See
[BladeOne's documentation](https://github.com/EFTEC/BladeOne) for every
directive. BladeOne is the library VtPhp uses to run Blade templates.

## Using views in emails

This is the most common use. A mailable names its template with `view()`:

```php
// app/Mail/WelcomeEmail.php
$this->to((string) $this->user->email)
    ->subject('Welcome to VtPhp!')
    ->view('emails.welcome', ['user' => $this->user]);
```

See [Mail](mail.md) for the full picture.

## Going further

### Returning an HTML page from a controller

If a controller returns the string from `view()` directly, it's sent as a
JSON string, not as a web page. To send real HTML, wrap it in a response with
an HTML content type:

```php
// app/Http/Controllers/PageController.php
namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface;

final class PageController
{
    public function about(): ResponseInterface
    {
        return response()->make(view('pages.about'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // VtPhp's default security headers block styles, scripts and
            // images. Allow what your page needs from your own site:
            'Content-Security-Policy' => "default-src 'self'",
        ]);
    }
}
```

VtPhp adds a strict `Content-Security-Policy` to every response, because
it's built for JSON APIs. That header tells browsers not to load any CSS,
JavaScript or images. Setting your own on the response replaces it, as shown
above (see [Security](security.md#security-headers)).

### Where compiled templates go

The first time a template is used, it's turned into plain PHP and saved in
`storage/framework/views/`. That makes later renders fast. Changes to your
templates are picked up automatically. Both folders are set in
`config/view.php`.

## Common mistakes

- **"Template not found: emails.order_shipped".** The file is missing or
  misnamed. Check that `resources/views/emails/order_shipped.blade.php`
  exists. Dots in the name are folders.
- **HTML shows up as text, or inside quotes.** You returned `view(...)`
  directly from a controller. Wrap it in `response()->make()` with a
  `text/html` content type.
- **The page loads with no styling.** The default security headers block
  CSS. Set your own `Content-Security-Policy` header on that response.
- **A variable is undefined in the template.** It wasn't passed in the array
  you gave `view()`.
- **The template doesn't update.** Make sure `storage/framework/views/`
  is writable by PHP.

## Next steps

- [Mail](mail.md): sending emails built from these templates.
- [Controllers](controllers.md): returning responses.
- [Security](security.md): the default response headers.
