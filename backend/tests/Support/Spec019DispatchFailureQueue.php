<?php

namespace Tests\Support;

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Connectors\SyncConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/**
 * US3 T041: real SyncQueue subclass that fails ONLY inside the low-level
 * post-commit execution path for translation-generation jobs.
 *
 * SyncQueue::push() defers afterCommit jobs into db.transactions callbacks
 * and only calls executeJob() after the surrounding transaction commits, so
 * overriding executeJob() (instead of faking the queue or throwing during
 * dispatch registration) proves the source write already committed before
 * the generation push fails. Queue::fake() must NOT be used here: the fake
 * records pushes immediately and bypasses the real after-commit mechanics.
 *
 * The marker is a harmless static string, never a credential or live key.
 */
class Spec019DispatchFailureQueue extends SyncQueue
{
    public const MARKER = 'spec019_dispatch_probe';

    public const CONNECTION = 'spec019_fail';

    /** @var bool Fail translation pushes when true; pure spy when false. */
    public static bool $spec019FailTranslationPush = true;

    /**
     * @var array<int, array{job: string, level: int, failed: bool}>
     */
    public static array $spec019Attempts = [];

    /**
     * @param  mixed  $job
     * @param  mixed  $data
     */
    protected function executeJob($job, $data = '', $queue = null)
    {
        $isTranslation = $job instanceof GenerateTourTranslationJob
            || (is_string($job) && $job === GenerateTourTranslationJob::class);

        if ($isTranslation) {
            // Reached only after the surrounding transaction committed:
            // SyncQueue::push() routes afterCommit jobs through the
            // db.transactions callback, which fires post-commit at level 0.
            self::$spec019Attempts[] = [
                'job' => GenerateTourTranslationJob::class,
                'level' => DB::transactionLevel(),
                'failed' => self::$spec019FailTranslationPush,
            ];

            if (self::$spec019FailTranslationPush) {
                throw new RuntimeException(self::MARKER);
            }

            return 0;
        }

        if (is_object($job)) {
            self::$spec019Attempts[] = [
                'job' => get_class($job),
                'level' => DB::transactionLevel(),
                'failed' => false,
            ];
        }

        return parent::executeJob($job, $data, $queue);
    }

    /**
     * Swap in a fresh real queue manager carrying the stock sync connector
     * plus this failing/spy connection, and route default dispatches to it.
     */
    public static function bind(bool $failTranslationPush = true): void
    {
        self::$spec019FailTranslationPush = $failTranslationPush;
        self::$spec019Attempts = [];

        $manager = new QueueManager(app());
        Queue::swap($manager);
        $manager->addConnector('sync', fn () => new SyncConnector);
        $manager->addConnector(self::CONNECTION, fn () => new Spec019FailConnector);

        config()->set('queue.connections.' . self::CONNECTION, ['driver' => self::CONNECTION]);
        config()->set('queue.default', self::CONNECTION);
    }

    public static function clear(): void
    {
        self::$spec019Attempts = [];
    }

    public static function restore(): void
    {
        self::$spec019FailTranslationPush = true;
        self::$spec019Attempts = [];
        config()->set('queue.default', 'sync');
    }

    /**
     * @return array<int, array{job: string, level: int, failed: bool}>
     */
    public static function translationAttempts(): array
    {
        return array_values(array_filter(
            self::$spec019Attempts,
            static fn (array $attempt): bool => $attempt['job'] === GenerateTourTranslationJob::class
        ));
    }

    /**
     * @return array<int, array{job: string, level: int, failed: bool}>
     */
    public static function attemptsForJob(string $jobClass): array
    {
        return array_values(array_filter(
            self::$spec019Attempts,
            static fn (array $attempt): bool => $attempt['job'] === $jobClass
        ));
    }
}

/**
 * Connector resolving the Spec019 failing/spy queue. Prefixed to avoid any
 * global class-name collision with other suites.
 */
class Spec019FailConnector implements ConnectorInterface
{
    public function connect(array $config): QueueContract
    {
        return new Spec019DispatchFailureQueue;
    }
}
