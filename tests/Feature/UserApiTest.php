<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Tests\Support\FeatureTestCase;

final class UserApiTest extends FeatureTestCase
{
    /**
     * Create a user and log in as them.
     *
     * @return array<string, mixed>
     */
    private function signedInUser(string $email = 'ada@example.com', string $name = 'Ada Lovelace'): array
    {
        $user = $this->createUser($email, 'password123', $name);
        $this->assertSame(200, $this->login($email, 'password123')->getStatusCode());

        return $user;
    }

    public function test_index_lists_users_in_id_order(): void
    {
        $this->createUser('grace@example.com', name: 'Grace Hopper');
        $this->signedInUser('ada@example.com');

        $emails = array_column($this->body($this->request('GET', '/api/v1/users'))['data'], 'email');
        $this->assertSame(['grace@example.com', 'ada@example.com'], $emails);
    }

    public function test_sign_up_is_public_and_hashes_the_password(): void
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

    public function test_passwords_over_72_bytes_are_rejected_not_silently_truncated(): void
    {
        $long = str_repeat('a', 72).'SECRET-TAIL';
        $response = $this->request('POST', '/api/v1/users', ['name' => 'Long', 'email' => 'long@example.com', 'password' => $long, 'password_confirmation' => $long]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertStringContainsString('72 bytes', $this->body($response)['error']['errors']['password'][0]);

        $emoji = str_repeat('🔒', 19); // 19 characters, 76 bytes
        $this->assertSame(422, $this->request('POST', '/api/v1/users', ['name' => 'E', 'email' => 'e@example.com', 'password' => $emoji, 'password_confirmation' => $emoji])->getStatusCode());

        $max = str_repeat('a', 72);
        $this->assertSame(201, $this->request('POST', '/api/v1/users', ['name' => 'Max', 'email' => 'max@example.com', 'password' => $max, 'password_confirmation' => $max])->getStatusCode());
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

    public function test_user_management_requires_authentication(): void
    {
        $user = $this->createUser();
        $this->cookies = [];

        $this->assertErrorCode($this->request('GET', '/api/v1/users'), 401, 'UNAUTHENTICATED');
        $this->assertErrorCode($this->request('GET', "/api/v1/users/{$user['id']}"), 401, 'UNAUTHENTICATED');
        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$user['id']}", ['name' => 'x']), 401, 'UNAUTHENTICATED');
        $this->assertErrorCode($this->request('DELETE', "/api/v1/users/{$user['id']}"), 401, 'UNAUTHENTICATED');
        $this->assertNotNull(User::query()->find($user['id']));
    }

    public function test_users_cannot_edit_or_delete_someone_else(): void
    {
        $grace = $this->createUser('grace@example.com', name: 'Grace Hopper');
        $this->signedInUser('ada@example.com');

        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$grace['id']}", ['name' => 'Hacked']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->request('DELETE', "/api/v1/users/{$grace['id']}"), 403, 'FORBIDDEN');
        $this->assertSame('Grace Hopper', User::query()->find($grace['id'])?->name);

        $this->assertSame(200, $this->request('GET', "/api/v1/users/{$grace['id']}")->getStatusCode(), 'Viewing other users is allowed.');
    }

    public function test_put_and_patch_both_update(): void
    {
        $user = $this->signedInUser();

        $this->assertSame('Via Put', $this->body($this->request('PUT', "/api/v1/users/{$user['id']}", ['name' => 'Via Put']))['data']['name']);
        $this->assertSame('Via Patch', $this->body($this->request('PATCH', "/api/v1/users/{$user['id']}", ['name' => 'Via Patch']))['data']['name']);
    }

    public function test_show_returns_a_user_or_404(): void
    {
        $user = $this->signedInUser();

        $this->assertSame($user, $this->body($this->request('GET', "/api/v1/users/{$user['id']}"))['data']);
        $this->assertErrorCode($this->request('GET', '/api/v1/users/999'), 404, 'NOT_FOUND');
    }

    public function test_update_changes_only_the_given_fields(): void
    {
        $user = $this->signedInUser();

        $updated = $this->body($this->request('PATCH', "/api/v1/users/{$user['id']}", ['name' => 'Countess Ada']))['data'];

        $this->assertSame('Countess Ada', $updated['name']);
        $this->assertSame('ada@example.com', $updated['email']);
    }

    public function test_update_validates_rejects_conflicts_and_unknown_users(): void
    {
        $this->createUser('grace@example.com');
        $ada = $this->signedInUser('ada@example.com');

        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'bad']), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'grace@example.com']), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', "/api/v1/users/{$ada['id']}", ['name' => null]), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->request('PATCH', '/api/v1/users/999', ['name' => 'x']), 404, 'NOT_FOUND');

        $same = $this->request('PATCH', "/api/v1/users/{$ada['id']}", ['email' => 'ada@example.com']);
        $this->assertSame(200, $same->getStatusCode(), 'Re-submitting your own email is not a conflict.');
    }

    public function test_destroy_deletes_your_own_account_and_ends_the_session(): void
    {
        $user = $this->signedInUser();

        $response = $this->request('DELETE', "/api/v1/users/{$user['id']}");
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertNull(User::query()->find($user['id']));

        $this->assertErrorCode($this->request('GET', '/api/v1/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_a_non_numeric_id_is_a_404_not_a_500(): void
    {
        $this->signedInUser();

        $this->assertErrorCode($this->request('GET', '/api/v1/users/abc'), 404, 'NOT_FOUND');
        $this->assertErrorCode($this->request('PATCH', '/api/v1/users/abc', ['name' => 'x']), 404, 'NOT_FOUND');
    }

    public function test_emails_are_unique_regardless_of_case(): void
    {
        $this->createUser('ada@example.com');

        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Ada', 'email' => 'ADA@Example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['The email has already been taken.'], $this->body($response)['error']['errors']['email']);
    }

    public function test_emails_are_stored_lowercased_and_match_any_case(): void
    {
        $user = $this->createUser('  Grace.Hopper@Example.COM ');
        $this->assertSame('grace.hopper@example.com', $user['email']);

        $this->assertSame(200, $this->login('GRACE.HOPPER@example.com', 'password123')->getStatusCode());

        $this->request('POST', '/api/v1/password/forgot', ['email' => 'Grace.Hopper@EXAMPLE.com']);
        $this->assertSame(['grace.hopper@example.com'], $this->logs->mails()[0]['to'] ?? null);

        $this->assertSame('ada@example.com', User::query()->create(['name' => 'Ada', 'email' => 'ADA@Example.com', 'password' => null])->email, 'Direct model writes are normalized too.');
    }
}
