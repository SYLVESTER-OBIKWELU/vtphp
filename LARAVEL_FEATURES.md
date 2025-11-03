# Laravel-Like Features in VTPHP Framework

## 🚀 Routing Features

### Route Methods with Chaining

```php
// Named routes
$router->get('/users', [UserController::class, 'index'])->name('users.index');

// Middleware
$router->get('/admin', [AdminController::class, 'dashboard'])
    ->middleware(['auth', 'admin']);

// Multiple chained methods
$router->get('/posts/{id}', [PostController::class, 'show'])
    ->name('posts.show')
    ->middleware('auth')
    ->where('id', '[0-9]+');

// Where constraints
$router->get('/user/{id}', [UserController::class, 'show'])
    ->where('id', '[0-9]+');

$router->get('/post/{slug}', [PostController::class, 'show'])
    ->where('slug', '[a-z0-9-]+');

// Multiple where constraints
$router->get('/archive/{year}/{month}', [ArchiveController::class, 'show'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}']);
```

### Route Groups

```php
// With prefix and middleware
$router->group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    $router->get('/users', [AdminController::class, 'users'])->name('admin.users');
});
```

### Resource Routes

```php
// Full resource
$router->resource('/posts', PostController::class);
// Creates: index, create, store, show, edit, update, destroy

// API resource (no create/edit)
$router->apiResource('/api/posts', ApiPostController::class);
```

## 🎨 Blade Directives & Helpers

### URL & Routing Helpers

```blade
{{-- Named routes --}}
<a href="{{ route('users.index') }}">Users</a>
<a href="{{ route('users.show', ['id' => $user->id]) }}">View User</a>

{{-- URL generation --}}
<a href="{{ url('/about') }}">About</a>

{{-- Asset URLs --}}
<img src="{{ asset('images/logo.png') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">

{{-- Or use directives --}}
@route('users.index')
@url('/about')
@asset('images/logo.png')
```

### Number Formatting

```php
// In PHP or Blade
{{ number_format(1500) }}                    // 1,500
{{ number_format_short(1500000) }}          // 1.5M
{{ format_bytes(1024000) }}                 // 1000.00 KB
{{ currency(99.99, 'USD') }}                // $99.99
{{ percentage(75, 100) }}                   // 75.00%
```

### Date Formatting

```blade
{{-- Human readable dates --}}
{{ diff_for_humans($user->created_at) }}    // "2 days ago"
{{ format_date($user->created_at, 'M d, Y') }}  // "Nov 03, 2025"
```

### Collection Methods

```blade
{{-- Arrays are auto-converted to collections --}}
@if ($users->count() > 0)
    <p>Found {{ $users->count() }} users</p>
@endif

{{ $users->first()->name }}
{{ $users->pluck('email')->implode(', ') }}
{{ $users->sum('age') }}
{{ $users->avg('age') }}
```

### Control Structures

```blade
{{-- If statements --}}
@if ($user->isAdmin())
    <p>Admin user</p>
@elseif ($user->isModerator())
    <p>Moderator</p>
@else
    <p>Regular user</p>
@endif

{{-- Unless --}}
@unless ($user->isGuest())
    <p>Welcome back!</p>
@endunless

{{-- Loops --}}
@foreach($users as $user)
    <li>{{ $user->name }}</li>
@endforeach

@forelse($users as $user)
    <li>{{ $user->name }}</li>
@empty
    <p>No users found</p>
@endforelse

@for($i = 0; $i < 10; $i++)
    <p>{{ $i }}</p>
@endfor

@while($condition)
    <p>Loop</p>
@endwhile
```

### Authentication

```blade
@auth
    <p>Welcome, {{ auth()->user()->name }}</p>
@endauth

@guest
    <a href="/login">Login</a>
@endguest
```

### Forms

```blade
<form method="POST" action="{{ route('users.store') }}">
    @csrf
    @method('PUT')

    <input type="text" name="name" value="{{ old('name') }}">

    @error('name')
        <span class="error">{{ $message }}</span>
    @enderror

    <button type="submit">Submit</button>
</form>
```

### Conditional Classes & Attributes

```blade
<div @class(['active' => $isActive, 'disabled' => $isDisabled])></div>

<input type="checkbox" @checked($isChecked)>
<input type="text" @disabled($isDisabled)>
<input type="text" @readonly($isReadonly)>
<input type="text" @required($isRequired)>
<option @selected($isSelected)>Option</option>
```

### Environment Checks

```blade
@env('local')
    <p>Development mode</p>
@endenv

@production
    <p>Production mode</p>
@endproduction
```

### Sections & Layouts

