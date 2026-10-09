<?php

use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Actions\RemoveFromIndexAction;
use App\Models\Tour;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Tests\Support\Spec019CommittedFixtures;
use Tests\Support\Spec019DispatchFailureQueue;
use Tests\TestCase;

class Spec019ProjectionFailureQueue extends SyncQueue
{
    public static array $attempts = [];

    public static bool $failScout = false;

    protected function executeJob($job, $data = '', $queue = null)
    {
        self::$attempts[] = ['class' => get_class($job), 'level' => DB::transactionLevel()];
        $fails = self::$failScout
            ? $job instanceof MakeSearchable || $job instanceof RemoveFromSearch
            : $job instanceof IndexTourAction || $job instanceof RemoveFromIndexAction;
        if ($fails) {
            throw new RuntimeException('spec019_projection_dispatch_probe');
        }

        return 0;
    }
}

function spec019BindProjectionFailureQueue(bool $failScout = false): void
{
    Spec019ProjectionFailureQueue::$attempts = [];
    Spec019ProjectionFailureQueue::$failScout = $failScout;
    $manager = new QueueManager(app());
    Queue::swap($manager);
    $manager->addConnector('spec019_projection_failure', fn () => new Spec019ProjectionFailureConnector);
    config()->set('queue.connections.spec019_projection_failure', ['driver' => 'spec019_projection_failure']);
    config()->set('queue.default', 'spec019_projection_failure');
}

class Spec019ProjectionFailureConnector implements ConnectorInterface
{
    public function connect(array $config): QueueContract
    {
        return new Spec019ProjectionFailureQueue;
    }
}

class Spec019FailingSynchronousScoutEngine extends CollectionEngine
{
    public function update($models)
    {
        throw new RuntimeException('spec019_synchronous_engine_probe');
    }

    public function delete($models)
    {
        throw new RuntimeException('spec019_synchronous_engine_probe');
    }
}

beforeEach(function () {
    $this->originalDefault = config('database.default');
    $this->originalTransactionsManager = app('db.transactions');
    $this->ownedRows = ['tables' => []];
    $this->guardPassed = false;
    config()->set('services.gemini.api_key', '');
    Queue::fake();
    Spec019CommittedFixtures::boot();
    app()->instance('db.transactions', new DatabaseTransactionsManager);
    Spec019CommittedFixtures::assertPrerequisites();
    $this->guardPassed = true;
    config()->set('database.default', Spec019CommittedFixtures::CONNECTION);
    $this->owner = Spec019CommittedFixtures::createOwner('outer-commit', $this->ownedRows);
});

afterEach(function () {
    try {
        if ($this->guardPassed) {
            $connection = DB::connection(Spec019CommittedFixtures::CONNECTION);
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            Spec019CommittedFixtures::deleteOnly($this->ownedRows);
        }
    } finally {
        config()->set('database.default', $this->originalDefault);
        app()->instance('db.transactions', $this->originalTransactionsManager);
        Spec019DispatchFailureQueue::restore();
        DB::purge(Spec019CommittedFixtures::CONNECTION);
    }
});

function spec019CreateOuterCommitTour(TestCase $case): Tour
{
    $response = Tour::withoutEvents(fn () => $case->postJson('/api/partner/tours', [
        'title' => 'Spec019 outer transaction fixture ' . bin2hex(random_bytes(5)),
        'description' => str_repeat('Explore the vineyards and local wines. ', 4),
        'category' => $case->owner['categorySlug'],
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], ['Authorization' => 'Bearer ' . $case->owner['token']]));
    $response->assertCreated();
    $id = (int) $response->json('data.id');
    Spec019CommittedFixtures::trackTour($case->ownedRows, $id);

    return Tour::findOrFail($id);
}

