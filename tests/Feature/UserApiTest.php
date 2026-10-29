<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use VtPhp\Tests\Support\FeatureTestCase;

final class UserApiTest extends FeatureTestCase
{
    public function test_index_starts_empty_and_lists_users_in_id_order(): void
    {
        $this->assertSame(['data' => []], $this->body($this->request('GET', '/api/v1/users')));

        $this->createUser('grace@example.com', name: 'Grace Hopper');
        $this->createUser('ada@example.com', name: 'Ada Lovelace');

        $emails = array_column($this->body($this->request('GET', '/api/v1/users'))['data'], 'email');
        $this->assertSame(['grace@example.com', 'ada@example.com'], $emails);
    }

    public function test_store_creates_a_user_and_hashes_the_password(): void
    {
        $user = $this->createUser('ada@example.com', 'password123');

        $this->assertSame(['id', 'name', 'email', 'email_verified', 'created_at'], array_keys($user));
        $this->assertSame('Ada Lovelace', $user['name']);
        $this->assertFalse($user['email_verified']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $user['created_at']);

        $stored = User::query()->find($user['id']);
        $this->assertNotSame('password123', $stored->password);
        $this->assertTrue(password_verify('password123', (string) $stored->password));
        $this->assertArrayNotHasKey('password', $stored->toArray(), 'Password must be $hidden.');
    }

    public function test_store_validates_input(): void
    {
        $response = $this->request('POST', '/api/v1/users', ['email' => 'not-an-email', 'password' => 'short']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['name', 'email', 'password'], array_keys($this->body($response)['error']['errors']));
    }

    public function test_store_requires_a_matching_password_confirmation(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password123', 'password_confirmation' => 'different',
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['The password confirmation does not match.'], $this->body($response)['error']['errors']['password']);
    }

    public function test_store_rejects_a_duplicate_email(): void
    {
        $this->createUser('ada@example.com');

        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Ada 2', 'email' => 'ada@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['The email has already been taken.'], $this->body($response)['error']['errors']['email']);
    }

    public function test_store_ignores_fields_that_are_not_validated(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'email_verified_at' => '2020-01-01 00:00:00', 'id' => 500,
        ]);

        $user = $this->body($response)['data'];
        $this->assertNotSame(500, $user['id']);
        $this->assertFalse($user['email_verified'], 'Unvalidated fields must not be mass-assigned.');
    }

    public function test_put_and_patch_both_update(): void
    {
        $user = $this->createUser();

        $this->assertSame('Via Put', $this->body($this->request('PUT', "/api/v1/users/{$user['id']}", ['name' => 'Via Put']))['data']['name']);
        $this->assertSame('Via Patch', $this->body($this->request('PATCH', "/api/v1/users/{$user['id']}", ['name' => 'Via Patch']))['data']['name']);
    }

    public function test_show_returns_a_user_or_404(): void
    {
        $user = $this->createUser();

        $this->assertSame($user, $this->body($this->request('GET', "/api/v1/users/{$user['id']}"))['data']);
        $this->assertErrorCode($this->request('GET', '/api/v1/users/999'), 404, 'NOT_FOUND');
    }

    public function test_update_changes_only_the_given_fields(): void
    {
        $user = $this->createUser();

        $updated = $this->body($this->request('PATCH', "/api/v1/users/{$user['id']}", ['name' => 'Countess Ada']))['data'];

        $this->assertSame('Countess Ada', $updated['name']);
        $this->assertSame('ada@example.com', $updated['email']);
    }

    public function test_update_validates_rejects_conflicts_and_unknown_users(): void
    {
        $ada = $this->createUser('ada@example.com');
        $this->createUser('grace@example.com');

        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'bad']), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'grace@example.com']), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['name' => null]), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', '/api/v1/users/999', ['name' => 'x']), 404, 'NOT_FOUND');

        $same = $this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'ada@example.com']);
        $this->assertSame(200, $same->getStatusCode(), 'Re-submitting your own email is not a conflict.');
    }

    public function test_destroy_deletes_the_user(): void
    {
        $user = $this->createUser();

        $response = $this->request('DELETE', "/api/v1/users/{$user['id']}");
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());

        $this->assertErrorCode($this->request('GET', "/api/v1/users/{$user['id']}"), 404, 'NOT_FOUND');
        $this->assertErrorCode($this->request('DELETE', "/api/v1/users/{$user['id']}"), 404, 'NOT_FOUND');
    }

    #[Group('defect')]
    public function test_user_management_requires_authentication(): void
    {
        // docs/authentication.md says these routes are guarded; they are not.
        // Anyone can list, edit or delete any account.
        $user = $this->createUser();
        $this->cookies = [];

        $this->assertSame(401, $this->request('GET', '/api/v1/users')->getStatusCode());
        $this->assertSame(401, $this->request('DELETE', "/api/v1/users/{$user['id']}")->getStatusCode());
    }

    public function test_a_non_numeric_id_is_a_404_not_a_500(): void
    {
        $this->assertErrorCode($this->request('GET', '/api/v1/users/abc'), 404, 'NOT_FOUND');
        $this->assertErrorCode($this->request('PATCH', '/api/v1/users/abc', ['name' => 'x']), 404, 'NOT_FOUND');
    }

    #[Group('defect')]
    public function test_emails_are_unique_regardless_of_case(): void
    {
        $this->createUser('ada@example.com');

        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Ada', 'email' => 'ADA@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }
}
