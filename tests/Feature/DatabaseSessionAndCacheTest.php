<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use VtPhp\Cache\CacheManager;
use VtPhp\Database\Schema;
use VtPhp\Session\SessionManager;
use VtPhp\Tests\Support\FeatureTestCase;

final class DatabaseSessionAndCacheTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnvironment(['SESSION_DRIVER' => 'database', 'CACHE_DRIVER' => 'database']);
        $this->freshApp();
    }

    /**
     * @return list<object>
     */
    private function sessionRows(): array
    {
        return Capsule::table('sessions')->get()->all();
    }

    public function test_the_migrations_create_the_sessions_and_cache_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('sessions', ['id', 'user_id', 'payload', 'last_activity']));
        $this->assertTrue(Schema::hasTable('cache'));
    }

    public function test_sessions_are_stored_in_the_database_and_survive_across_requests(): void
    {
        $user = $this->createUser('ada@example.com', 'password123');
        $this->login('ada@example.com', 'password123');

        $row = Capsule::table('sessions')->where('id', $this->cookies['vtphp_session'])->first();
        $this->assertNotNull($row, 'The session row is written.');
        $this->assertSame($user['id'], (int) $row->user_id, 'The user id is copied for easy lookup/revocation.');
        $this->assertStringNotContainsString('password123', base64_decode((string) $row->payload));

        $this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode());
        $this->assertSame([], glob($this->tmp.'/sessions/*.sess') ?: [], 'No session files with the database driver.');
    }

    public function test_logout_removes_the_users_session_row(): void
    {
        $user = $this->createUser('ada@example.com', 'password123');
        $this->login('ada@example.com', 'password123');

        $this->request('POST', '/api/v1/logout');

        $this->assertSame(0, Capsule::table('sessions')->where('user_id', $user['id'])->count());
        $this->assertSame(401, $this->request('GET', '/api/v1/me')->getStatusCode());
    }

    public function test_an_expired_session_row_is_discarded(): void
    {
        $user = $this->createUser('ada@example.com', 'password123');
        $this->login('ada@example.com', 'password123');
        $id = $this->cookies['vtphp_session'];

        Capsule::table('sessions')->where('id', $id)->update(['last_activity' => time() - 3 * 3600]);

        $this->assertSame(401, $this->request('GET', '/api/v1/me')->getStatusCode());

        // The request then saves a fresh, empty session under that cookie:
        // nothing from the expired one survives.
        $row = Capsule::table('sessions')->where('id', $id)->first();
        $this->assertNull($row?->user_id);
        $this->assertSame(0, Capsule::table('sessions')->where('user_id', $user['id'])->count());
        $this->assertGreaterThan(time() - 60, (int) $row?->last_activity);
    }

    public function test_a_tampered_payload_never_instantiates_objects(): void
    {
        $manager = $this->app->make(SessionManager::class);
        $id = str_repeat('c', 40);
        Capsule::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'payload' => base64_encode(serialize(['evil' => new \ArrayObject([1])])),
            'last_activity' => time(),
        ]);

        $data = $manager->store()->read($id);

        $this->assertNotInstanceOf(\ArrayObject::class, $data['evil'] ?? null);
        $this->assertSame([], $manager->store()->read(str_repeat('d', 40)), 'Unknown ids read as empty.');

        Capsule::table('sessions')->where('id', $id)->update(['payload' => '%%%not-base64%%%']);
        $this->assertSame([], $manager->store()->read($id), 'Corrupt payloads read as empty.');
    }

    public function test_gc_deletes_only_expired_rows_and_session_gc_runs_it(): void
    {
        Capsule::table('sessions')->insert([
            ['id' => str_repeat('a', 40), 'user_id' => null, 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
            ['id' => str_repeat('b', 40), 'user_id' => null, 'payload' => base64_encode(serialize([])), 'last_activity' => time() - 3 * 3600],
        ]);

        [$code, $output] = $this->forge(['command' => 'session:gc']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('[database]', $output);
        $this->assertSame([str_repeat('a', 40)], Capsule::table('sessions')->pluck('id')->all());
    }

    public function test_the_lottery_triggers_garbage_collection(): void
    {
        Capsule::table('sessions')->insert(['id' => str_repeat('b', 40), 'user_id' => null, 'payload' => '', 'last_activity' => time() - 3 * 3600]);
        $config = $this->app->make('config');

        $config->set('session.lottery', [0, 100]);
        $this->assertFalse($this->app->make(SessionManager::class)->collectGarbageByLottery());
        $this->assertSame(1, Capsule::table('sessions')->count());

        $config->set('session.lottery', [1, 1]);
        $this->assertTrue($this->app->make(SessionManager::class)->collectGarbageByLottery());
        $this->assertSame(0, Capsule::table('sessions')->count());
    }

    public function test_the_database_cache_store_works_and_persists_across_requests(): void
    {
        cache()->put('user:1', ['name' => 'Ada'], 60);
        cache()->put('greeting', 'hello');

        $this->freshApp(); // a later request
        $this->assertSame(['name' => 'Ada'], cache()->get('user:1'));
        $this->assertTrue(cache()->has('greeting'));
        $this->assertSame(2, Capsule::table('cache')->count(), 'Items live in the cache table.');

        $calls = 0;
        $this->assertSame('computed', cache()->remember('report', 60, function () use (&$calls): string {
            ++$calls;

            return 'computed';
        }));
        cache()->remember('report', 60, fn () => 'again');
        $this->assertSame(1, $calls);

        cache()->forget('greeting');
        $this->assertFalse(cache()->has('greeting'));
        cache()->put('fleeting', 'x', 0);
        $this->assertFalse(cache()->has('fleeting'));
    }

    public function test_cache_clear_flushes_the_default_or_a_named_store(): void
    {
        cache()->put('a', 1);
        $this->app->make(CacheManager::class)->store('file')->set('b', 2);

        [$code, $output] = $this->forge(['command' => 'cache:clear']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Cache store [database] cleared.', $output);
        $this->assertFalse(cache()->has('a'));

        [$code] = $this->forge(['command' => 'cache:clear', 'store' => 'file']);
        $this->assertSame(0, $code);
        $this->assertFalse($this->app->make(CacheManager::class)->store('file')->has('b'));
    }
}
