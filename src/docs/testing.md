# Testing

Automated tests are small programs that check your app still works. You run
them with one command, and they tell you within seconds if a change broke
something. VtPhp comes with about 400 tests and helpers that make writing
your own easy.

## The quick version

Run every test:

```bash
composer test
```

When everything passes, the last line looks like this (your numbers will
differ):

```text
OK (400 tests, 1439 assertions)
```

Write a test for your own endpoint:

```php
// tests/Feature/HealthTest.php
namespace VtPhp\Tests\Feature;

use VtPhp\Tests\Support\FeatureTestCase;

final class HealthTest extends FeatureTestCase
{
    public function test_the_health_check_says_ok(): void
    {
        $response = $this->request('GET', '/health');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $this->body($response)['status']);
    }
}
```

Run just that file:

```bash
php vendor/bin/phpunit tests/Feature/HealthTest.php
```

## How tests are organised

Tests use PHPUnit, the standard testing tool for PHP. There are two kinds:

- **Unit tests** (`tests/Unit/`) check one class on its own, such as the
  validator. They're tiny and very fast.
- **Feature tests** (`tests/Feature/`) start the whole app and send it real
  requests, just like a browser or mobile app would. They check that
  everything works together.

Each test is a public method whose name starts with `test_`. Inside it you
make **assertions**: statements such as "this should equal 200". If an
assertion is wrong, the test fails and PHPUnit shows you what it expected and
what it got.

## Running tests

```bash
composer test                                            # everything
php vendor/bin/phpunit --testsuite Unit                  # only unit tests
php vendor/bin/phpunit --testsuite Feature               # only feature tests
php vendor/bin/phpunit tests/Feature/UserApiTest.php     # one file
php vendor/bin/phpunit --filter test_store_validates     # tests whose name matches
```

The test setup is strict on purpose: a PHP warning, notice or deprecation
counts as a failure, so small problems can't hide.

## Writing feature tests

Extend `VtPhp\Tests\Support\FeatureTestCase`. Every test then gets its own
fresh, empty database in a temporary folder, so tests never affect each
other or your real data. All migrations have already run.

### Sending requests

`$this->request()` sends a request and returns the response:

```php
$response = $this->request('GET', '/api/v1/me');
$response = $this->request('POST', '/api/v1/users', ['name' => 'Ada', 'email' => 'ada@example.com']);
```

The third argument is the JSON body. A few useful things to know:

- Each request starts a brand-new copy of the app, exactly like a real
  server does for each visitor.
- Cookies from responses are remembered in `$this->cookies` and sent with the
  next request, like a browser. Set `$this->cookies = [];` to "close the
  browser".
- Write requests (`POST`, `PUT`, `PATCH`, `DELETE`) are always sent as JSON,
  as a real frontend would.
- The full signature is
  `request($method, $uri, $json = null, $headers = [], $configure = null, $server = [])`.
  Use `$headers` for extra headers, and `$server` to change values such as
  the visitor's IP address (`['REMOTE_ADDR' => '198.51.100.7']`).

### Reading responses

```php
$this->assertSame(201, $response->getStatusCode());
$data = $this->body($response);            // the JSON body as a PHP array
$this->assertSame('Ada', $data['data']['name']);
```

For error responses, `assertErrorCode()` checks the status and the error
`code` together:

```php
$this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
```

### Users and logging in

```php
$user = $this->createUser('ada@example.com', 'password123'); // signs up, returns the user's data
$this->login('ada@example.com', 'password123');                // logs in; the cookie is kept

$this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode());
```

Both have defaults (`ada@example.com` / `password123` / `Ada Lovelace`), so
`$this->createUser(); $this->login();` works too.

### A complete example

```php
// tests/Feature/SignUpTest.php
namespace VtPhp\Tests\Feature;

use VtPhp\Tests\Support\FeatureTestCase;

final class SignUpTest extends FeatureTestCase
{
    public function test_a_new_user_can_sign_up_and_log_in(): void
    {
        $user = $this->createUser('grace@example.com', 'secret-password');

        $this->assertSame('grace@example.com', $user['email']);
        $this->assertSame(200, $this->login('grace@example.com', 'secret-password')->getStatusCode());
        $this->assertSame('grace@example.com', $this->body($this->request('GET', '/api/v1/me'))['data']['email']);
    }

    public function test_sign_up_rejects_a_bad_email(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Grace',
            'email' => 'not-an-email',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('email', $this->body($response)['error']['errors']);
    }
}
```

