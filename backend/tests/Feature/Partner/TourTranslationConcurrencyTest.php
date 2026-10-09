<?php

// Spec019 T034 (tests-first): real-process translation/source races.
//
// FIVE scenarios over the shared Spec019TranslationProcess harness: the two
// baseline translation races (old-completion vs new source; first same-hash
// completion wins, RED at baseline until the job owner lands first-wins) plus
// three failure/overlap races authored here: (3) terminal provider failure
// paused in an independent child never clobbers a genuine overlapping
// same-hash ready completion; (4) terminal failure paused then a newer
// parent source edit commits, and the released failure stays scoped to its
// stale hash; (5) two overlapping independent source writers through the
// real TourService::updateTour (NEW Spec019SourceEditWorker.php) proven
// waiting on the real PostgreSQL row lock retain both disjoint patches with
// the final hash in es/it states. Root is the sole suite owner and runs all
// five real races; this file only authors, never runs, the suite.
//
// No RefreshDatabase escape hatch exists for this file on purpose: the global
// Pest setup wraps the default connection in a transaction that child
// processes could never see. Like the inherited TourContentRevisionTest
// committed-owner row-lock test, fixtures therefore commit for real on a
// named `spec019_owner` default connection and are deleted by OWN id in the
// finally block. The parent Pest process stays the sole suite owner; children
// bootstrap Laravel standalone and never touch tests/bootstrap.php.

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spec019TranslationProcess;
use Tests\Support\TourContentFixtures;

/**
 * Seed one committed tour + EN source + pending es/it states on the named
 * owner connection. Fills $owned with ONLY our ids for scoped cleanup.
 *
 * @param  array{userId: ?int, partnerId: ?int, categoryId: ?int, tourId: ?int}  $owned
 * @return array{userId: int, partnerId: int, categoryId: int, tourId: int, hash: string, englishTitle: string}
 */
function spec019RaceSeedCommittedTour(string $slugPrefix, array &$owned): array
{
    $service = app(TourTranslationService::class);

    $category = Category::create(['slug' => $slugPrefix . '-' . uniqid(), 'name' => 'Race harness']);
    $owned['categoryId'] = $category->id;

    $user = User::factory()->partner()->create();
    $owned['userId'] = $user->id;

    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $owned['partnerId'] = $partner->id;

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => $slugPrefix . '-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'draft',
    ]);
    $owned['tourId'] = $tour->id;

    // Deterministic valid source body: integers/nulls/order preserved.
    $english = $tour->translations()->create(['locale' => 'en'] + TourContentFixtures::englishAttributes());
    $hash = $service->sourceHash($english->fresh());

    // Real queue path (dispatches are faked): pending es/it desired states.
    $service->queueIfChanged($tour->fresh(), null);

    return [
        'userId' => $user->id,
        'partnerId' => $partner->id,
        'categoryId' => $category->id,
        'tourId' => $tour->id,
        'hash' => $hash,
        'englishTitle' => $english->title,
    ];
}

/**
 * Spawn one owned child worker. Args carry ONLY fixture IDs, the fake
 * expected hash, barrier paths and the fake-output marker.
 *
 * @return array{0: Spec019TranslationProcess, 1: array<string, string>}
 */
function spec019RaceSpawnChild(int $tourId, string $locale, string $hash, string $barrierDir, string $name, string $marker): array
{
    $paths = [
        'arrival' => "{$barrierDir}/{$name}.arrival",
        'release' => "{$barrierDir}/{$name}.release",
        'done' => "{$barrierDir}/{$name}.done",
        'out' => "{$barrierDir}/{$name}.out",
        'err' => "{$barrierDir}/{$name}.err",
    ];

    $proc = Spec019TranslationProcess::spawn(
        dirname(__DIR__, 2) . '/Support/Spec019TranslationWorker.php',
        [(string) $tourId, $locale, $hash, $paths['arrival'], $paths['release'], $paths['done'], $marker],
        $paths['out'],
        $paths['err'],
        "spec019-{$name}"
    );

    return [$proc, $paths];
}

