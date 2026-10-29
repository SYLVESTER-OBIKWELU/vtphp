<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Tests\Support\FeatureTestCase;

final class AuthFlowTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('ada@example.com', 'password123');
        $this->cookies = [];
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        foreach ([['ada@example.com', 'wrong-password'], ['nobody@example.com', 'password123']] as [$email, $password]) {
            $response = $this->login($email, $password);

            $this->assertSame(401, $response->getStatusCode());
            $this->assertSame('These credentials do not match our records.', $this->body($response)['message']);
        }
    }

    public function test_login_validates_input(): void
    {
        $response = $this->request('POST', '/api/v1/login', ['email' => 'nope']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['email', 'password'], array_keys($this->body($response)['error']['errors']));
    }

    public function test_login_returns_the_user_and_rotates_the_session(): void
    {
        $this->request('GET', '/health');
        $guestSession = $this->cookies['vtphp_session'];

        $response = $this->login();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ada@example.com', $this->body($response)['data']['email']);
        $this->assertNotSame($guestSession, $this->cookies['vtphp_session'], 'Session id must rotate on login (fixation).');
    }

    public function test_me_requires_a_session_and_returns_the_logged_in_user(): void
    {
        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');

        $this->login();
        $me = $this->request('GET', '/api/v1/me');

        $this->assertSame(200, $me->getStatusCode());
        $this->assertSame('ada@example.com', $this->body($me)['data']['email']);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->login();
        $loggedInSession = $this->cookies['vtphp_session'];

        $this->assertSame(['message' => 'Logged out.'], $this->body($this->request('POST', '/api/v1/logout')));
        $this->assertNotSame($loggedInSession, $this->cookies['vtphp_session']);
        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');

        // Replaying the old cookie must not resurrect the session.
        $this->cookies['vtphp_session'] = $loggedInSession;
        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_a_forged_session_cookie_is_ignored(): void
    {
        $this->cookies['vtphp_session'] = str_repeat('0', 40);
        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');

        $this->cookies['vtphp_session'] = '../../../etc/passwd';
        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');

        $this->request('GET', '/health');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $this->cookies['vtphp_session'], 'A malformed id is replaced, never used as a file name.');
    }

    public function test_deleting_the_user_ends_their_session(): void
    {
        $this->login();
        $id = $this->body($this->request('GET', '/api/v1/me'))['data']['id'];

        $this->request('DELETE', "/api/v1/users/{$id}");

        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_session_files_hold_no_secrets(): void
    {
        $this->login();

        $file = $this->tmp.'/sessions/'.$this->cookies['vtphp_session'].'.sess';
        $this->assertFileExists($file);

        $contents = (string) file_get_contents($file);
        $data = unserialize($contents, ['allowed_classes' => false]);
        $this->assertSame(['auth_user_id', 'auth_password_hash'], array_keys($data));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $data['auth_password_hash'], 'Only a digest of the hash.');

        $passwordHash = (string) User::query()->where('email', 'ada@example.com')->value('password');
        $this->assertStringNotContainsString($passwordHash, $contents);
        $this->assertStringNotContainsString('password123', $contents);
    }
}
