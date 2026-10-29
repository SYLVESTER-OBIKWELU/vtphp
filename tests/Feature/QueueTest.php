<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use VtPhp\Database\EloquentManager;
use VtPhp\Queue\Job;
use VtPhp\Queue\QueueManager;
use VtPhp\Queue\Worker;
use VtPhp\Tests\Support\FeatureTestCase;

final class RecordingJob extends Job
{
    /** @var list<string> */
    public static array $ran = [];

    /** @var list<string> */
    public static array $failures = [];

    public function __construct(public string $label, public int $failTimes = 0)
    {
    }

    public function handle(LoggerInterface $logger): void
    {
        static::$ran[] = $this->label;
        $logger->info("ran {$this->label}");

        if (count(array_keys(static::$ran, $this->label, true)) <= $this->failTimes) {
            throw new \RuntimeException("{$this->label} broke");
        }
    }

    public function failed(\Throwable $exception): void
    {
        static::$failures[] = $exception->getMessage();
    }
}

final class RetryingJob extends Job
{
    public ?int $tries = 3;

    /** @var list<int> */
    public int|array $backoff = [0, 0];

    public function handle(): void
    {
        RecordingJob::$ran[] = 'retrying';

        throw new \RuntimeException('always broken');
    }

    public function failed(\Throwable $exception): void
    {
        RecordingJob::$failures[] = 'retrying gave up';
    }
}

final class RenameUserJob extends Job
{
    /**
     * @param Collection<int, User> $others
     */
    public function __construct(private readonly User $user, public Collection $others)
    {
    }

    public function handle(): void
    {
        RecordingJob::$ran[] = $this->user->name.' + '.implode(',', $this->others->pluck('email')->all());
    }
}

