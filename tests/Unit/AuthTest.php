<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use VtPhp\Auth\AuthManager;
use VtPhp\Auth\GuardInterface;
use VtPhp\Auth\SessionGuard;
use VtPhp\Auth\UserProviderInterface;
use VtPhp\Config\Repository;
use VtPhp\Exceptions\AuthenticationException;
use VtPhp\Foundation\Application;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\Authenticate;
use VtPhp\Session\ArraySessionStore;
use VtPhp\Session\Session;

final class InMemoryUserProvider implements UserProviderInterface
{
    /** @var array<int, object{id: int, email: string, password: string}> */
    public array $users = [];

    public function __construct()
    {
        $this->users[1] = (object) ['id' => 1, 'email' => 'ada@example.com', 'password' => password_hash('secret', PASSWORD_BCRYPT)];
    }

    public function retrieveById(int|string $id): ?object
    {
        return $this->users[(int) $id] ?? null;
    }

    public function retrieveByCredentials(array $credentials): ?object
    {
        foreach ($this->users as $user) {
            if ($user->email === ($credentials['email'] ?? null)) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(object $user, array $credentials): bool
    {
        return password_verify((string) ($credentials['password'] ?? ''), $user->password);
    }
}

final class AuthTest extends TestCase
{
    private Application $app;

    private Session $session;

    private InMemoryUserProvider $provider;

    private const INITIAL_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        $this->app = new Application(sys_get_temp_dir());
        $this->session = new Session(new ArraySessionStore(), self::INITIAL_ID);
        $this->provider = new InMemoryUserProvider();
        $this->app->instance(Session::class, $this->session);
        $this->app->instance(UserProviderInterface::class, $this->provider);
    }

    private function guard(): SessionGuard
    {
        return new SessionGuard($this->app, $this->provider);
    }

    public function test_a_new_guard_is_a_guest(): void
    {
        $guard = $this->guard();

        $this->assertTrue($guard->guest());
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
        $this->assertNull($guard->id());
    }

    public function test_attempt_rejects_bad_credentials(): void
    {
        $guard = $this->guard();

        $this->assertFalse($guard->attempt(['email' => 'ada@example.com', 'password' => 'wrong']));
        $this->assertFalse($guard->attempt(['email' => 'nobody@example.com', 'password' => 'secret']));
        $this->assertTrue($guard->guest());
        $this->assertSame(self::INITIAL_ID, $this->session->id(), 'Failed logins must not rotate the session.');
    }

    public function test_attempt_logs_in_and_rotates_the_session_id(): void
    {
        $guard = $this->guard();

        $this->assertTrue($guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']));
        $this->assertTrue($guard->check());
        $this->assertSame(1, $guard->id());
        $this->assertSame(1, $this->session->get('auth_user_id'));
        $this->assertNotSame(self::INITIAL_ID, $this->session->id(), 'Login must rotate the id (session fixation).');
    }

    public function test_a_later_request_resolves_the_user_from_the_session(): void
    {
        $this->guard()->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

        $nextRequest = $this->guard();

        $this->assertSame(1, $nextRequest->id());
        $this->assertSame('ada@example.com', $nextRequest->user()?->email);
    }

    public function test_a_password_change_ends_existing_sessions(): void
    {
        $this->guard()->attempt(['email' => 'ada@example.com', 'password' => 'secret']);
        $this->assertNotSame('', (string) $this->session->get('auth_password_hash'));
        $this->assertStringNotContainsString((string) $this->provider->users[1]->password, serialize($this->session->all()), 'The raw hash is never stored.');

        $this->provider->users[1]->password = password_hash('changed', PASSWORD_BCRYPT);

        $this->assertTrue($this->guard()->guest());
        $this->assertSame([], $this->session->all(), 'The stale session is invalidated.');
    }

    public function test_a_session_without_a_fingerprint_adopts_the_current_one(): void
    {
        $this->session->put('auth_user_id', 1); // e.g. created before fingerprinting existed

        $this->assertTrue($this->guard()->check());
        $this->assertIsString($this->session->get('auth_password_hash'));

        $this->provider->users[1]->password = password_hash('changed', PASSWORD_BCRYPT);
        $this->assertTrue($this->guard()->guest(), 'Once adopted, the fingerprint is enforced.');
    }

    public function test_a_deleted_user_is_treated_as_a_guest(): void
    {
        $this->session->put('auth_user_id', 99);

        $this->assertTrue($this->guard()->guest());
    }

    public function test_logout_clears_the_session_and_rotates_the_id(): void
    {
        $guard = $this->guard();
        $guard->login($this->provider->users[1]);
        $idAfterLogin = $this->session->id();

        $guard->logout();

        $this->assertTrue($guard->guest());
        $this->assertSame([], $this->session->all());
        $this->assertNotSame($idAfterLogin, $this->session->id());
        $this->assertTrue($this->guard()->guest(), 'The next request must also be a guest.');
    }

    private function manager(string $defaultGuard = 'web'): AuthManager
    {
        return new AuthManager($this->app, new Repository(['auth' => [
            'default' => ['guard' => $defaultGuard],
            'guards' => [
                'web' => ['driver' => 'session', 'provider' => 'users'],
                'weird' => ['driver' => 'carrier-pigeon', 'provider' => 'users'],
            ],
        ]]));
    }

    public function test_manager_resolves_and_memoizes_session_guards(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(SessionGuard::class, $manager->guard('web'));
        $this->assertSame($manager->guard('web'), $manager->guard('web'));
        $this->assertSame($manager->guard('web'), $manager->guard(), 'guard() uses the configured default.');
    }

    public function test_manager_rejects_unsupported_drivers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('carrier-pigeon');

        $this->manager()->guard('weird');
    }

    public function test_authenticate_middleware_blocks_guests_and_passes_users(): void
    {
        $manager = $this->manager();
        $middleware = new Authenticate($manager);
        $request = (new Psr17Factory())->createServerRequest('GET', '/me');
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['ok' => true]);
            }
        };

        try {
            $middleware->process($request, $handler);
            $this->fail('Guests must be rejected.');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        $manager->guard('web')->login($this->provider->users[1]);
        $this->assertSame(200, $middleware->process($request, $handler)->getStatusCode());
    }

    public function test_the_shipped_default_guard_can_be_resolved(): void
    {
        $config = new Repository(['auth' => require __DIR__.'/../../config/auth.php']);

        $this->assertInstanceOf(GuardInterface::class, (new AuthManager($this->app, $config))->guard());
    }

    public function test_an_unsupported_provider_driver_is_rejected(): void
    {
        $manager = new AuthManager($this->app, new Repository(['auth' => [
            'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
            'providers' => ['users' => ['driver' => 'ldap']],
        ]]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('ldap');

        $manager->guard('web');
    }
}