function spec019RaceState(int $tourId, string $locale): TourTranslationState
{
    return TourTranslationState::where('tour_id', $tourId)->where('locale', $locale)->firstOrFail();
}

/**
 * Scoped cleanup: stop ONLY our child processes, delete ONLY our fixture rows
 * by id (never shared categories, never broad deletes, no Redis flush, no
 * generic max-id sweeps), remove ONLY our barrier files, then ALWAYS restore
 * the default named connection and purge the owner connection.
 *
 * When the DB identity guard failed ($guardPassed false) the finally block
 * issues NO writes at all: no fixture deletes, only process stops, own file
 * removal, and the mandatory restore/purge. Restore/purge run even if the
 * cleanup body itself throws, and a cleanup error is rethrown only after
 * restore/purge have been attempted.
 *
 * @param  array<int, Spec019TranslationProcess>  $procs
 * @param  array<int, string>  $files
 * @param  array{userId: ?int, partnerId: ?int, categoryId: ?int, tourId: ?int}  $owned
 */
function spec019RaceCleanup(array $procs, array $files, string $barrierDir, array $owned, string $previousDefault, bool $guardPassed): void
{
    $firstError = null;
    try {
        foreach ($procs as $proc) {
            $proc->terminate();
        }
        foreach ($procs as $proc) {
            $proc->close();
        }

        if ($guardPassed) {
            if (($owned['tourId'] ?? null) !== null) {
                TourTranslationState::where('tour_id', $owned['tourId'])->delete();
                TourTranslation::where('tour_id', $owned['tourId'])->delete();
                Tour::where('id', $owned['tourId'])->delete();
            }
            if (($owned['partnerId'] ?? null) !== null) {
                Partner::where('id', $owned['partnerId'])->delete();
            }
            if (($owned['userId'] ?? null) !== null) {
                User::where('id', $owned['userId'])->delete();
            }
            if (($owned['categoryId'] ?? null) !== null) {
                Category::where('id', $owned['categoryId'])->delete();
            }
        }

        foreach ($files as $file) {
            if ($file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
        if ($barrierDir !== '' && is_dir($barrierDir)) {
            @rmdir($barrierDir);
        }
    } catch (Throwable $e) {
        $firstError = $e;
    }

    try {
        DB::setDefaultConnection($previousDefault);
    } catch (Throwable $e) {
        $firstError ??= $e;
    }
    try {
        DB::purge('spec019_owner');
    } catch (Throwable $e) {
        $firstError ??= $e;
    }

    if ($firstError !== null) {
        throw $firstError;
    }
}

it('old translation completion never writes after a newer source edit commits', function () {
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $owned = ['userId' => null, 'partnerId' => null, 'categoryId' => null, 'tourId' => null];
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    try {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
        $guardPassed = true;

        Queue::fake();
        $seed = spec019RaceSeedCommittedTour('spec019-old-edit', $owned);

        $barrierDir = sys_get_temp_dir() . '/spec019-race-' . uniqid();
        mkdir($barrierDir, 0700, true);

        [$proc, $paths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'old', 'OLD');
        $procs[] = $proc;
        $files = array_values($paths);

        // Provider arrival is observed BEFORE any parent mutation: the old
        // job is paused inside the fake provider with its process alive.
        Spec019TranslationProcess::waitForFile($paths['arrival'], 60.0, 'old-job arrival', $paths['err']);
        $arrivedAt = microtime(true);
        expect($proc->isAlive())->toBeTrue();
        // A real child PHP process: concrete pid, never the parent pid.
        expect($proc->pid())->not->toBeNull();
        expect($proc->pid())->not->toBe(getmypid());
        expect(trim((string) file_get_contents($paths['arrival'])))->toBe('OLD');

        // A newer source edit commits (real TourService path) while the old
        // job is still paused in the provider.
        app(TourService::class)->updateTour(Tour::findOrFail($seed['tourId']), ['title' => 'Evening harbor walk']);
        $editedAt = microtime(true);
        expect($arrivedAt)->toBeLessThan($editedAt);

        $service = app(TourTranslationService::class);
        $newEnglish = Tour::findOrFail($seed['tourId'])->translations()->where('locale', 'en')->firstOrFail();
        expect($newEnglish->title)->toBe('Evening harbor walk');
        $newHash = $service->sourceHash($newEnglish);
        expect($newHash)->not->toBe($seed['hash']);

        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->source_hash)->toBe($newHash);
        expect($state->status)->toBe('pending');
        expect($state->translated_hash)->toBeNull();

        // Release the stale worker; its completion must be a harmless no-op.
        Spec019TranslationProcess::signal($paths['release']);
        Spec019TranslationProcess::waitForFile($paths['done'], 60.0, 'old-job done', $paths['err']);
        expect($proc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($paths['done'])), 'ok'))->toBeTrue();

        expect(TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->first())->toBeNull();
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->source_hash)->toBe($newHash);
        expect($state->status)->toBe('pending');
        expect($state->translated_hash)->toBeNull();
    } finally {
        spec019RaceCleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
    }
});

