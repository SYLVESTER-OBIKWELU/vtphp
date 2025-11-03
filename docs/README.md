# PHP Framework Documentation

A modern, Laravel-inspired PHP framework for building web applications and APIs.

## Table of Contents

1. [Installation](#installation)
2. [Getting Started](#getting-started)
3. [Routing](#routing)
4. [Controllers](#controllers)
5. [Models & Database](#models--database)
6. [Migrations](#migrations)
7. [Views](#views)
8. [Middleware](#middleware)
9. [Validation](#validation)
10. [API Development](#api-development)
11. [CLI Commands](#cli-commands)

## Installation

### Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB
- Composer

### Setup

1. **Clone or download the framework**

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Configure environment**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` file with your database credentials:

   ```
   DB_HOST=localhost
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

4. **Start development server**

   ```bash
   php artisan serve
   ```

   Visit `http://localhost:8000`

## Getting Started

### Directory Structure

```
framework/
├── app/
│   ├── controller/       # Controllers
│   ├── middleware/       # Custom middleware
│   └── Models/          # Eloquent-like models
├── config/              # Configuration files
├── core/                # Framework core files
├── database/
│   └── migrations/      # Database migrations
├── public_html/         # Public web root
│   └── index.php        # Entry point
├── resource/
│   ├── css/            # CSS files
│   ├── js/             # JavaScript files
│   └── views/          # Blade-like views
├── routes/
│   ├── web.php         # Web routes
│   └── api.php         # API routes
├── storage/
│   ├── cache/          # Cache files
│   └── logs/           # Log files
└── artisan             # CLI tool
```

### Hello World Example

**routes/web.php:**

```php
<?php

$router->get('/hello', function($request) {
    return 'Hello, World!';
});
```

## Routing

### Basic Routing

The framework supports all HTTP methods:

```php
// routes/web.php
$router->get('/users', ['App\Controller\UserController', 'index']);
$router->post('/users', ['App\Controller\UserController', 'store']);
$router->put('/users/{id}', ['App\Controller\UserController', 'update']);
$router->delete('/users/{id}', ['App\Controller\UserController', 'destroy']);
```

### Route Parameters

```php
$router->get('/users/{id}', function($request) {
    $id = $request->params('id');
    return "User ID: {$id}";
});

$router->get('/posts/{postId}/comments/{commentId}', function($request) {
    $postId = $request->params('postId');
    $commentId = $request->params('commentId');
    return "Post {$postId}, Comment {$commentId}";
});
```

### Route Groups

```php
$router->group(['prefix' => 'admin'], function($router) {
    $router->get('/dashboard', ['AdminController', 'dashboard']);
    $router->get('/users', ['AdminController', 'users']);
});

// With middleware
$router->group([
    'prefix' => 'admin',
    'middleware' => ['App\Middleware\AuthMiddleware']
], function($router) {
    $router->get('/dashboard', ['AdminController', 'dashboard']);
});
```

### Resource Routes

```php
// Creates all CRUD routes
$router->resource('/users', 'App\Controller\UserController');

// For API (no create/edit routes)
$router->apiResource('/users', 'App\Controller\Api\UserController');
```

This creates:

- GET /users - index()
- GET /users/create - create()
- POST /users - store()
- GET /users/{id} - show()
- GET /users/{id}/edit - edit()
- PUT /users/{id} - update()
- DELETE /users/{id} - destroy()

## Controllers

### Creating Controllers

```bash
php artisan make:controller UserController
php artisan make:controller UserController --resource
php artisan make:controller Api/UserController --api
```

### Basic Controller

```php
<?php

namespace App\Controller;

use Core\Controller;
use Core\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::all();
        return $this->view('users.index', compact('users'));
    }

    public function show(Request $request)
    {
        $id = $request->params('id');
        $user = User::find($id);

        if (!$user) {
            $this->abort(404, 'User not found');
        }

        return $this->view('users.show', compact('user'));
    }

    public function store(Request $request)
    {
        $data = $this->validate($request->all(), [
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);

        $user = User::create($data);

        return $this->redirect('/users');
    }
}
```

### API Controller

```php
<?php

namespace App\Controller\Api;

use Core\Controller;
use Core\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::all();
        return $this->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users'
        ]);

        $user = User::create($data);

        return $this->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }
}
```

## Models & Database

### Creating Models

```bash
php artisan make:model User
php artisan make:model Post --migration
```

### Basic Model

```php
<?php

namespace App\Models;

use Core\Model;

class User extends Model
{
    protected static $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected $casts = [
        'is_admin' => 'bool',
        'settings' => 'json'
    ];
}
```

### Querying

```php
// Retrieve all records
$users = User::all();

// Find by ID
$user = User::find(1);
$user = User::findOrFail(1); // Throws exception if not found

// Where clauses
$users = User::where('status', 'active')->get();
$users = User::where('age', '>', 18)->get();
$users = User::whereIn('role', ['admin', 'moderator'])->get();
$users = User::whereNull('deleted_at')->get();

// Ordering and limiting
$users = User::orderBy('created_at', 'DESC')->limit(10)->get();

// First record
$user = User::where('email', 'test@example.com')->first();

// Count
$count = User::where('status', 'active')->count();

// Chaining
$users = User::where('status', 'active')
    ->where('age', '>', 18)
    ->orderBy('name')
    ->limit(10)
    ->get();
```

### Creating & Updating

```php
// Create
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Update
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Or
$user->update(['name' => 'Jane Doe']);

// Delete
$user->delete();
User::destroy(1);
User::destroy([1, 2, 3]);
```

### Pagination

```php
$users = User::where('status', 'active')
    ->paginate(15, 1); // 15 per page, page 1

// Returns:
// [
//     'data' => [...],
//     'total' => 100,
//     'per_page' => 15,
//     'current_page' => 1,
//     'last_page' => 7,
//     'from' => 1,
//     'to' => 15
// ]
```

## Migrations

### Creating Migrations

```bash
php artisan make:migration create_users_table
php artisan make:migration add_status_to_users_table
```

### Migration Structure

```php
<?php

use Core\Migration;
use Core\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('users');
    }
}
```

### Available Column Types

```php
$table->id();                          // Auto-increment ID
$table->bigIncrements('id');           // BIGINT auto-increment
$table->increments('id');              // INT auto-increment
$table->string('name', 255);           // VARCHAR
$table->text('description');           // TEXT
$table->mediumText('content');         // MEDIUMTEXT
$table->longText('content');           // LONGTEXT
$table->integer('votes');              // INT
$table->bigInteger('votes');           // BIGINT
$table->tinyInteger('votes');          // TINYINT
$table->smallInteger('votes');         // SMALLINT
$table->float('amount', 8, 2);         // FLOAT
$table->double('amount', 8, 2);        // DOUBLE
$table->decimal('amount', 8, 2);       // DECIMAL
$table->boolean('confirmed');          // TINYINT(1)
$table->date('created_at');            // DATE
$table->dateTime('created_at');        // DATETIME
$table->timestamp('created_at');       // TIMESTAMP
$table->time('sunrise');               // TIME
$table->json('options');               // JSON
$table->enum('status', ['active', 'inactive']);
$table->timestamps();                  // created_at & updated_at
$table->softDeletes();                 // deleted_at
```

### Column Modifiers

```php
$table->string('email')->nullable();
$table->string('name')->default('Guest');
$table->integer('votes')->unsigned();
$table->string('email')->unique();
$table->string('name')->index();
```

### Foreign Keys

```php
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade')
    ->onUpdate('cascade');
```

### Running Migrations

```bash
php artisan migrate                  # Run migrations
php artisan migrate:rollback         # Rollback last batch
php artisan migrate:reset            # Rollback all
php artisan migrate:fresh            # Drop all & re-migrate
```

## Views

### Creating Views

Views are stored in `resource/views/` and use Blade-like syntax.

**resource/views/welcome.php:**

```php
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
</head>
<body>
    <h1>{{ $heading }}</h1>

    @if($users)
        <ul>
            @foreach($users as $user)
                <li>{{ $user->name }}</li>
            @endforeach
        </ul>
    @else
        <p>No users found.</p>
    @endif
</body>
</html>
```

### Blade Syntax

```php
// Escaped output
{{ $variable }}

// Unescaped output
{!! $html !!}

// Conditionals
@if($condition)
    // code
@elseif($other)
    // code
@else
    // code
@endif

// Loops
@foreach($users as $user)
    {{ $user->name }}
@endforeach

@for($i = 0; $i < 10; $i++)
    {{ $i }}
@endfor

@while($condition)
    // code
@endwhile

// PHP Code
@php
    $name = strtoupper($name);
@endphp

// Include
@include('partials.header')

// CSRF Token
@csrf

// Method Field
@method('PUT')
```

### Using Views

```php
// In controller
return $this->view('users.index', [
    'users' => $users,
    'title' => 'User List'
]);

// Using helper
return view('users.index', compact('users', 'title'));
```

## Middleware

### Creating Middleware

```bash
php artisan make:middleware CheckAge
```

### Middleware Structure

```php
<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Request;

class CheckAge extends Middleware
{
    public function handle(Request $request)
    {
        $age = $request->input('age');

        if ($age < 18) {
            $this->abort(403, 'You must be 18 or older');
        }

        return $this->next();
    }
}
```

### Applying Middleware

```php
// On routes
$router->group([
    'middleware' => ['App\Middleware\AuthMiddleware']
], function($router) {
    $router->get('/dashboard', ['DashboardController', 'index']);
});

// Multiple middleware
$router->group([
    'middleware' => [
        'App\Middleware\AuthMiddleware',
        'App\Middleware\CheckAge'
    ]
], function($router) {
    // routes
});
```

## Validation

### Basic Validation

```php
$data = $this->validate($request->all(), [
    'name' => 'required|string|min:3|max:255',
    'email' => 'required|email|unique:users,email',
    'age' => 'required|integer|min:18',
    'password' => 'required|min:6|confirmed',
    'role' => 'required|in:admin,user,moderator'
]);
```

### Available Rules

- `required` - Field must be present and not empty
- `email` - Must be valid email
- `string` - Must be string
- `integer` - Must be integer
- `numeric` - Must be numeric
- `array` - Must be array
- `boolean` - Must be boolean
- `url` - Must be valid URL
- `min:value` - Minimum length/value
- `max:value` - Maximum length/value
- `in:foo,bar` - Must be in list
- `unique:table,column,ignoreId` - Must be unique in database
- `exists:table,column` - Must exist in database
- `confirmed` - Must match field_confirmation
- `same:field` - Must match another field
- `different:field` - Must be different from another field

### Handling Validation Errors

```php
use Core\ValidationException;

try {
    $data = $this->validate($request->all(), $rules);
} catch (ValidationException $e) {
    return $this->json([
        'errors' => $e->errors()
    ], 422);
}
```

## API Development

### API Routes

**routes/api.php:**

```php
$router->group(['prefix' => 'api'], function($router) {
    // Public routes
    $router->post('/login', ['Api\AuthController', 'login']);
    $router->post('/register', ['Api\AuthController', 'register']);

    // Protected routes
    $router->group([
        'middleware' => ['App\Middleware\AuthMiddleware']
    ], function($router) {
        $router->apiResource('/users', 'Api\UserController');
        $router->apiResource('/posts', 'Api\PostController');
    });
});
```

### API Controller

```php
<?php

namespace App\Controller\Api;

use Core\Controller;
use Core\Request;
use App\Models\Post;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::orderBy('created_at', 'DESC')
            ->paginate(20, $request->query('page', 1));

        return $this->json($posts);
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validate($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'status' => 'in:draft,published'
            ]);

            $post = Post::create($data);

            return $this->json([
                'message' => 'Post created successfully',
                'data' => $post
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Request $request)
    {
        $post = Post::findOrFail($request->params('id'));
        return $this->json(['data' => $post]);
    }

    public function update(Request $request)
    {
        $post = Post::findOrFail($request->params('id'));

        $data = $this->validate($request->all(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'status' => 'in:draft,published'
        ]);

        $post->update($data);

        return $this->json([
            'message' => 'Post updated successfully',
            'data' => $post
        ]);
    }

    public function destroy(Request $request)
    {
        $post = Post::findOrFail($request->params('id'));
        $post->delete();

        return $this->json([
            'message' => 'Post deleted successfully'
        ]);
    }
}
```

### CORS Middleware

Already included: `App\Middleware\CorsMiddleware`

```php
$router->group([
    'middleware' => ['App\Middleware\CorsMiddleware']
], function($router) {
    // API routes with CORS enabled
});
```

## CLI Commands

### Available Commands

```bash
php artisan list                          # List all commands
php artisan serve                         # Start development server
php artisan serve --port=8080             # Custom port

# Generators
php artisan make:controller UserController
php artisan make:controller UserController --resource
php artisan make:controller Api/UserController --api
php artisan make:model User
php artisan make:model Post --migration
php artisan make:migration create_posts_table
php artisan make:middleware CheckAge

# Migrations
php artisan migrate                       # Run migrations
php artisan migrate:rollback              # Rollback last batch
php artisan migrate:reset                 # Rollback all
php artisan migrate:fresh                 # Drop all & re-migrate
```

### Helper Functions

```php
view($view, $data)              // Render view
redirect($url)                  // Redirect
dd($var)                        // Dump and die
dump($var)                      // Dump
env($key, $default)             // Get environment variable
config($key, $default)          // Get config value
app()                           // Get app instance
request()                       // Get request instance
response()                      // Get response instance
session($key, $default)         // Get session value
old($key, $default)             // Get old input
csrf_token()                    // Get CSRF token
csrf_field()                    // CSRF input field
method_field($method)           // Method input field
asset($path)                    // Asset URL
url($path)                      // Full URL
back()                          // Redirect back
abort($code, $message)          // Abort with error
now()                           // Current timestamp
today()                         // Current date
```

## Error Handling

The framework includes a beautiful exception handler that displays errors in development mode similar to Laravel's Whoops.

### Features

- **Beautiful error pages** with syntax highlighting
- **Stack traces** with file previews
- **JSON responses** for API requests
- **Error logging** in storage/logs/error.log

### Configuration

Edit `config/app.php`:

```php
'debug' => env('APP_DEBUG', true)  // Set to false in production
```

---

## License

MIT License

## Support

For issues and questions, please refer to the documentation or create an issue in the repository.