## Going further

### Checking emails

In tests, emails are never really sent. They're recorded instead, and
`$this->logs->mails()` returns them, each with `to`, `subject` and `body`:

```php
$this->createUser('ada@example.com');
$this->request('POST', '/api/v1/password/forgot', ['email' => 'ada@example.com']);

$mail = $this->logs->mails()[0];
$this->assertSame(['ada@example.com'], $mail['to']);
$this->assertSame('Reset your password', $mail['subject']);
```

`$this->logs->atLevel('error')` returns everything logged as an error, which
is handy for checking that failures are recorded.

### Changing settings for one test

`setEnvironment()` changes `.env` values for the current test only. They're
reset automatically afterwards:

```php
$this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000']);
```

The new values apply from the next `$this->request()`. The defaults for
tests are: file sessions, an `array` cache, emails recorded rather than
sent, and debug mode off. Because the `array` cache forgets everything
between requests, rate limits never trigger in tests. To test them, add
`'RATE_LIMIT_STORE' => 'database'`.

### Adding a route just for a test

The fifth argument of `request()` runs code on the app before the request,
which is useful for testing framework behaviour without touching your
routes:

```php
use VtPhp\Foundation\Application;
use VtPhp\Routing\Router;

$response = $this->request('GET', '/boom', null, [], function (Application $app): void {
    $app->make(Router::class)->get('/boom', fn () => throw new \RuntimeException('oops'));
});

$this->assertErrorCode($response, 500, 'INTERNAL_SERVER_ERROR');
```

### Testing `forge` commands

`$this->forge()` runs a command and returns its exit code and output:

```php
[$code, $output] = $this->forge(['command' => 'route:list']);

$this->assertSame(0, $code);
$this->assertStringContainsString('users.index', $output);
```

Pass arguments and options by name, e.g.
`['command' => 'migrate', '--force' => true]`.

### Unit tests

For a class that doesn't need the whole app, extend PHPUnit's own `TestCase`:

```php
// tests/Unit/PasswordRuleTest.php
namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Validation\Validator;

final class PasswordRuleTest extends TestCase
{
    public function test_passwords_must_be_at_least_eight_characters(): void
    {
        $this->assertTrue(Validator::make(['password' => 'long-enough'], ['password' => 'min:8'])->passes());
        $this->assertFalse(Validator::make(['password' => 'short'], ['password' => 'min:8'])->passes());
    }
}
```

### Keeping your settings out of tests

Tests never read your personal `.env` file. `phpunit.xml.dist` sets
`APP_ENV=testing`, which makes the app load `.env.testing` instead. Put
test-only settings there if you need them.

### Recording a known bug first

Found a bug you can't fix right now? Write a test that shows it, and tag it
so it doesn't block everyone else:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('defect')]
public function test_the_known_bug_is_fixed(): void
{
    // ...a test that currently fails...
}
```

```bash
php vendor/bin/phpunit --exclude-group defect   # everything else must still pass
php vendor/bin/phpunit --group defect           # the known bugs
```

Remove the tag once the bug is fixed.

### Checking code quality

```bash
composer stan    # PHPStan: finds type errors and likely bugs without running the code
composer fmt     # PHP-CS-Fixer: rewrites files to the project's code style
```

`composer fmt` changes files. To only see what it would change, run
`php vendor/bin/php-cs-fixer fix --dry-run --diff`.

## Troubleshooting

- **A test fails only when run with others.** Something leaked between tests.
  Use `setEnvironment()` rather than `putenv()`, and never rely on test
  order. `php vendor/bin/phpunit --order-by=random` helps find these.
- **"Failed asserting that 429 is identical to 200".** You hit a rate limit
  in a test that sets `RATE_LIMIT_STORE=database`. Send some requests from
  another IP address with `server: ['REMOTE_ADDR' => '...']`.
- **A test fails with a PHP warning.** Warnings count as failures here. Fix
  the warning; the message shows the file and line.
- **On Windows, PHP-CS-Fixer reports `line_ending` on every file.** Git
  converts line endings when checking files out. That's harmless and isn't a
  style problem.

## Next steps

- [Your first API](your-first-api.md): build something worth testing.
- [Validation](validation.md): the rules your tests will exercise.
- [Deploying](deploying.md): run the tests before every release.