it('first same-hash completion wins when two jobs overlap in real processes', function () {
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $owned = ['userId' => null, 'partnerId' => null, 'categoryId' => null, 'tourId' => null];
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    try {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
        $guardPassed = true;

        Queue::fake();
        $seed = spec019RaceSeedCommittedTour('spec019-first-wins', $owned);

        $barrierDir = sys_get_temp_dir() . '/spec019-race-' . uniqid();
        mkdir($barrierDir, 0700, true);

        [$firstProc, $firstPaths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'first', 'FIRST');
        [$secondProc, $secondPaths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'second', 'SECOND');
        $procs = [$firstProc, $secondProc];
        $files = array_merge(array_values($firstPaths), array_values($secondPaths));

        // Two actual child processes overlap: both provider arrivals observed
        // while both processes are still alive. Two provider calls are allowed.
        Spec019TranslationProcess::waitForFile($firstPaths['arrival'], 60.0, 'first-job arrival', $firstPaths['err']);
        Spec019TranslationProcess::waitForFile($secondPaths['arrival'], 60.0, 'second-job arrival', $secondPaths['err']);
        expect($firstProc->isAlive())->toBeTrue();
        expect($secondProc->isAlive())->toBeTrue();
        // Two distinct real child PHP processes, neither the parent process.
        $firstPid = $firstProc->pid();
        $secondPid = $secondProc->pid();
        expect($firstPid)->not->toBeNull();
        expect($secondPid)->not->toBeNull();
        expect($firstPid)->not->toBe($secondPid);
        expect($firstPid)->not->toBe(getmypid());
        expect($secondPid)->not->toBe(getmypid());
        expect(trim((string) file_get_contents($firstPaths['arrival'])))->toBe('FIRST');
        expect(trim((string) file_get_contents($secondPaths['arrival'])))->toBe('SECOND');

        // Release the first job and wait until it is provably ready.
        Spec019TranslationProcess::signal($firstPaths['release']);
        Spec019TranslationProcess::waitForFile($firstPaths['done'], 60.0, 'first-job done', $firstPaths['err']);
        expect($firstProc->waitExit(30.0))->toBe(0);

        $expectedFirst = $seed['englishTitle'] . ' (es FIRST)';
        $derivative = TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->firstOrFail();
        expect($derivative->title)->toBe($expectedFirst);
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->status)->toBe('ready');
        expect($state->source_hash)->toBe($seed['hash']);
        expect($state->translated_hash)->toBe($seed['hash']);

        // Release the second job; both provider calls happened, yet the first
        // completed derivative must stand (first-wins, no overwrite).
        // Baseline RED: the second completion overwrites with the SECOND text.
        Spec019TranslationProcess::signal($secondPaths['release']);
        Spec019TranslationProcess::waitForFile($secondPaths['done'], 60.0, 'second-job done', $secondPaths['err']);
        expect($secondProc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($secondPaths['done'])), 'ok'))->toBeTrue();

        $derivative = TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->firstOrFail();
        expect($derivative->title)->toBe($expectedFirst);
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->status)->toBe('ready');
        expect($state->source_hash)->toBe($seed['hash']);
        expect($state->translated_hash)->toBe($seed['hash']);
    } finally {
        spec019RaceCleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
    }
});