```blade
{{-- layout.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    @stack('styles')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>

{{-- page.blade.php --}}
@extends('layout')

@section('content')
    <h1>Page Content</h1>
@endsection

@push('scripts')
    <script src="page.js"></script>
@endpush
```

### Components & Slots

```blade
{{-- alert.blade.php --}}
<div class="alert alert-{{ $type }}">
    {{ $slot }}
    {{ $title ?? '' }}
</div>

{{-- Usage --}}
@component('alert', ['type' => 'success'])
    @slot('title')
        Success!
    @endslot
    Operation completed successfully.
@endcomponent
```

### Debug Helpers

```blade
@dump($user)           {{-- Dump variable --}}
@dd($user)             {{-- Dump and die --}}
@json($user)           {{-- Output as JSON --}}
```

## 🛠️ PHP Helper Functions

### Path Helpers

```php
base_path('app/Models')           // /path/to/framework/app/Models
public_path('css/app.css')        // /path/to/framework/public_html/css/app.css
storage_path('logs/app.log')      // /path/to/framework/storage/logs/app.log
resource_path('views')            // /path/to/framework/resources/views
```

### Array/Data Helpers

```php
data_get($array, 'user.name', 'default')   // Get nested data with dot notation
data_set($array, 'user.name', 'John')      // Set nested data
collect([1, 2, 3])->map(fn($n) => $n * 2)  // Collection helpers
```

### Utility Helpers

```php
optional($user)->name              // Safely access properties
blank($value)                      // Check if value is blank
filled($value)                     // Check if value is filled
e($string)                         // Escape HTML
class_basename(User::class)        // Get class basename
throw_if($condition, 'Error')      // Conditional exception
throw_unless($condition, 'Error')  // Conditional exception
with($value, fn($v) => $v * 2)     // Pass value to callback
transform($value, fn($v) => $v)    // Transform if filled
```

### String Helpers

```php
str_slug('Hello World')            // hello-world
str_camel('hello_world')           // helloWorld
str_snake('HelloWorld')            // hello_world
str_kebab('HelloWorld')            // hello-world
str_limit('Long text', 10)         // Long text...
str_random(16)                     // Random string
str_contains('hello', 'ell')       // true
str_starts_with('hello', 'he')     // true
str_ends_with('hello', 'lo')       // true
```

### Session & Cache

```php
session('key', 'default')          // Get session value
cache('key', 'default')            // Get cache value
old('input', 'default')            // Get old input
csrf_token()                       // Get CSRF token
```

### Response Helpers

```php
redirect('/home')                  // Redirect
back()                            // Redirect back
abort(404, 'Not found')           // Abort with error
view('users.index', $data)        // Render view
```

## 📊 Usage Examples

### Complete Route Example

```php
// routes/web.php
$router->get('/posts/{id}', [PostController::class, 'show'])
    ->name('posts.show')
    ->middleware(['auth'])
    ->where('id', '[0-9]+');

$router->group(['prefix' => 'api', 'middleware' => ['api']], function($router) {
    $router->get('/users', [ApiController::class, 'users'])->name('api.users');
    $router->post('/users', [ApiController::class, 'store'])->name('api.users.store');
});
```

### Complete Blade Example

```blade
@extends('layouts.app')

@section('content')
<div class="users">
    <h1>Users ({{ $users->count() }})</h1>

    @forelse($users as $user)
        <div class="user-card">
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <small>Joined {{ diff_for_humans($user->created_at) }}</small>

            @auth
                <a href="{{ route('users.edit', ['id' => $user->id]) }}">Edit</a>
            @endauth
        </div>
    @empty
        <p>No users found.</p>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
    console.log('User list loaded');
</script>
@endpush
```

## ✅ Features Summary

- ✅ Named routes with `->name()`
- ✅ Route middleware with `->middleware([])`
- ✅ Route parameter constraints with `->where()`
- ✅ Route groups with prefix and middleware
- ✅ Resource and API resource routes
- ✅ `route()` helper for named route URLs
- ✅ `url()` helper for URL generation
- ✅ `asset()` helper for asset URLs
- ✅ Path helpers (base_path, public_path, etc.)
- ✅ Number formatting (number_format_short, currency, percentage)
- ✅ Date formatting (diff_for_humans, format_date)
- ✅ Collection methods on arrays in Blade
- ✅ All Laravel blade directives (@if, @foreach, @auth, etc.)
- ✅ String helpers (str_slug, str_camel, etc.)
- ✅ Array/data helpers (data_get, data_set, collect)
- ✅ Utility helpers (optional, blank, filled, etc.)
- ✅ View caching control via VIEW_CACHE env variable