final class QueueTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordingJob::$ran = [];
        RecordingJob::$failures = [];
    }

    private function useDatabaseQueue(): void
    {
        $this->app->make('config')->set('queue.default', 'database');
    }

    private function work(int $tries = 1): ?array
    {
        return $this->app->make(Worker::class)->runNextJob('database', ['default'], $tries);
    }

    private function logged(string $level, string $message): bool
    {
        return in_array($message, array_column($this->logs->atLevel($level), 'message'), true);
    }

    private function db(): \Illuminate\Database\Connection
    {
        return $this->app->make(EloquentManager::class)->connection();
    }

    public function test_the_sync_connection_runs_jobs_immediately(): void
    {
        RecordingJob::dispatch('now');
        dispatch(new RecordingJob('helper'));
        RecordingJob::dispatchSync('direct');

        $this->assertSame(['now', 'helper', 'direct'], RecordingJob::$ran);
        $this->assertTrue($this->logged('info', 'ran now'), 'handle() parameters come from the container.');
    }

    public function test_a_failing_sync_job_calls_failed_and_throws(): void
    {
        try {
            RecordingJob::dispatch('bad', failTimes: 1);
            $this->fail('Expected the exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('bad broke', $e->getMessage());
        }

        $this->assertSame(['bad broke'], RecordingJob::$failures);
    }

    public function test_the_database_queue_stores_jobs_until_a_worker_runs_them(): void
    {
        $this->useDatabaseQueue();

        RecordingJob::dispatch('first');
        RecordingJob::dispatch('second');

        $this->assertSame([], RecordingJob::$ran, 'Nothing runs until a worker does.');
        $this->assertSame(2, $this->app->make(QueueManager::class)->connection()->size());

        $result = $this->work();
        $this->assertSame(RecordingJob::class, $result['job']);
        $this->assertSame('done', $result['status']);
        $this->work();

        $this->assertSame(['first', 'second'], RecordingJob::$ran, 'Jobs run in order.');
        $this->assertNull($this->work(), 'Nothing left.');
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    public function test_delays_and_named_queues(): void
    {
        $this->useDatabaseQueue();
        $worker = $this->app->make(Worker::class);

        RecordingJob::dispatch('later')->delay(3600);
        RecordingJob::dispatch('low');
        RecordingJob::dispatch('urgent')->onQueue('high');

        $worker->runNextJob('database', ['high', 'default']);
        $worker->runNextJob('database', ['high', 'default']);
        $this->assertNull($worker->runNextJob('database', ['high', 'default']), 'The delayed job is not due yet.');
        $this->assertSame(['urgent', 'low'], RecordingJob::$ran, 'Queues are worked in priority order.');

        $this->db()->table('jobs')->update(['available_at' => time() - 1]);
        $worker->runNextJob('database', ['default']);
        $this->assertSame(['urgent', 'low', 'later'], RecordingJob::$ran);
    }

    public function test_failed_jobs_are_retried_then_recorded(): void
    {
        $this->useDatabaseQueue();

        RetryingJob::dispatch();

        $this->assertSame('released', $this->work()['status']);
        $this->assertSame('released', $this->work()['status']);
        $this->assertSame('failed', $this->work()['status'], 'The job sets $tries = 3.');
        $this->assertSame(['retrying', 'retrying', 'retrying'], RecordingJob::$ran);
        $this->assertSame(['retrying gave up'], RecordingJob::$failures, 'failed() runs once, at the end.');

        $this->assertSame(0, $this->db()->table('jobs')->count());
        $failed = $this->db()->table('failed_jobs')->first();
        $this->assertSame('database', $failed->connection);
        $this->assertStringContainsString('always broken', $failed->exception);
        $this->assertTrue($this->logged('error', 'Queued job '.RetryingJob::class.' failed: always broken'));
    }

    public function test_backoff_delays_the_retry_and_worker_tries_apply_to_jobs_without_their_own(): void
    {
        $this->useDatabaseQueue();

        $job = new RecordingJob('flaky', failTimes: 1);
        $job->backoff = 120;
        dispatch($job);

        $this->assertSame('released', $this->work(tries: 2)['status']);
        $this->assertNull($this->work(tries: 2), 'Waiting out the backoff.');

        $this->db()->table('jobs')->update(['available_at' => time()]);
        $this->assertSame('done', $this->work(tries: 2)['status']);
        $this->assertSame(['flaky', 'flaky'], RecordingJob::$ran);
    }

    public function test_a_tampered_payload_is_never_unserialized(): void
    {
        $this->useDatabaseQueue();
        RecordingJob::dispatch('original');

        $row = $this->db()->table('jobs')->first();
        $payload = json_decode($row->payload, true);
        $payload['job'] = base64_encode(serialize(new RecordingJob('evil')));
        $this->db()->table('jobs')->update(['payload' => json_encode($payload)]);

        $this->assertSame('failed', $this->work(tries: 5)['status'], 'Not retried: it can never succeed.');
        $this->assertSame([], RecordingJob::$ran);
        $this->assertStringContainsString('invalid signature', $this->db()->table('failed_jobs')->value('exception'));
    }

    public function test_models_are_stored_by_id_and_loaded_fresh(): void
    {
        $ada = User::query()->find($this->createUser('ada@example.com')['id']);
        $grace = User::query()->find($this->createUser('grace@example.com', 'password123', 'Grace')['id']);
        $alan = User::query()->find($this->createUser('alan@example.com', 'password123', 'Alan')['id']);
        $this->useDatabaseQueue();

        RenameUserJob::dispatch($ada, new Collection([$alan, $grace]));

        $payload = (string) $this->db()->table('jobs')->value('payload');
        $this->assertStringNotContainsString('password', (string) base64_decode(json_decode($payload, true)['job']), 'Only the id is stored.');

        $ada->update(['name' => 'Countess Ada']);
        $this->work();
        $this->assertSame(['Countess Ada + alan@example.com,grace@example.com'], RecordingJob::$ran, 'Fresh data, original order.');

        RenameUserJob::dispatch($ada, new Collection());
        $ada->delete();
        $this->assertSame('failed', $this->work(tries: 3)['status'], 'A deleted model fails the job without retrying.');
    }

    public function test_queued_mail_is_sent_by_the_worker(): void
    {
        $this->createUser('ada@example.com');

        $response = $this->request('POST', '/api/v1/password/forgot', ['email' => 'ada@example.com'], configure: function ($app): void {
            $app->make('config')->set('queue.default', 'database');
        });
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->logs->mails(), 'Not sent during the request.');
        $this->assertStringContainsString('ResetPasswordEmail (mail)', (string) $this->db()->table('jobs')->value('payload'));

        $this->app->make('config')->set('queue.default', 'database');
        $this->assertSame('done', $this->work()['status']);
        $this->assertSame('Reset your password', $this->logs->mails()[0]['subject'] ?? null);
    }

    public function test_queue_commands(): void
    {
        $this->useDatabaseQueue();
        RetryingJob::dispatch();
        RecordingJob::dispatch('fine');

        [$code, $output] = $this->forge(['command' => 'queue:work', '--stop-when-empty' => true, '--sleep' => '0']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('WILL RETRY', $output);
        $this->assertStringContainsString('DONE', $output);
        $this->assertStringContainsString('FAILED', $output, 'No backoff, so all three tries ran in this one session.');
        $this->assertSame(0, $this->forge(['command' => 'queue:work', '--once' => true])[0], '--once on an empty queue just stops.');

        [, $output] = $this->forge(['command' => 'queue:failed']);
        $this->assertStringContainsString(RetryingJob::class, $output);
        $this->assertStringContainsString('always broken', $output);

        $id = (string) $this->db()->table('failed_jobs')->value('id');
        [$code] = $this->forge(['command' => 'queue:retry', 'id' => [$id]]);
        $this->assertSame(0, $code);
        $this->assertSame(0, $this->db()->table('failed_jobs')->count());
        $this->assertSame(0, (int) $this->db()->table('jobs')->value('attempts'), 'Retried jobs start counting again.');

        $this->assertSame(1, $this->forge(['command' => 'queue:retry', 'id' => ['999']])[0]);
        $this->assertSame(1, $this->forge(['command' => 'queue:forget', 'id' => '999'])[0]);

        [$code, $output] = $this->forge(['command' => 'queue:clear']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 1 waiting job', $output);

        $this->db()->table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'e', 'failed_at' => date('Y-m-d H:i:s')]);
        [, $output] = $this->forge(['command' => 'queue:flush']);
        $this->assertStringContainsString('Deleted 1 failed job', $output);
    }

    public function test_queue_work_explains_the_sync_connection(): void
    {
        [$code, $output] = $this->forge(['command' => 'queue:work', '--once' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('nothing for a worker to do', $output);
    }

    public function test_an_unknown_driver_is_reported(): void
    {
        $this->app->make('config')->set('queue.connections.redis', ['driver' => 'redis']);

        $this->expectExceptionMessageIsOrContains('Use "sync" or "database"');
        $this->app->make(QueueManager::class)->connection('redis');
    }
}