/**
 * Spawn one owned child source writer. Args carry ONLY the fixture tour id,
 * one disjoint patch field/value, and barrier paths.
 *
 * @return array{0: Spec019TranslationProcess, 1: array<string, string>}
 */
function spec019RaceSpawnSourceChild(int $tourId, string $field, string $value, string $barrierDir, string $name): array
{
    $paths = [
        'arrival' => "{$barrierDir}/{$name}.arrival",
        'release' => "{$barrierDir}/{$name}.release",
        'done' => "{$barrierDir}/{$name}.done",
        'out' => "{$barrierDir}/{$name}.out",
        'err' => "{$barrierDir}/{$name}.err",
    ];

    $proc = Spec019TranslationProcess::spawn(
        dirname(__DIR__, 2) . '/Support/Spec019SourceEditWorker.php',
        [(string) $tourId, $field, $value, $paths['arrival'], $paths['release'], $paths['done']],
        $paths['out'],
        $paths['err'],
        "spec019-{$name}"
    );

    return [$proc, $paths];
}

/**
 * Parse the real PostgreSQL backend pid a source child wrote into its
 * arrival file. Throws loudly when the file carries no pid.
 */
function spec019RaceBackendPid(string $arrivalPath): int
{
    $content = trim((string) @file_get_contents($arrivalPath));
    if (! preg_match('/\bpg=(\d+)\b/', $content, $matches) || (int) $matches[1] <= 0) {
        throw new RuntimeException("arrival file carries no pg backend pid: '{$arrivalPath}' content '{$content}'.");
    }

    return (int) $matches[1];
}

/**
 * Bounded proof that THOSE EXACT child backends are waiting on the real
 * PostgreSQL lock: a single poll instant where every listed pid shows
 * wait_event_type='Lock' in pg_stat_activity, corroborated by at least one
 * ungranted pg_locks row among them. Returns the proof snapshot; throws with
 * the last snapshot (truncated, no credentials) when the deadline passes.
 * Sleep timing or process aliveness alone is never accepted as proof.
 *
 * @param  array<int, int>  $backendPids
 */
