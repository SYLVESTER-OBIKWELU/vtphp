<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Config\Repository;
use VtPhp\Session\ArraySessionStore;
use VtPhp\Session\FileSessionStore;
use VtPhp\Session\Session;
use VtPhp\Session\SessionManager;
use VtPhp\Tests\Support\TempDirectory;

final class InjectionCanary
{
    public static bool $woke = false;

    public function __wakeup(): void
    {
        self::$woke = true;
    }
}

final class SessionTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-session');
        InjectionCanary::$woke = false;
    }

    protected function tearDown(): void
    {
        TempDirectory::delete($this->tmp);
    }

    public function test_session_attribute_bag(): void
    {
        $session = new Session(new ArraySessionStore(), str_repeat('a', 40));
        $session->put('cart', [1, 2]);
        $session->put('flag', null);

        $this->assertSame([1, 2], $session->get('cart'));
        $this->assertSame('fallback', $session->get('missing', 'fallback'));
        $this->assertTrue($session->has('flag'), 'has() must see keys holding null.');
        $session->forget('cart');
        $this->assertFalse($session->has('cart'));
        $this->assertSame(['flag' => null], $session->all());
    }

    public function test_save_persists_attributes_to_the_store(): void
    {
        $store = new ArraySessionStore();
        $session = new Session($store, $id = str_repeat('b', 40));
        $session->put('user', 5);
        $session->save();

        $this->assertSame(['user' => 5], $store->read($id));
    }

    public function test_regenerate_rotates_the_id_keeps_data_and_destroys_the_old_record(): void
    {
        $store = new ArraySessionStore();
        $store->write($oldId = str_repeat('c', 40), ['user' => 5]);
        $session = new Session($store, $oldId, ['user' => 5]);

        $newId = $session->regenerate();

        $this->assertNotSame($oldId, $newId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $newId);
        $this->assertSame($newId, $session->id());
        $this->assertSame(5, $session->get('user'));
        $this->assertSame([], $store->read($oldId));
    }

    public function test_invalidate_clears_data_and_rotates_the_id(): void
    {
        $session = new Session(new ArraySessionStore(), $id = str_repeat('d', 40), ['user' => 5]);
        $session->invalidate();

        $this->assertSame([], $session->all());
        $this->assertNotSame($id, $session->id());
    }

    public function test_file_store_round_trips_and_destroys(): void
    {
        $store = new FileSessionStore($this->tmp.'/nested/sessions', 120);
        $id = str_repeat('e', 40);

        $this->assertDirectoryExists($this->tmp.'/nested/sessions', 'The store creates its directory.');
        $this->assertSame([], $store->read($id));

        $store->write($id, ['user' => 5, 'roles' => ['admin']]);
        $this->assertSame(['user' => 5, 'roles' => ['admin']], $store->read($id));

        $store->destroy($id);
        $this->assertSame([], $store->read($id));
        $store->destroy($id); // Destroying twice must not error.
    }

    public function test_file_store_expires_stale_sessions(): void
    {
        $store = new FileSessionStore($this->tmp, 1);
        $id = str_repeat('f', 40);
        $store->write($id, ['user' => 5]);
        touch($this->tmp.DIRECTORY_SEPARATOR.$id.'.sess', time() - 120);
        clearstatcache();

        $this->assertSame([], $store->read($id));
        $this->assertFileDoesNotExist($this->tmp.DIRECTORY_SEPARATOR.$id.'.sess');
    }

    public function test_file_store_gc_deletes_only_expired_files(): void
    {
        $store = new FileSessionStore($this->tmp, 120);
        $store->write($fresh = str_repeat('1', 40), ['a' => 1]);
        $store->write($stale = str_repeat('2', 40), ['a' => 1]);
        touch($this->tmp.DIRECTORY_SEPARATOR.$stale.'.sess', time() - 3 * 3600);
        clearstatcache();

        $store->gc(7200);

        $this->assertFileExists($this->tmp.DIRECTORY_SEPARATOR.$fresh.'.sess');
        $this->assertFileDoesNotExist($this->tmp.DIRECTORY_SEPARATOR.$stale.'.sess');
    }

    public function test_file_store_ignores_corrupted_files(): void
    {
        $store = new FileSessionStore($this->tmp, 120);
        file_put_contents($this->tmp.DIRECTORY_SEPARATOR.str_repeat('1', 40).'.sess', 'garbage{{{');
        file_put_contents($this->tmp.DIRECTORY_SEPARATOR.str_repeat('2', 40).'.sess', '');

        $this->assertSame([], $store->read(str_repeat('1', 40)));
        $this->assertSame([], $store->read(str_repeat('2', 40)));
    }

    public function test_file_store_never_instantiates_objects_from_session_files(): void
    {
        $store = new FileSessionStore($this->tmp, 120);
        $id = str_repeat('9', 40);
        file_put_contents($this->tmp.DIRECTORY_SEPARATOR.$id.'.sess', serialize(['payload' => new InjectionCanary()]));

        $data = $store->read($id);

        $this->assertFalse(InjectionCanary::$woke, '__wakeup() ran: PHP object injection is possible.');
        $this->assertNotInstanceOf(InjectionCanary::class, $data['payload']);
    }

    private function manager(string $driver = 'file'): SessionManager
    {
        return new SessionManager(new Repository(['session' => [
            'driver' => $driver,
            'lifetime' => 120,
            'files' => $this->tmp,
        ]]));
    }

    public function test_manager_issues_a_fresh_id_for_missing_or_malformed_ids(): void
    {
        $manager = $this->manager();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $manager->start(null)->id());

        foreach (['', 'short', '../../etc/passwd', str_repeat('Z', 40)] as $bad) {
            $id = $manager->start($bad)->id();
            $this->assertNotSame($bad, $id);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $id);
        }
    }

    public function test_manager_resumes_an_existing_session(): void
    {
        $manager = $this->manager();
        $first = $manager->start(null);
        $first->put('user', 7);
        $first->save();

        $resumed = $this->manager()->start($first->id());

        $this->assertSame($first->id(), $resumed->id());
        $this->assertSame(7, $resumed->get('user'));
    }

    public function test_manager_supports_the_array_driver_and_rejects_unknown_drivers(): void
    {
        $this->assertSame([], $this->manager('array')->start(null)->all());

        $this->expectException(\InvalidArgumentException::class);
        $this->manager('redis')->start(null);
    }
}
