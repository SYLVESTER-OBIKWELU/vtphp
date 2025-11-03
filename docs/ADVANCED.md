# Additional Documentation

## Advanced Topics

### Custom Middleware

Create middleware for any cross-cutting concern:

```php
<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Request;

class RateLimitMiddleware extends Middleware
{
    public function handle(Request $request)
    {
        $ip = $request->ip();

        // Implement rate limiting logic
        if ($this->exceedsRateLimit($ip)) {
            $this->abort(429, 'Too many requests');
        }

        return $this->next();
    }

    protected function exceedsRateLimit($ip)
    {
        // Your rate limiting logic
        return false;
    }
}
```

### Database Transactions

```php
use Core\Database;

$db = Database::getInstance();

try {
    $db->beginTransaction();

    $user = User::create(['name' => 'John']);
    $profile = Profile::create(['user_id' => $user->id]);

    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
    throw $e;
}
```

### Custom Query Builder Methods

```php
// Raw queries
$users = User::query()->raw('SELECT * FROM users WHERE active = ?', [1]);

// Complex queries
$users = User::query()
    ->select('users.*', 'profiles.bio')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->where('users.status', 'active')
    ->orderBy('users.created_at', 'DESC')
    ->limit(10)
    ->get();
```

### Form Requests

Create a custom validation class:

```php
<?php

namespace App\Requests;

use Core\Validator;

class CreateUserRequest
{
    public static function rules()
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'age' => 'required|integer|min:18'
        ];
    }
}
```

Usage in controller:

```php
public function store(Request $request)
{
    $data = $this->validate(
        $request->all(),
        CreateUserRequest::rules()
    );

    $user = User::create($data);
    return $this->json(['data' => $user], 201);
}
```

### Model Relationships (Future Enhancement)

While not implemented in v1.0, you can manually handle relationships:

```php
class User extends Model
{
    public function posts()
    {
        return Post::where('user_id', $this->id)->get();
    }

    public function profile()
    {
        return Profile::where('user_id', $this->id)->first();
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts();
$profile = $user->profile();
```

### Session Management

```php
// Start session
session_start();

// Set session
$_SESSION['user_id'] = $user->id;
$_SESSION['username'] = $user->name;

// Get session using helper
$userId = session('user_id');

// Check if logged in
if (!session('user_id')) {
    redirect('/login');
}

// Destroy session
session_destroy();
```

### File Uploads

```php
public function upload(Request $request)
{
    if ($request->hasFile('avatar')) {
        $file = $request->file('avatar');

        $filename = time() . '_' . $file['name'];
        $destination = __DIR__ . '/../storage/uploads/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $this->json([
                'message' => 'File uploaded successfully',
                'file' => $filename
            ]);
        }
    }

    return $this->json(['error' => 'Upload failed'], 400);
}
```

### JSON API Responses

Consistent API response format:

```php
class ApiController extends Controller
{
    protected function success($data, $message = 'Success', $code = 200)
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error($message, $code = 400, $errors = [])
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}

// Usage
return $this->success($users, 'Users retrieved successfully');
return $this->error('Validation failed', 422, $validator->errors());
```

### Environment-based Configuration

```php
// In your code
if (env('APP_DEBUG')) {
    // Debug mode actions
}

$dbHost = config('database.host');
$appName = config('app.name', 'Default App');
```

### Caching (Simple Implementation)

```php
class Cache
{
    protected static $cachePath;

    public static function set($key, $value, $ttl = 3600)
    {
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        $path = self::getCachePath();
        file_put_contents($path . '/' . md5($key) . '.cache', serialize($data));
    }

    public static function get($key, $default = null)
    {
        $path = self::getCachePath() . '/' . md5($key) . '.cache';

        if (!file_exists($path)) {
            return $default;
        }

        $data = unserialize(file_get_contents($path));

        if ($data['expires'] < time()) {
            unlink($path);
            return $default;
        }

        return $data['value'];
    }

    protected static function getCachePath()
    {
        $path = __DIR__ . '/../storage/cache';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }
}
```

### Database Seeding

Create a seeder:

```php
<?php
// database/seeds/UserSeeder.php

use App\Models\User;

class UserSeeder
{
    public function run()
    {
        for ($i = 1; $i <= 50; $i++) {
            User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => password_hash('password', PASSWORD_DEFAULT)
            ]);
        }
    }
}
```

Run seeder:

```bash
php artisan db:seed
```

### Testing Tips

Basic testing approach:

```php
// tests/UserTest.php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\User;

class UserTest
{
    public function testUserCreation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT)
        ]);

        assert($user->id !== null);
        assert($user->name === 'Test User');

        // Cleanup
        $user->delete();

        echo "✓ User creation test passed\n";
    }
}

$test = new UserTest();
$test->testUserCreation();
```

### Security Best Practices

1. **Always hash passwords**

   ```php
   $hashed = password_hash($password, PASSWORD_DEFAULT);
   ```

2. **Use CSRF protection**

   ```php
   // In forms
   @csrf
   ```

3. **Validate all input**

   ```php
   $this->validate($data, $rules);
   ```

4. **Escape output in views**

   ```php
   {{ $variable }}  // Auto-escaped
   {!! $html !!}    // Not escaped (be careful)
   ```

5. **Use prepared statements** (already built-in)

6. **Set proper permissions**
   ```bash
   chmod 755 storage
   chmod 644 .env
   ```

### Deployment

1. Set environment to production:

   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

2. Set proper web root to `/public`

3. Enable OPcache in php.ini

4. Use HTTPS

5. Set secure permissions

6. Keep `.env` secure and never commit to git

---

For more information, refer to the main documentation in `docs/README.md`