function spec019RaceAwaitPgLockWaits(array $backendPids, float $timeoutSeconds): string
{
    $pids = array_values(array_unique(array_map('intval', $backendPids)));
    foreach ($pids as $pid) {
        if ($pid <= 0) {
            throw new RuntimeException('pg lock proof requires positive backend pids.');
        }
    }
    $list = implode(',', $pids);

    $deadline = microtime(true) + $timeoutSeconds;
    $lastSnapshot = 'none';
    while (true) {
        // pg_stat_activity can retain a transaction statistics snapshot;
        // refresh it while the parent deliberately holds the Tour lock.
        DB::select('select pg_stat_clear_snapshot()');
        $activity = DB::select(
            "select pid, application_name, wait_event_type, wait_event from pg_stat_activity where pid in ({$list})"
        );
        $byPid = [];
        foreach ($activity as $row) {
            $byPid[(int) $row->pid] = $row;
        }
        $waiting = [];
        foreach ($pids as $pid) {
            if (isset($byPid[$pid]) && (($byPid[$pid]->wait_event_type ?? '') === 'Lock')) {
                $waiting[] = $pid;
            }
        }
        $locks = DB::select(
            "select pid, locktype, mode from pg_locks where pid in ({$list}) and not granted"
        );
        $lastSnapshot = substr((string) json_encode(['activity' => $activity, 'ungranted' => $locks]), 0, 2000);

        $blockedPids = array_unique(array_map(static fn ($lock): int => (int) $lock->pid, $locks));
        if (count($waiting) === count($pids) && count(array_intersect($pids, $blockedPids)) === count($pids)) {
            $proofDirectory = getenv('SPEC019_RACE_EVIDENCE_DIR');
            if (is_string($proofDirectory) && str_starts_with($proofDirectory, '/tmp/spec019-race-evidence-')) {
                if (! is_dir($proofDirectory)) {
                    mkdir($proofDirectory, 0700, true);
                }
                file_put_contents($proofDirectory . '/overlapping-writers.json', json_encode([
                    'activity' => $activity, 'ungranted_locks' => $locks, 'observed_at' => microtime(true),
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }

            return 'both writers lock-waiting on pids ' . $list
                . '; wait_event=' . substr((string) json_encode($waiting), 0, 100)
                . '; ungranted_locks=' . count($locks)
                . '; snapshot=' . $lastSnapshot;
        }

        if (microtime(true) >= $deadline) {
            throw new RuntimeException(
                "pg lock-wait proof timed out after {$timeoutSeconds}s for backend pids {$list}."
                . " Last snapshot: {$lastSnapshot}"
            );
        }
        usleep(100000);
    }
}

it('terminal provider failure never clobbers a genuine ready completion from an overlapping same-hash job', function () {
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $owned = ['userId' => null, 'partnerId' => null, 'categoryId' => null, 'tourId' => null];
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    try {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
        $guardPassed = true;

        Queue::fake();
        $seed = spec019RaceSeedCommittedTour('spec019-fail-after-ready', $owned);

        $barrierDir = sys_get_temp_dir() . '/spec019-race-' . uniqid();
        mkdir($barrierDir, 0700, true);

        // Two independent children overlap on the SAME tour/locale/hash: one
        // genuine success, one terminal FAIL marker that throws the harmless
        // fixed PermanentTranslationException after release (actual job
        // failure path; the parent writes no fake state itself).
        [$goodProc, $goodPaths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'good', 'GOOD');
        [$failProc, $failPaths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'fail', 'FAIL');
        $procs = [$goodProc, $failProc];
        $files = array_merge(array_values($goodPaths), array_values($failPaths));

        Spec019TranslationProcess::waitForFile($goodPaths['arrival'], 60.0, 'good-job arrival', $goodPaths['err']);
        Spec019TranslationProcess::waitForFile($failPaths['arrival'], 60.0, 'fail-job arrival', $failPaths['err']);
        expect($goodProc->isAlive())->toBeTrue();
        expect($failProc->isAlive())->toBeTrue();
        $goodPid = $goodProc->pid();
        $failPid = $failProc->pid();
        expect($goodPid)->not->toBeNull();
        expect($failPid)->not->toBeNull();
        expect($goodPid)->not->toBe($failPid);
        expect($goodPid)->not->toBe(getmypid());
        expect($failPid)->not->toBe(getmypid());
        expect(trim((string) file_get_contents($goodPaths['arrival'])))->toBe('GOOD');
        expect(trim((string) file_get_contents($failPaths['arrival'])))->toBe('FAIL');

        // Release the success and await the REAL ready derivative + state.
        Spec019TranslationProcess::signal($goodPaths['release']);
        Spec019TranslationProcess::waitForFile($goodPaths['done'], 60.0, 'good-job done', $goodPaths['err']);
        expect($goodProc->waitExit(30.0))->toBe(0);

        $expected = $seed['englishTitle'] . ' (es GOOD)';
        expect(TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->firstOrFail()->title)->toBe($expected);
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->status)->toBe('ready');
        expect($state->source_hash)->toBe($seed['hash']);
        expect($state->translated_hash)->toBe($seed['hash']);
        expect($state->last_error_code)->toBeNull();

        // Release the terminal failure; its markFailed is scoped to the same
        // hash AND non-ready rows, so the genuine ready row must stand.
        Spec019TranslationProcess::signal($failPaths['release']);
        Spec019TranslationProcess::waitForFile($failPaths['done'], 60.0, 'fail-job done', $failPaths['err']);
        expect($failProc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($failPaths['done'])), 'ok'))->toBeTrue();

        expect(TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->firstOrFail()->title)->toBe($expected);
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->status)->toBe('ready');
        expect($state->source_hash)->toBe($seed['hash']);
        expect($state->translated_hash)->toBe($seed['hash']);
        expect($state->last_error_code)->toBeNull();
        $raw = DB::selectOne(
            'select status, source_hash, translated_hash, last_error_code from tour_translation_states where tour_id = ? and locale = ?',
            [$seed['tourId'], 'es']
        );
        expect($raw->status)->toBe('ready');
        expect($raw->source_hash)->toBe($seed['hash']);
        expect($raw->translated_hash)->toBe($seed['hash']);
        expect($raw->last_error_code)->toBeNull();
    } finally {
        spec019RaceCleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
    }
});

