# VTPHP Framework - Laravel-Like Features Examples

## Quick Start Examples

### 1. Routes with Names and Middleware

```php
// routes/web.php

// Basic named route
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

// Route with middleware
$router->get('/admin', [AdminController::class, 'index'])
    ->name('admin.dashboard')
    ->middleware(['auth', 'admin']);

// Route with parameter constraints
$router->get('/posts/{id}', [PostController::class, 'show'])
    ->name('posts.show')
    ->where('id', '[0-9]+');

// Route with multiple constraints
$router->get('/blog/{year}/{month}/{slug}', [BlogController::class, 'show'])
    ->name('blog.post')
    ->where([
        'year' => '[0-9]{4}',
        'month' => '[0-9]{2}',
        'slug' => '[a-z0-9-]+'
    ]);
```

### 2. Route Groups

```php
// Admin routes with prefix and middleware
$router->group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard'])
        ->name('admin.dashboard');

    $router->get('/users', [AdminController::class, 'users'])
        ->name('admin.users');

    $router->get('/settings', [AdminController::class, 'settings'])
        ->name('admin.settings');
});

// API routes with prefix
$router->group(['prefix' => 'api/v1'], function($router) {
    $router->get('/users', [ApiController::class, 'users']);
    $router->post('/users', [ApiController::class, 'createUser']);
});
```

### 3. Resource Routes

```php
// Full resource routes (7 routes)
$router->resource('/posts', PostController::class);
// Creates:
// GET    /posts              -> index
// GET    /posts/create       -> create
// POST   /posts              -> store
// GET    /posts/{id}         -> show
// GET    /posts/{id}/edit    -> edit
// PUT    /posts/{id}         -> update
// DELETE /posts/{id}         -> destroy

// API resource routes (5 routes, no create/edit)
$router->apiResource('/api/products', ApiProductController::class);
```

### 4. Blade Templates with Laravel Helpers

```blade
<!-- resources/views/users/index.blade.php -->
@extends('layouts.app')

@section('title', 'Users List')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endpush

@section('content')
<div class="container">
    <div class="header">
        <h1>Users Management</h1>
        <div class="actions">
            <a href="{{ route('home') }}" class="btn">← Home</a>
            <a href="{{ route('users.create') }}" class="btn btn-primary">+ New User</a>
        </div>
    </div>

    @if ($users->count() > 0)
        <div class="stats">
            <p>Total Users: <strong>{{ number_format($users->count()) }}</strong></p>
            <p>Active Users: <strong>{{ $users->where('active', true)->count() }}</strong></p>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>
                        {{ $user->name }}
                        @if($user->is_admin)
                            <span class="badge">Admin</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span title="{{ format_date($user->created_at) }}">
                            {{ diff_for_humans($user->created_at) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('users.show', ['id' => $user->id]) }}"
                           class="btn btn-sm">View</a>

                        @auth
                        <a href="{{ route('users.edit', ['id' => $user->id]) }}"
                           class="btn btn-sm btn-warning">Edit</a>

                        <form method="POST"
                              action="{{ route('users.destroy', ['id' => $user->id]) }}"
                              style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Delete {{ $user->name }}?')">
                                Delete
                            </button>
                        </form>
                        @endauth
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            <p>No users found.</p>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Create First User</a>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    console.log('Users page loaded with {{ $users->count() }} users');
</script>
@endpush
```

### 5. Form with Validation and Old Input

```blade
<!-- resources/views/users/create.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Create New User</h1>

    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        <div class="form-group">
            <label for="name">Name</label>
            <input type="text"
                   id="name"
                   name="name"
                   value="{{ old('name') }}"
                   @required(true)>
            @error('name')
                <span class="error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   @required(true)>
            @error('email')
                <span class="error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="user" @selected(old('role') == 'user')>User</option>
                <option value="admin" @selected(old('role') == 'admin')>Admin</option>
            </select>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox"
                       name="active"
                       @checked(old('active', true))>
                Active Account
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="{{ route('users.index') }}" class="btn">Cancel</a>
        </div>
    </form>
</div>
@endsection
```

### 6. Using Helper Functions in PHP

```php
// app/Controller/UserController.php
namespace App\Controller;

use Core\Controller;

class UserController extends Controller
{
    public function index($request)
    {
        $users = \App\Models\User::all();

        // Format data using helpers
        $stats = [
            'total' => number_format($users->count()),
            'active' => $users->where('active', true)->count(),
            'percentage' => percentage(
                $users->where('active', true)->count(),
                $users->count()
            )
        ];

        return view('users.index', compact('users', 'stats'));
    }

    public function show($request)
    {
        $id = $request->param('id');
        $user = \App\Models\User::find($id);

        if (!$user) {
            abort(404, 'User not found');
        }

        // Use optional helper for safe property access
        $profile = optional($user->profile);

        return view('users.show', [
            'user' => $user,
            'profile' => $profile
        ]);
    }

    public function store($request)
    {
        $data = $request->all();

        // Validate
        if (blank($data['name'])) {
            return back()->with('error', 'Name is required');
        }

        // Create user
        $user = \App\Models\User::create($data);

        // Redirect to named route
        return redirect(route('users.show', ['id' => $user->id]))
            ->with('success', 'User created successfully');
    }
}
```

