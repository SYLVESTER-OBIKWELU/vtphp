<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use Psr\Http\Message\ResponseInterface;
use VtPhp\Auth\PersonalAccessToken;
use VtPhp\Foundation\Application;
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\RequireTokenAbility;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;

final class TokenAuthTest extends FeatureTestCase
{
    private User $ada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password123']);
    }

    private function routes(): \Closure
    {
        return function (Application $app): void {
            $router = $app->make(Router::class);
            $router->get('/whoami', fn () => ['id' => auth()->id(), 'guard' => app(\VtPhp\Auth\AuthManager::class)->defaultGuard()], null, [Authenticate::using('web', 'api')]);
            $router->get('/token-only', fn () => ['ok' => true], null, [Authenticate::using('api')]);
            $router->post('/notes', fn () => ['saved' => true], null, [Authenticate::using('web', 'api'), RequireTokenAbility::using('notes:write')]);
            $router->post('/revoke', function (): array {
                auth()->logout();

                return ['revoked' => true];
            }, null, [Authenticate::using('api')]);
        };
    }

    private function bearer(string $method, string $uri, ?string $token, ?array $json = null): ResponseInterface
    {
        $this->cookies = []; // a pure API client: no session cookie

        return $this->request($method, $uri, $json, $token !== null ? ['Authorization' => "Bearer {$token}"] : [], $this->routes());
    }

    public function test_a_created_token_is_shown_once_and_stored_hashed(): void
    {
        $new = $this->ada->createToken('iPhone');

        $this->assertMatchesRegularExpression('/^\d+\|[a-f0-9]{40}$/', $new->plainTextToken);
        [, $secret] = explode('|', $new->plainTextToken, 2);
        $stored = PersonalAccessToken::query()->find($new->accessToken->id);
        $this->assertSame(hash('sha256', $secret), $stored->token);
        $this->assertSame(['*'], $stored->abilities);
        $this->assertArrayNotHasKey('token', $stored->toArray(), 'The hash is hidden from JSON.');
    }

    public function test_a_valid_bearer_token_authenticates_and_becomes_the_default_guard(): void
    {
        $token = $this->ada->createToken('iPhone')->plainTextToken;

        $response = $this->bearer('GET', '/whoami', $token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => $this->ada->id, 'guard' => 'api'], $this->body($response));
        $this->assertNotNull(PersonalAccessToken::query()->find((int) $token)?->last_used_at, 'last_used_at is recorded.');
    }

    public function test_missing_malformed_wrong_or_unknown_tokens_are_rejected(): void
    {
        $token = $this->ada->createToken('iPhone')->plainTextToken;
        [$id] = explode('|', $token, 2);

        foreach ([null, 'garbage', "{$id}|".str_repeat('0', 40), '999|'.str_repeat('a', 40), "{$id}|"] as $bad) {
            $this->assertErrorCode($this->bearer('GET', '/token-only', $bad), 401, 'UNAUTHENTICATED');
        }
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $expired = $this->ada->createToken('old', ['*'], new \DateTimeImmutable('-1 minute'))->plainTextToken;
        $this->assertSame(401, $this->bearer('GET', '/token-only', $expired)->getStatusCode());

        $this->setEnvironment(['AUTH_TOKEN_EXPIRATION' => '60']);
        $aged = $this->ada->createToken('aged');
        PersonalAccessToken::query()->whereKey($aged->accessToken->id)->update(['created_at' => now()->modify('-2 hours')]);
        $this->assertSame(401, $this->bearer('GET', '/token-only', $aged->plainTextToken)->getStatusCode(), 'The app-wide maximum age applies too.');
    }

    public function test_a_deleted_user_or_deleted_token_stops_working(): void
    {
        $token = $this->ada->createToken('iPhone')->plainTextToken;
        $this->assertSame(200, $this->bearer('GET', '/token-only', $token)->getStatusCode());

        $this->assertSame(['revoked' => true], $this->body($this->bearer('POST', '/revoke', $token)));
        $this->assertSame(401, $this->bearer('GET', '/token-only', $token)->getStatusCode(), 'logout() revokes the token.');

        $other = $this->ada->createToken('laptop')->plainTextToken;
        $this->ada->delete();
        $this->assertSame(401, $this->bearer('GET', '/token-only', $other)->getStatusCode());
    }

    public function test_token_abilities_are_enforced_but_session_logins_are_not_limited(): void
    {
        $readOnly = $this->ada->createToken('reader', ['notes:read'])->plainTextToken;
        $writer = $this->ada->createToken('writer', ['notes:read', 'notes:write'])->plainTextToken;

        $this->assertErrorCode($this->bearer('POST', '/notes', $readOnly, []), 403, 'FORBIDDEN');
        $this->assertSame(200, $this->bearer('POST', '/notes', $writer, [])->getStatusCode());

        $this->login('ada@example.com', 'password123');
        $this->assertSame(200, $this->request('POST', '/notes', [], configure: $this->routes())->getStatusCode(), 'Cookie logins may do anything.');
    }

    public function test_session_only_routes_ignore_tokens(): void
    {
        $token = $this->ada->createToken('iPhone')->plainTextToken;

        $this->assertSame(401, $this->request('GET', '/session-only', null, ['Authorization' => "Bearer {$token}"], function (Application $app): void {
            $app->make(Router::class)->get('/session-only', fn () => ['ok' => true], null, [Authenticate::class]);
        })->getStatusCode());
    }

    public function test_token_writes_need_no_csrf_header_because_no_cookie_is_sent(): void
    {
        $token = $this->ada->createToken('server')->plainTextToken;
        $this->cookies = [];

        $response = $this->request('POST', '/notes', null, ['Authorization' => "Bearer {$token}", 'Content-Type' => 'text/plain'], $this->routes());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_tokens_prune_deletes_only_expired_tokens(): void
    {
        $this->ada->createToken('expired', ['*'], new \DateTimeImmutable('-1 day'));
        $this->ada->createToken('valid', ['*'], new \DateTimeImmutable('+1 day'));
        $this->ada->createToken('forever');

        [$code, $output] = $this->forge(['command' => 'tokens:prune']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 1 expired token(s).', $output);
        $this->assertSame(['valid', 'forever'], PersonalAccessToken::query()->orderBy('id')->pluck('name')->all());
    }
}
