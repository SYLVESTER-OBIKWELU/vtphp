# Error Handler Documentation

## Overview

The VTPHP Framework includes a production-ready error handler with beautiful, copyable error pages.

## Features

### 🎨 Beautiful Error Display

- Dark-themed UI optimized for readability
- Syntax-highlighted code snippets
- Color-coded stack traces
- Line-by-line error location

### 📋 Copy Functionality

Three copy buttons for different error formats:

1. **Code Snippet** - The exact code where error occurred with context
2. **Stack Trace** - Formatted call stack with file/line info
3. **Raw Trace** - Complete PHP stack trace

### ⌨️ Keyboard Shortcuts

- `Ctrl+C` (Windows/Linux) or `Cmd+C` (Mac) - Copy raw trace to clipboard

### 🔒 Environment-Aware

- **Development Mode** (`APP_DEBUG=true`): Shows detailed error page
- **Production Mode** (`APP_DEBUG=false`): Shows generic 500 error

## Configuration

### `.env` Settings

```properties
APP_ENV=development    # or production
APP_DEBUG=true         # false in production
APP_TIMEZONE=UTC       # Your timezone
```

### Registration

The error handler is automatically registered in `public_html/index.php`:

```php
require_once __DIR__ . '/../core/ErrorHandler.php';
Core\ErrorHandler::register();
```

## Error Page Sections

### 1. Error Header

- Exception type (e.g., `ErrorException`, `PDOException`)
- Error message
- File path and line number

### 2. Code Snippet

- Shows 10 lines before and after the error
- Highlighted error line with red background
- Line numbers for easy reference
- Syntax-preserved code display

### 3. Stack Trace

- Formatted call stack
- Each frame shows:
  - Stack position number
  - File path
  - Line number
  - Class/Function name
- Hover effects for better readability

### 4. Raw Trace

- Complete PHP stack trace
- Scrollable container
- Monospace font for readability
- Easy to copy entire trace

## Usage Examples

### Testing the Error Handler

#### Test Route (add to `routes/web.php`):

```php
$router->get('/test-error', function() {
    throw new \Exception('Testing the beautiful error page!');
});
```

Visit `http://localhost:5500/test-error` to see the error page.

### Custom Error Messages

```php
// Throw with custom message
throw new \Exception('User not found in database');

// HTTP errors with abort helper
abort(404, 'Page not found');
abort(403, 'Unauthorized access');
abort(500, 'Server error occurred');

// Conditional aborts
abort_if(!$user, 404, 'User not found');
abort_unless($user->isAdmin(), 403, 'Admin access required');
```

### Catching Errors in Controllers

```php
class UserController extends Controller
{
    public function show($request)
    {
        try {
            $user = User::find($request->params('id'));

            if (!$user) {
                abort(404, 'User not found');
            }

            return $this->view('users.show', compact('user'));

        } catch (\Exception $e) {
            // Error will be caught by global handler
            // and display beautiful error page
            throw $e;
        }
    }
}
```

## Production Error Page

In production (`APP_DEBUG=false`), users see:

```
┌─────────────────────┐
│        500          │
│                     │
│ Oops! Something     │
│  went wrong         │
│                     │
│ We're sorry, but    │
│ something went      │
│ wrong on our end.   │
│ Please try again    │
│ later.              │
└─────────────────────┘
```

## Error Types Handled

The error handler catches:

### PHP Errors

- `E_ERROR` - Fatal runtime errors
- `E_WARNING` - Runtime warnings
- `E_PARSE` - Compile-time parse errors
- `E_NOTICE` - Runtime notices
- `E_CORE_ERROR` - Fatal errors during PHP startup
- `E_COMPILE_ERROR` - Fatal compile-time errors

### Exceptions

- All uncaught exceptions
- Custom exceptions
- PDO exceptions (database)
- ErrorException (converted from errors)

### Shutdown Errors

- Fatal errors that cause script termination
- Parse errors
- Out of memory errors

## Logging Errors

Errors are also logged for later analysis:

```php
// In your code
logger()->error('Something went wrong', [
    'user_id' => $userId,
    'action' => 'purchase',
    'error' => $e->getMessage()
]);

// Or use helper
error_log_custom('Payment failed', [
    'amount' => $amount,
    'reason' => $e->getMessage()
]);
```

## Best Practices

### 1. Development

```properties
APP_ENV=development
APP_DEBUG=true
```

- See detailed errors
- Debug efficiently
- Copy error details

### 2. Production

```properties
APP_ENV=production
APP_DEBUG=false
```

- Hide sensitive information
- Show user-friendly messages
- Log errors for review

### 3. Error Messages

```php
// ✅ Good: Descriptive messages
throw new \Exception('Failed to connect to payment gateway: ' . $error);

// ❌ Bad: Generic messages
throw new \Exception('Error');
```

### 4. Error Context

```php
// ✅ Good: Include context
logger()->error('Order creation failed', [
    'order_id' => $orderId,
    'user_id' => $userId,
    'items' => $items,
    'error' => $e->getMessage()
]);

// ❌ Bad: No context
logger()->error($e->getMessage());
```

## Customization

### Custom Error Page

Create `resources/views/errors/500.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
</head>
<body>
    <h1>Custom Error Page</h1>
    <p>{{ $message ?? 'An error occurred' }}</p>
</body>
</html>
```

### Custom Error Handler

```php
// In public_html/index.php
Core\ErrorHandler::register();

// Add custom logic
set_exception_handler(function($exception) {
    // Your custom handling

    // Then use default handler
    Core\ErrorHandler::handleException($exception);
});
```

## Troubleshooting

### Error Page Not Showing

1. Check `.env`:

   ```properties
   APP_DEBUG=true
   ```

2. Verify error handler is registered:

   ```php
   // In public_html/index.php
   require_once __DIR__ . '/../core/ErrorHandler.php';
   Core\ErrorHandler::register();
   ```

3. Check file permissions:
   ```bash
   chmod -R 755 storage/
   ```

### Copy Button Not Working

1. Ensure JavaScript is enabled in browser
2. Check browser console for errors
3. Try keyboard shortcut `Ctrl+C` as alternative

### Errors in Production

1. Check logs:

   ```bash
   tail -f storage/logs/laravel.log
   ```

2. Enable detailed logging:

   ```properties
   LOG_LEVEL=debug
   ```

3. Monitor error patterns

## Security Considerations

### Don't Expose in Production

- Database credentials
- API keys
- Internal file paths
- Stack traces

### Always Set

```properties
APP_DEBUG=false  # In production
APP_ENV=production
```

### Sanitize Error Messages

```php
// ✅ Good: Generic user message
throw new \Exception('Unable to process payment');

// ❌ Bad: Exposes internals
throw new \Exception('MySQL error: Access denied for user root@localhost');
```

## Tips

1. **Copy Errors Fast**: Use `Ctrl+C` keyboard shortcut
2. **Share Errors**: Click copy button and paste in issue tracker
3. **Debug Efficiently**: Line numbers help locate issues quickly
4. **Test Regularly**: Create test routes to verify error handling
5. **Log Everything**: Use logger() for important events

## Support

For issues or questions:

- Check framework documentation
- Review error logs
- Test in development mode first
- Verify `.env` configuration

---

Your error pages are now beautiful, functional, and developer-friendly! 🎉