it('terminal provider failure stays scoped to its stale hash after a newer source edit commits', function () {
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $owned = ['userId' => null, 'partnerId' => null, 'categoryId' => null, 'tourId' => null];
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    try {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
        $guardPassed = true;

        Queue::fake();
        $seed = spec019RaceSeedCommittedTour('spec019-fail-stale', $owned);

        $barrierDir = sys_get_temp_dir() . '/spec019-race-' . uniqid();
        mkdir($barrierDir, 0700, true);

        // Terminal failure pauses INSIDE the fake provider in its own child
        // process with the old hash; the arrival is observed BEFORE any
        // parent mutation, so the overlap is a real process race, never a
        // sequential hash-only rehearsal.
        [$proc, $paths] = spec019RaceSpawnChild($seed['tourId'], 'es', $seed['hash'], $barrierDir, 'stalefail', 'FAIL');
        $procs[] = $proc;
        $files = array_values($paths);

        Spec019TranslationProcess::waitForFile($paths['arrival'], 60.0, 'stale-fail arrival', $paths['err']);
        expect($proc->isAlive())->toBeTrue();
        expect($proc->pid())->not->toBeNull();
        expect($proc->pid())->not->toBe(getmypid());
        expect(trim((string) file_get_contents($paths['arrival'])))->toBe('FAIL');

        // A newer source edit commits through the real TourService path while
        // the stale failure is still paused in the provider.
        app(TourService::class)->updateTour(Tour::findOrFail($seed['tourId']), ['title' => 'Sunrise harbor walk']);
        $service = app(TourTranslationService::class);
        $newEnglish = Tour::findOrFail($seed['tourId'])->translations()->where('locale', 'en')->firstOrFail();
        expect($newEnglish->title)->toBe('Sunrise harbor walk');
        $newHash = $service->sourceHash($newEnglish);
        expect($newHash)->not->toBe($seed['hash']);

        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->source_hash)->toBe($newHash);
        expect($state->status)->toBe('pending');
        expect($state->translated_hash)->toBeNull();

        // Release the stale failure: its markFailed matches ONLY the old
        // source_hash, so the newer pending row must persist untouched and no
        // old derivative may appear.
        Spec019TranslationProcess::signal($paths['release']);
        Spec019TranslationProcess::waitForFile($paths['done'], 60.0, 'stale-fail done', $paths['err']);
        expect($proc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($paths['done'])), 'ok'))->toBeTrue();

        expect(Tour::findOrFail($seed['tourId'])->translations()->where('locale', 'en')->firstOrFail()->title)
            ->toBe('Sunrise harbor walk');
        expect(TourTranslation::where('tour_id', $seed['tourId'])->where('locale', 'es')->first())->toBeNull();
        $state = spec019RaceState($seed['tourId'], 'es');
        expect($state->source_hash)->toBe($newHash);
        expect($state->status)->toBe('pending');
        expect($state->translated_hash)->toBeNull();
        expect($state->last_error_code)->toBeNull();
    } finally {
        spec019RaceCleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
    }
});

