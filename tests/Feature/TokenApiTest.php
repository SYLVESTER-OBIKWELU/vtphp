<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Psr\Http\Message\ResponseInterface;
use VtPhp\Auth\PersonalAccessToken;
use VtPhp\Tests\Support\FeatureTestCase;

final class TokenApiTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('ada@example.com', 'password123');
        $this->createUser('grace@example.com', 'password123', 'Grace Hopper');
        $this->cookies = [];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function issue(string $email = 'ada@example.com', string $password = 'password123', array $extra = []): ResponseInterface
    {
        $this->cookies = [];

        return $this->request('POST', '/api/v1/tokens', ['email' => $email, 'password' => $password, 'device_name' => 'iPhone'] + $extra);
    }

    private function tokenFor(string $email, array $extra = []): string
    {
        return $this->body($this->issue($email, 'password123', $extra))['data']['token'];
    }

    private function asToken(string $token, string $method, string $uri, ?array $json = null): ResponseInterface
    {
        $this->cookies = []; // a pure API client never sends the session cookie

        return $this->request($method, $uri, $json, ['Authorization' => "Bearer {$token}"]);
    }

    public function test_issuing_a_token_with_email_and_password(): void
    {
        $response = $this->issue();

        $this->assertSame(201, $response->getStatusCode());
        $data = $this->body($response)['data'];
        $this->assertMatchesRegularExpression('/^\d+\|[a-f0-9]{40}$/', $data['token']);
        $this->assertSame('Bearer', $data['type']);
        $this->assertSame(['*'], $data['abilities']);
    }

    public function test_bad_credentials_and_bad_input_are_rejected(): void
    {
        foreach ([['ada@example.com', 'wrong'], ['nobody@example.com', 'password123']] as [$email, $password]) {
            $response = $this->issue($email, $password);
            $this->assertSame(401, $response->getStatusCode());
            $this->assertSame('These credentials do not match our records.', $this->body($response)['message']);
        }

        $invalid = $this->request('POST', '/api/v1/tokens', ['email' => 'ada@example.com']);
        $this->assertErrorCode($invalid, 422, 'VALIDATION_ERROR');
        $this->assertSame(['password', 'device_name'], array_keys($this->body($invalid)['error']['errors']));
    }

    public function test_a_token_works_on_the_protected_routes(): void
    {
        $token = $this->tokenFor('ada@example.com');

        $me = $this->asToken($token, 'GET', '/api/v1/me');
        $this->assertSame(200, $me->getStatusCode());
        $this->assertSame('ada@example.com', $this->body($me)['data']['email']);

        $id = $this->body($me)['data']['id'];
        $this->assertSame('Countess Ada', $this->body($this->asToken($token, 'PATCH', "/api/v1/users/{$id}", ['name' => 'Countess Ada']))['data']['name']);
        $this->assertSame(200, $this->asToken($token, 'GET', '/api/v1/users')->getStatusCode());
        $this->assertSame(401, $this->asToken('1|wrong', 'GET', '/api/v1/me')->getStatusCode());
    }

    public function test_listing_tokens_never_reveals_secrets(): void
    {
        $token = $this->tokenFor('ada@example.com');
        $this->tokenFor('ada@example.com', ['abilities' => ['notes:read']]);
        $this->tokenFor('grace@example.com');

        $list = $this->body($this->asToken($token, 'GET', '/api/v1/tokens'))['data'];

        $this->assertCount(2, $list, "Only Ada's own tokens.");
        $this->assertSame(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'], array_keys($list[0]));
        $this->assertSame(['notes:read'], $list[1]['abilities']);
        $this->assertStringNotContainsString(explode('|', $token)[1], json_encode($list, JSON_THROW_ON_ERROR));
    }

    public function test_revoking_your_own_token_but_not_someone_elses(): void
    {
        $ada = $this->tokenFor('ada@example.com');
        $adaSecond = $this->tokenFor('ada@example.com');
        $grace = $this->tokenFor('grace@example.com');

        $this->assertErrorCode($this->asToken($ada, 'DELETE', '/api/v1/tokens/'.(int) $grace), 404, 'NOT_FOUND');
        $this->assertSame(200, $this->asToken($grace, 'GET', '/api/v1/me')->getStatusCode(), "Grace's token still works.");

        $this->assertSame(204, $this->asToken($ada, 'DELETE', '/api/v1/tokens/'.(int) $adaSecond)->getStatusCode());
        $this->assertSame(401, $this->asToken($adaSecond, 'GET', '/api/v1/me')->getStatusCode());
        $this->assertSame(200, $this->asToken($ada, 'GET', '/api/v1/me')->getStatusCode());
    }

    public function test_logging_out_with_a_token_revokes_it(): void
    {
        $token = $this->tokenFor('ada@example.com');

        $this->assertSame(200, $this->asToken($token, 'POST', '/api/v1/logout')->getStatusCode());
        $this->assertSame(401, $this->asToken($token, 'GET', '/api/v1/me')->getStatusCode());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_session_logins_still_work_alongside_tokens(): void
    {
        $this->login('ada@example.com', 'password123');

        $this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode());
        $this->assertSame(200, $this->request('GET', '/api/v1/tokens')->getStatusCode(), 'A browser can manage its tokens too.');
    }
}
