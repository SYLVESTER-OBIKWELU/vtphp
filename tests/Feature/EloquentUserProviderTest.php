<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Auth\EloquentUserProvider;
use VtPhp\Session\Session;
use VtPhp\Session\SessionManager;
use VtPhp\Tests\Support\FeatureTestCase;

final class EloquentUserProviderTest extends FeatureTestCase
{
    private EloquentUserProvider $provider;

    private User $ada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new EloquentUserProvider(User::class);
        $this->ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => password_hash('secret', PASSWORD_BCRYPT)]);
    }

    public function test_retrieve_by_id(): void
    {
        $this->assertSame($this->ada->id, $this->provider->retrieveById($this->ada->id)?->id);
        $this->assertSame($this->ada->id, $this->provider->retrieveById((string) $this->ada->id)?->id);
        $this->assertNull($this->provider->retrieveById(999));
    }

    public function test_retrieve_by_credentials_ignores_the_password(): void
    {
        $this->assertSame($this->ada->id, $this->provider->retrieveByCredentials(['email' => 'ada@example.com', 'password' => 'anything'])?->id);
        $this->assertNull($this->provider->retrieveByCredentials(['email' => 'nobody@example.com']));
        $this->assertNull($this->provider->retrieveByCredentials(['password' => 'secret']), 'A password alone must not match the first user.');
    }

    public function test_validate_credentials(): void
    {
        $this->assertTrue($this->provider->validateCredentials($this->ada, ['password' => 'secret']));
        $this->assertFalse($this->provider->validateCredentials($this->ada, ['password' => 'wrong']));
        $this->assertFalse($this->provider->validateCredentials($this->ada, []));

        $passwordless = User::query()->create(['name' => 'Grace', 'email' => 'grace@example.com', 'password' => null]);
        $this->assertFalse($this->provider->validateCredentials($passwordless, ['password' => '']));
    }

    public function test_the_default_guard_uses_the_configured_model(): void
    {
        // Outside HTTP there is no StartSession middleware, so start one by hand.
        $this->app->instance(Session::class, $this->app->make(SessionManager::class)->start(null));

        $this->assertTrue(auth()->attempt(['email' => 'ada@example.com', 'password' => 'secret']));
        $this->assertInstanceOf(User::class, auth()->user());
    }
}
