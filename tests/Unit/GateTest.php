<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Auth\Access\Gate;
use VtPhp\Container\Container;
use VtPhp\Exceptions\AuthorizationException;

final class GateUser
{
    public function __construct(public int $id, public bool $admin = false)
    {
    }
}

final class GateNote
{
    public function __construct(public int $ownerId)
    {
    }
}

final class GateNotePolicy
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function update(GateUser $user, GateNote $note): bool
    {
        return $user->id === $note->ownerId;
    }

    public function create(GateUser $user): bool
    {
        return $user->id > 0;
    }

    public function viewAny(?GateUser $user): bool
    {
        return true; // even guests
    }
}

final class AdminFirstPolicy
{
    public function before(?GateUser $user, string $ability): ?bool
    {
        return $user?->admin ? true : null;
    }

    public function delete(GateUser $user, GateNote $note): bool
    {
        return false;
    }
}

final class GateTest extends TestCase
{
    private function gate(?GateUser $user): Gate
    {
        GateNotePolicy::$instances = 0;
        $gate = new Gate(new Container(), static fn (): ?GateUser => $user);
        $gate->policy(GateNote::class, GateNotePolicy::class);

        return $gate;
    }

    public function test_defined_abilities(): void
    {
        $gate = $this->gate(new GateUser(1, admin: true));
        $gate->define('viewReports', fn (GateUser $user): bool => $user->admin);
        $gate->define('tag', fn (GateUser $user, string $tag): bool => $tag === 'php');

        $this->assertTrue($gate->allows('viewReports'));
        $this->assertTrue($gate->allows('tag', 'php'));
        $this->assertTrue($gate->denies('tag', 'go'));
        $this->assertTrue($gate->denies('undefined-ability'), 'Unknown abilities are denied.');
        $this->assertTrue($gate->has('viewReports'));
    }

    public function test_policies_receive_the_user_and_the_model(): void
    {
        $gate = $this->gate(new GateUser(7));

        $this->assertTrue($gate->allows('update', new GateNote(7)));
        $this->assertFalse($gate->allows('update', new GateNote(8)));
        $this->assertTrue($gate->allows('create', GateNote::class), 'A class name picks the policy but is not passed on.');
        $this->assertFalse($gate->allows('delete', new GateNote(7)), 'A missing policy method denies.');
        $this->assertSame(1, GateNotePolicy::$instances, 'The policy is built once.');
    }

    public function test_guests_are_denied_unless_the_rule_accepts_null(): void
    {
        $gate = $this->gate(null);

        $this->assertFalse($gate->allows('update', new GateNote(7)));
        $this->assertTrue($gate->allows('viewAny', GateNote::class), '?GateUser opts guests in.');

        $gate->define('public', fn (?GateUser $user): bool => true);
        $gate->define('members', fn (GateUser $user): bool => true);
        $this->assertTrue($gate->allows('public'));
        $this->assertFalse($gate->allows('members'));
    }

    public function test_authorize_throws_403_when_denied(): void
    {
        $gate = $this->gate(new GateUser(7));
        $gate->authorize('update', new GateNote(7)); // allowed: no exception

        try {
            $gate->authorize('update', new GateNote(8));
            $this->fail('Expected a 403.');
        } catch (AuthorizationException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_before_callbacks_and_policy_before_can_decide_early(): void
    {
        $gate = $this->gate(new GateUser(1, admin: true));
        $gate->before(fn (?GateUser $user, string $ability): ?bool => $ability === 'banned' ? false : null);
        $gate->define('banned', fn (): bool => true);
        $this->assertFalse($gate->allows('banned'), 'A non-null before() result wins.');

        $gate->policy(GateNote::class, AdminFirstPolicy::class);
        $this->assertTrue($gate->allows('delete', new GateNote(99)), "The policy's before() lets admins through.");
        $this->assertFalse($gate->forUser(new GateUser(2))->allows('delete', new GateNote(2)), 'Non-admins fall through to delete().');
    }

    public function test_for_user_checks_someone_else_without_changing_the_gate(): void
    {
        $gate = $this->gate(new GateUser(7));

        $this->assertFalse($gate->forUser(new GateUser(8))->allows('update', new GateNote(7)));
        $this->assertTrue($gate->allows('update', new GateNote(7)));
    }

    public function test_policies_are_found_by_naming_convention(): void
    {
        $gate = new Gate(new Container(), static fn (): ?object => null);
        $ada = new \App\Models\User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $ada->id = 1;
        $grace = new \App\Models\User(['name' => 'Grace', 'email' => 'grace@example.com']);
        $grace->id = 2;

        // App\Models\User → App\Policies\UserPolicy, without registering it.
        $this->assertTrue($gate->forUser($ada)->allows('update', $ada));
        $this->assertFalse($gate->forUser($ada)->allows('delete', $grace));
    }
}
