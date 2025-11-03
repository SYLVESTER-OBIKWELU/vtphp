# Blade Templating System

## Table of Contents

- [Introduction](#introduction)
- [Layouts](#layouts)
- [Components](#components)
- [Directives](#directives)
- [Control Structures](#control-structures)
- [Including Views](#including-views)

## Introduction

The framework includes a powerful Blade-like templating engine that compiles your templates into plain PHP for maximum performance.

## Layouts

### Creating Layouts

Create a master layout in `resource/views/layouts/app.php`:

```php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
</head>
<body>
    <header>
        @yield('header')
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        @yield('footer')
    </footer>
</body>
</html>
```

### Extending Layouts

Extend a layout in your views:

```php
@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    <h1>Page Content</h1>
    <p>This is the main content.</p>
@endsection
```

## Components

### Creating Components

Create reusable components in `resource/views/components/`:

```php
<!-- components/card.php -->
<div class="card">
    @isset($title)
        <h3>{{ $title }}</h3>
    @endisset

    <div class="card-body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
```

### Using Components

```php
@component('components.card')
    @slot('title')
        Card Title
    @endslot

    This is the main content of the card.

    @slot('footer')
        Card Footer
    @endslot
@endcomponent
```

## Directives

### Echo Directives

```php
<!-- Escaped echo -->
{{ $variable }}

<!-- Unescaped echo (use with caution) -->
{!! $htmlContent !!}
```

### Control Structures

#### If Statements

```php
@if($user->isAdmin())
    <p>You are an admin</p>
@elseif($user->isModerator())
    <p>You are a moderator</p>
@else
    <p>You are a regular user</p>
@endif
```

#### Unless

```php
@unless($user->isAdmin())
    <p>You are not an administrator</p>
@endunless
```

#### Isset & Empty

```php
@isset($records)
    <p>Records are defined</p>
@endisset

@empty($records)
    <p>No records found</p>
@endempty
```

#### Auth & Guest

```php
@auth
    <p>You are logged in</p>
@endauth

@guest
    <p>Please log in</p>
@endguest
```

### Loops

#### Foreach

```php
@foreach($users as $user)
    <p>{{ $user->name }}</p>
@endforeach
```

#### Forelse

```php
@forelse($users as $user)
    <p>{{ $user->name }}</p>
@empty
    <p>No users found</p>
@endforelse
```

#### For Loop

```php
@for($i = 0; $i < 10; $i++)
    <p>Number: {{ $i }}</p>
@endfor
```

#### While Loop

```php
@while($condition)
    <p>Still looping...</p>
@endwhile
```

#### Break & Continue

```php
@foreach($users as $user)
    @if($user->type === 'banned')
        @continue
    @endif

    <p>{{ $user->name }}</p>

    @if($loop->index === 10)
        @break
    @endif
@endforeach
```

### Including Views

```php
<!-- Include a view -->
@include('partials.header')

<!-- Include with data -->
@include('partials.user-card', ['user' => $user])
```

### PHP Blocks

```php
@php
    $formatted = date('Y-m-d', strtotime($date));
    $total = $price * $quantity;
@endphp
```

### Form Directives

```php
<form method="POST" action="/users">
    @csrf
    @method('PUT')

    <input type="text" name="name">
    <button type="submit">Submit</button>
</form>
```

## Examples

### Complete Page Example

```php
@extends('layouts.app')

@section('title', 'User Profile')

@section('content')
    <div class="profile">
        <h1>{{ $user->name }}</h1>

        @if($user->avatar)
            <img src="{{ $user->avatar }}" alt="{{ $user->name }}">
        @endif

        @component('components.card')
            @slot('title')
                User Information
            @endslot

            <dl>
                <dt>Email</dt>
                <dd>{{ $user->email }}</dd>

                <dt>Joined</dt>
                <dd>{{ $user->created_at }}</dd>

                @isset($user->bio)
                    <dt>Bio</dt>
                    <dd>{{ $user->bio }}</dd>
                @endisset
            </dl>

            @slot('footer')
                @auth
                    @if($currentUser->id === $user->id)
                        <a href="/profile/edit">Edit Profile</a>
                    @endif
                @endauth
            @endslot
        @endcomponent

        <h2>Posts</h2>
        @forelse($user->posts as $post)
            @include('partials.post-preview', ['post' => $post])
        @empty
            <p>No posts yet.</p>
        @endforelse
    </div>
@endsection
```

## View Cache

Views are automatically compiled and cached in `storage/cache/views/`. To clear the view cache:

```bash
php artisan view:clear
```

## Best Practices

1. **Use layouts** - Create master layouts for consistent page structure
2. **Create components** - Build reusable UI components for common elements
3. **Always escape output** - Use `{{ }}` by default, only use `{!! !!}` for trusted HTML
4. **Keep logic minimal** - Complex logic belongs in controllers, not views
5. **Use includes** - Break large views into smaller, reusable partials
6. **Clear cache** - Clear view cache after making template changes in production

## Tips

- Views are compiled only when the source file changes
- Use `@php` blocks sparingly - prefer passing data from controllers
- Component slots can contain any valid Blade syntax
- Nested layouts are supported using multiple `@extends`
- Use `@include` for partials that don't need slots