### 7. Advanced Number Formatting

```blade
<!-- Display file sizes -->
<p>File Size: {{ format_bytes(1536000) }}</p>
<!-- Output: 1.46 MB -->

<!-- Display large numbers -->
<p>Views: {{ number_format_short(1500000) }}</p>
<!-- Output: 1.5M -->

<!-- Display currency -->
<p>Price: {{ currency(99.99, 'USD') }}</p>
<!-- Output: $99.99 -->

<p>Price: {{ currency(2500, 'NGN') }}</p>
<!-- Output: ₦2,500.00 -->

<!-- Display percentages -->
<p>Progress: {{ percentage(75, 100) }}</p>
<!-- Output: 75.00% -->

<!-- Regular number formatting -->
<p>Total: {{ number_format(1234567.89, 2) }}</p>
<!-- Output: 1,234,567.89 -->
```

### 8. Date and Time Formatting

```blade
<!-- Human readable dates -->
<p>Posted {{ diff_for_humans($post->created_at) }}</p>
<!-- Output: "2 hours ago" or "3 days ago" -->

<!-- Custom format -->
<p>Date: {{ format_date($post->created_at, 'F j, Y') }}</p>
<!-- Output: "November 3, 2025" -->

<p>Time: {{ format_date($post->created_at, 'g:i A') }}</p>
<!-- Output: "2:30 PM" -->

<!-- Using Carbon directly in PHP -->
<?php
$now = now(); // Returns Carbon instance
$today = today(); // Returns Carbon for today at 00:00:00
?>
```

### 9. Collection Methods in Blade

```blade
<!-- Count items -->
<p>Total: {{ $users->count() }}</p>

<!-- Check if empty -->
@if($users->isEmpty())
    <p>No users</p>
@endif

<!-- Get first/last -->
<p>First User: {{ $users->first()->name }}</p>
<p>Last User: {{ $users->last()->name }}</p>

<!-- Filter and count -->
<p>Active Users: {{ $users->where('active', true)->count() }}</p>

<!-- Sum and average -->
<p>Total Age: {{ $users->sum('age') }}</p>
<p>Average Age: {{ number_format($users->avg('age'), 1) }}</p>

<!-- Pluck and join -->
<p>Emails: {{ $users->pluck('email')->implode(', ') }}</p>

<!-- Map and transform -->
@foreach($users->map(fn($u) => strtoupper($u->name)) as $name)
    <li>{{ $name }}</li>
@endforeach
```

### 10. Path Helpers

```php
// Get framework paths
$basePath = base_path();                    // /path/to/framework
$configPath = base_path('config/app.php'); // /path/to/framework/config/app.php

$publicPath = public_path();                // /path/to/framework/public_html
$cssPath = public_path('css/app.css');     // /path/to/framework/public_html/css/app.css

$storagePath = storage_path();              // /path/to/framework/storage
$logPath = storage_path('logs/app.log');   // /path/to/framework/storage/logs/app.log

$resourcePath = resource_path();            // /path/to/framework/resources
$viewPath = resource_path('views');        // /path/to/framework/resources/views
```

### 11. Utility Helpers

```php
// Safe property access
$userName = optional($user)->name; // Returns null if $user is null

// Check if blank or filled
if (blank($request->input('name'))) {
    // Handle empty input
}

if (filled($request->input('email'))) {
    // Process email
}

// HTML escaping
echo e($userInput); // Safe HTML output

// Conditional exceptions
throw_if($user->isBanned(), 'Exception', 'User is banned');
throw_unless($user->isActive(), 'Exception', 'User is not active');

// Transform values
$result = transform($value, fn($v) => strtoupper($v), 'default');

// Get nested data safely
$city = data_get($user, 'address.city', 'Unknown');
```

## Testing the Features

Visit your application at `http://127.0.0.1:5500/users` to see:

- Named route: `{{ route('users.index') }}`
- Asset loading: `{{ asset('css/app.css') }}`
- Date formatting: `{{ diff_for_humans($user->created_at) }}`
- Collection methods: `{{ $users->count() }}`
- Number formatting: `{{ number_format($value) }}`

All Laravel-like features are now fully integrated into your VTPHP framework! 🚀