it('overlapping source edits through TourService retain both disjoint patches with the final hash in es/it states', function () {
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $owned = ['userId' => null, 'partnerId' => null, 'categoryId' => null, 'tourId' => null];
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    $holdingOpen = false;
    try {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
        $guardPassed = true;

        Queue::fake();
        $seed = spec019RaceSeedCommittedTour('spec019-source-race', $owned);

        // Parent holds the Tour row lock in a real committed named-connection
        // transaction BEFORE spawning, so both children provably block on it.
        DB::beginTransaction();
        Tour::where('id', $seed['tourId'])->lockForUpdate()->first();
        $holdingOpen = true;

        $barrierDir = sys_get_temp_dir() . '/spec019-race-' . uniqid();
        mkdir($barrierDir, 0700, true);

        // Two DIFFERENT PHP children with disjoint patches and explicit
        // arrival/release/done barriers plus real pg backend pids.
        [$titleProc, $titlePaths] = spec019RaceSpawnSourceChild($seed['tourId'], 'title', 'Midday harbor walk', $barrierDir, 'srctitle');
        [$pointProc, $pointPaths] = spec019RaceSpawnSourceChild($seed['tourId'], 'meeting_point', 'Old port gate', $barrierDir, 'srcpoint');
        $procs = [$titleProc, $pointProc];
        $files = array_merge(array_values($titlePaths), array_values($pointPaths));

        Spec019TranslationProcess::waitForFile($titlePaths['arrival'], 60.0, 'title-writer arrival', $titlePaths['err']);
        Spec019TranslationProcess::waitForFile($titlePaths['release'], 60.0, 'title-writer attempting', $titlePaths['err']);
        Spec019TranslationProcess::waitForFile($pointPaths['arrival'], 60.0, 'point-writer arrival', $pointPaths['err']);
        Spec019TranslationProcess::waitForFile($pointPaths['release'], 60.0, 'point-writer attempting', $pointPaths['err']);

        // Real distinct child PHP processes, neither the parent process.
        $titlePid = $titleProc->pid();
        $pointPid = $pointProc->pid();
        expect($titlePid)->not->toBeNull();
        expect($pointPid)->not->toBeNull();
        expect($titlePid)->not->toBe($pointPid);
        expect($titlePid)->not->toBe(getmypid());
        expect($pointPid)->not->toBe(getmypid());
        expect(trim((string) file_get_contents($titlePaths['arrival'])))->toContain('app=spec019-src-title');
        expect(trim((string) file_get_contents($pointPaths['arrival'])))->toContain('app=spec019-src-meeting_point');

        // Exact child backend pids from their own arrival files.
        $titlePg = spec019RaceBackendPid($titlePaths['arrival']);
        $pointPg = spec019RaceBackendPid($pointPaths['arrival']);
        expect($titlePg)->not->toBe($pointPg);

        // Real PostgreSQL lock proof for THOSE backends (bounded observation,
        // never sleep timing or aliveness alone): both writers must be seen
        // waiting on the lock before the parent releases.
        $evidence = spec019RaceAwaitPgLockWaits([$titlePg, $pointPg], 30.0);
        expect($evidence)->not->toBe('');

        // Release: commit the holding transaction so both writers proceed and
        // serialize through the real TourService path.
        DB::commit();
        $holdingOpen = false;

        Spec019TranslationProcess::waitForFile($titlePaths['done'], 60.0, 'title-writer done', $titlePaths['err']);
        Spec019TranslationProcess::waitForFile($pointPaths['done'], 60.0, 'point-writer done', $pointPaths['err']);
        expect($titleProc->waitExit(30.0))->toBe(0);
        expect($pointProc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($titlePaths['done'])), 'ok'))->toBeTrue();
        expect(str_starts_with(trim((string) file_get_contents($pointPaths['done'])), 'ok'))->toBeTrue();

        // Both disjoint patches retained; the fresh final English hash lands
        // in BOTH es and it desired states.
        $english = Tour::findOrFail($seed['tourId'])->translations()->where('locale', 'en')->firstOrFail();
        expect($english->title)->toBe('Midday harbor walk');
        expect($english->meeting_point)->toBe('Old port gate');
        $finalHash = app(TourTranslationService::class)->sourceHash($english->fresh());
        expect(spec019RaceState($seed['tourId'], 'es')->source_hash)->toBe($finalHash);
        expect(spec019RaceState($seed['tourId'], 'it')->source_hash)->toBe($finalHash);
        foreach (['es', 'it'] as $locale) {
            expect(spec019RaceState($seed['tourId'], $locale)->status)->toBe('pending');
            expect(spec019RaceState($seed['tourId'], $locale)->translated_hash)->toBeNull();
        }
    } finally {
        $holdError = null;
        if ($holdingOpen) {
            try {
                DB::rollBack();
            } catch (Throwable $e) {
                $holdError = $e;
            }
        }
        spec019RaceCleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
        if ($holdError !== null) {
            throw $holdError;
        }
    }
});