it('contains real generation failures when the enclosing transaction commits', function (string $operation) {
    $tour = $operation === 'update' ? spec019CreateOuterCommitTour($this) : null;
    Spec019DispatchFailureQueue::bind(true);
    DB::beginTransaction();

    if ($operation === 'create') {
        $tour = spec019CreateOuterCommitTour($this);
    } else {
        Tour::withoutEvents(fn () => $this->putJson('/api/partner/tours/' . $tour->id, [
            'title' => 'Accepted English revision in enclosing transaction',
        ], ['Authorization' => 'Bearer ' . $this->owner['token']])->assertOk());
    }

    expect(Spec019DispatchFailureQueue::translationAttempts())->toBeEmpty();
    DB::commit();
    expect(DB::transactionLevel())->toBe(0);
    expect(Spec019DispatchFailureQueue::translationAttempts())->toHaveCount(2);
    foreach (Spec019DispatchFailureQueue::translationAttempts() as $attempt) {
        expect($attempt['level'])->toBe(0);
        expect($attempt['failed'])->toBeTrue();
    }
    expect($tour->fresh()->translations()->where('locale', 'en')->exists())->toBeTrue();
    expect($tour->fresh()->translationStates()->count())->toBe(2);
})->with(['create', 'update']);

it('contains a real search projection dispatch failure after commit', function (string $operation) {
    $tour = spec019CreateOuterCommitTour($this);
    if ($operation === 'published-save') {
        Tour::withoutEvents(fn () => $tour->update(['status' => 'published', 'price_amount' => 5000]));
        addAvailabilityRule($tour);
        $tour = $tour->fresh();
        expect($tour->shouldBeSearchable())->toBeTrue();
    }
    spec019BindProjectionFailureQueue();

    DB::beginTransaction();
    if ($operation !== 'delete') {
        $tour->update(['duration_minutes' => 240]);
    } else {
        $tour->delete();
    }
    expect(Spec019ProjectionFailureQueue::$attempts)->toBeEmpty();
    DB::commit();
    expect(Spec019ProjectionFailureQueue::$attempts)->toHaveCount(1);
    expect(Spec019ProjectionFailureQueue::$attempts[0])->toBe([
        'class' => $operation === 'published-save' ? IndexTourAction::class : RemoveFromIndexAction::class,
        'level' => 0,
    ]);
    expect(Tour::whereKey($tour->id)->exists())->toBe($operation !== 'delete');
})->with(['save', 'published-save', 'delete']);

it('keeps a committed public source write successful when Scouts queue push fails', function (string $operation) {
    $tour = spec019CreateOuterCommitTour($this);
    Tour::withoutEvents(fn () => $tour->update(['status' => 'published', 'price_amount' => 5000]));
    addAvailabilityRule($tour);
    $tour = $tour->fresh();
    expect($tour->shouldBeSearchable())->toBeTrue();
    spec019BindProjectionFailureQueue(true);
    config()->set('scout.queue', ['connection' => 'spec019_projection_failure', 'queue' => 'scout']);

    DB::beginTransaction();
    if ($operation === 'save') {
        $tour->update(['duration_minutes' => 240]);
    } else {
        $tour->delete();
    }
    expect(Spec019ProjectionFailureQueue::$attempts)->toBeEmpty();
    DB::commit();
    if ($operation === 'save') {
        expect($tour->fresh()->duration_minutes)->toBe(240);
    } else {
        expect(Tour::whereKey($tour->id)->exists())->toBeFalse();
    }
    expect(DB::transactionLevel())->toBe(0);
    expect(array_column(Spec019ProjectionFailureQueue::$attempts, 'class'))
        ->toContain($operation === 'save' ? MakeSearchable::class : RemoveFromSearch::class);
    foreach (Spec019ProjectionFailureQueue::$attempts as $attempt) {
        expect($attempt['level'])->toBe(0);
    }
})->with(['save', 'delete']);

it('propagates synchronous Scout engine failures so a projection worker can retry', function (string $operation) {
    $tour = spec019CreateOuterCommitTour($this);
    $engine = new Spec019FailingSynchronousScoutEngine;
    app(EngineManager::class)->extend('spec019_failing_sync', fn () => $engine);
    config(['scout.driver' => 'spec019_failing_sync', 'scout.queue' => false]);

    expect(fn () => $operation === 'index' ? $tour->searchable() : $tour->unsearchable())
        ->toThrow(RuntimeException::class, 'spec019_synchronous_engine_probe');
})->with(['index', 'remove']);

it('rejects reconcile cursors outside the native integer range', function (string $cursor) {
    expect(Artisan::call('tours:queue-translations', ['--after-id' => $cursor, '--limit' => '1']))->toBe(1);
    expect(Artisan::output())->toContain('After ID must be a non-negative integer.');
})->with([
    str_repeat('9', 40),
    (string) PHP_INT_MAX . '0',
    PHP_INT_SIZE === 8 ? '9223372036854775808' : '2147483648',
]);
