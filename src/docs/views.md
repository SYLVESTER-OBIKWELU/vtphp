# Views (Blade)

Templates use `eftec/bladeone` (a standalone Blade implementation with no
Illuminate/view dependency) behind `VtPhp\View\BladeEngine`, resolved from
`resources/views/` and compiled to `storage/framework/views/`.

Render a view with the `view()` helper — returns the rendered HTML as a
string (it doesn't send a response itself):

```php
echo view('emails.welcome', ['user' => $user]);
```

`emails.welcome` maps to `resources/views/emails/welcome.blade.php` (dots
become directory separators, same as Laravel). Standard Blade syntax is
supported: `{{ $variable }}`, `@if`/`@endif`, `@foreach`/`@endforeach`,
`@include('partial')`, layouts via `@extends`/`@section`/`@yield`, etc. — see
[BladeOne's documentation](https://github.com/EFTEC/BladeOne) for the full
directive list, since this framework doesn't restrict or wrap it.

Views are most commonly used for email bodies (see [Mail](mail.md)), but
nothing stops you from returning rendered HTML directly from a controller
action for server-rendered pages.
