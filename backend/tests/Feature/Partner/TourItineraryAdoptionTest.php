<?php

// Spec019 T064 (tests-first, RED): legacy itinerary adoption acceptance.
//
// Planned artisan name CONFIRMED from specs/019-tour-content-data/
// quickstart.md:70-71 as `tours:adopt-itineraries` (with --dry-run,
// --after-id=0, --limit=100) — matching tasks T067/T068 and the existing
// `tours:queue-translations` cursor/limit conventions. This file uses ONLY
// that planned name; if the command ever appears under a different name,
// these tests stay red and the difference must be reported, not absorbed.
//
// Root first ran these against the absent command and retained the RED
// results before production authoring. The command and normalizer now
// exist; acceptance requires the same assertions and real PostgreSQL races.
//
// Integration rules honored here: full artisan + current revision/hash/
// scheduling API, NO mocks of methods under test; fake queue only for
// dispatch observation, blank GEMINI_API_KEY (no live model); raw
// tours.itinerary asserted retained; populated EN (including intentional
// []) never overwritten; missing EN reported without row fabrication;
// dry-run performs zero content/state/queue writes; cursor is a strict
// non-negative integer and limit is strictly 1-500; ID-ordered bounded
// pages; command output carries counts/cursor and never raw content; the
// race test uses a real PHP subprocess + barrier files + real PostgreSQL
// row-lock proof (never sleeps or fabricated interleavings) to prove a
// concurrently populated source wins.

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spec019AdoptionRaceHelper;
use Tests\Support\Spec019DispatchFailureQueue;
use Tests\Support\Spec019TranslationProcess;
use Tests\Support\TourContentFixtures;

/**
 * @return array{userIds: array<int, int>, partnerIds: array<int, int>, categoryIds: array<int, int>, tourIds: array<int, int>}
 */
function spec019AdoptOwned(): array
{
    return ['userIds' => [], 'partnerIds' => [], 'categoryIds' => [], 'tourIds' => []];
}

/**
 * Seed one committed tour with raw legacy tours.itinerary plus an optional
 * EN row. $rawLegacy null leaves the raw column NULL; $enOverrides null
 * creates NO EN row (missing-EN fixture).
 *
 * @param  array{userIds: array<int, int>, partnerIds: array<int, int>, categoryIds: array<int, int>, tourIds: array<int, int>}  $owned
 */
function spec019AdoptSeed(string $slugPrefix, array &$owned, mixed $rawLegacy, ?array $enOverrides): int
{
    $category = Category::create(['slug' => $slugPrefix . '-' . uniqid(), 'name' => 'Adoption harness']);
    $owned['categoryIds'][] = $category->id;

    $user = User::factory()->partner()->create();
    $owned['userIds'][] = $user->id;

    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $owned['partnerIds'][] = $partner->id;

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
    $owned['tourIds'][] = $tour->id;

    if ($rawLegacy !== null) {
        DB::table('tours')->where('id', $tour->id)->update([
            'itinerary' => is_string($rawLegacy) ? $rawLegacy : json_encode($rawLegacy, JSON_THROW_ON_ERROR),
        ]);
    }

    if ($enOverrides !== null) {
        $tour->translations()->create(['locale' => 'en'] + TourContentFixtures::englishAttributes($enOverrides));
    }

    return $tour->id;
}

function spec019AdoptRawLegacy(int $tourId): mixed
{
    $value = DB::table('tours')->where('id', $tourId)->value('itinerary');
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    return $value;
}

function spec019AdoptEnglish(int $tourId): ?TourTranslation
{
    return TourTranslation::where('tour_id', $tourId)->where('locale', 'en')->first();
}

function spec019AdoptStateCount(int $tourId): int
{
    return TourTranslationState::where('tour_id', $tourId)->count();
}

/** JSONB object keys are unordered; retain strict values and ordered lists. */
function spec019AdoptCanonical(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    $isList = array_is_list($value);
    foreach ($value as $key => $entry) {
        $value[$key] = spec019AdoptCanonical($entry);
    }
    if (! $isList) {
        ksort($value);
    }

    return $value;
}

it('adopts positional valid legacy strings to a day sequence and retains the raw legacy source', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        expect((string) config('services.gemini.api_key'))->toBe('');
        Queue::fake();

        $legacy = ['Old town walk', 'Harbor sunset'];
        $tourId = spec019AdoptSeed('spec019-adopt-strings', $owned, $legacy, ['title' => 'String legacy tour', 'itinerary' => null]);

        $exit = Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']);
        $output = Artisan::output();

        expect($exit)->toBe(0);
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourId)->itinerary))->toBe(spec019AdoptCanonical([
            ['day' => 1, 'title' => 'Old town walk', 'description' => null, 'stops' => []],
            ['day' => 2, 'title' => 'Harbor sunset', 'description' => null, 'stops' => []],
        ]));
        // Raw legacy source is retained verbatim, never rewritten.
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourId)))->toBe(spec019AdoptCanonical($legacy));
        // Changed accepted source is scheduled through the revision service.
        expect(Queue::pushed(GenerateTourTranslationJob::class))->toHaveCount(2);
        // Output sanitization: counts/cursor only, no raw content.
        expect($output)->not->toContain('Old town walk');
        expect($output)->not->toContain('Harbor sunset');
        expect($output)->toContain('--after-id=');
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('preserves populated EN arrays including intentional [] across apply and repeat runs', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $populated = [
            ['day' => 1, 'title' => 'Authored day', 'description' => 'Authored', 'stops' => [
                ['title' => 'Authored stop', 'description' => null, 'duration_minutes' => 45],
            ]],
        ];
        $tourPopulated = spec019AdoptSeed('spec019-adopt-kept', $owned, ['Legacy noise'], ['title' => 'Kept tour', 'itinerary' => $populated]);
        $tourEmpty = spec019AdoptSeed('spec019-adopt-kept-empty', $owned, ['Other noise'], ['title' => 'Kept empty tour', 'itinerary' => []]);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourPopulated)->itinerary))->toBe(spec019AdoptCanonical($populated));
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourEmpty)->itinerary))->toBe(spec019AdoptCanonical([]));
        // Idempotent repeat: still untouched, raw retained.
        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourPopulated)->itinerary))->toBe(spec019AdoptCanonical($populated));
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourEmpty)->itinerary))->toBe(spec019AdoptCanonical([]));
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourPopulated)))->toBe(spec019AdoptCanonical(['Legacy noise']));
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourEmpty)))->toBe(spec019AdoptCanonical(['Other noise']));
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('reports missing EN without creating rows', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $tourId = spec019AdoptSeed('spec019-adopt-noen', $owned, ['Orphan legacy day'], null);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        $output = Artisan::output();

        // No EN row is fabricated for governed repair; nothing scheduled.
        expect(spec019AdoptEnglish($tourId))->toBeNull();
        expect(spec019AdoptStateCount($tourId))->toBe(0);
        expect(Queue::pushed(GenerateTourTranslationJob::class))->toHaveCount(0);
        expect(strtolower($output))->toContain('missing');
        expect($output)->not->toContain('Orphan legacy day');
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('normalizes malformed, partially invalid, and empty legacy records as a whole to []', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        // Malformed: scalar day item can never become a titled day.
        $malformed = spec019AdoptSeed('spec019-adopt-malformed', $owned, [42], ['title' => 'Malformed tour', 'itinerary' => null]);
        // Partially invalid: one bad day poisons the whole record, no partial fabrication.
        $partial = spec019AdoptSeed('spec019-adopt-partial', $owned, [
            ['day' => 1, 'title' => 'Fine day'],
            ['day' => 99, 'title' => 'Out of range day'],
        ], ['title' => 'Partial tour', 'itinerary' => null]);
        // Empty: nothing meaningful to adopt.
        $empty = spec019AdoptSeed('spec019-adopt-empty', $owned, [], ['title' => 'Empty tour', 'itinerary' => null]);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);

        foreach ([$malformed, $partial, $empty] as $tourId) {
            expect(spec019AdoptCanonical(spec019AdoptEnglish($tourId)->itinerary))->toBe(spec019AdoptCanonical([]));
        }
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($malformed)))->toBe(spec019AdoptCanonical([42]));
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('rejects invalid structured days as a whole while allowing omitted stops', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $missingDay = spec019AdoptSeed('spec019-adopt-noday', $owned, [['title' => 'No day key']], ['title' => 'Missing day tour', 'itinerary' => null]);
        $stringDay = spec019AdoptSeed('spec019-adopt-strday', $owned, [['day' => '1', 'title' => 'String day']], ['title' => 'String day tour', 'itinerary' => null]);
        $nullStops = spec019AdoptSeed('spec019-adopt-nullstops', $owned, [['day' => 1, 'title' => 'Null stops', 'stops' => null]], ['title' => 'Null stops tour', 'itinerary' => null]);
        $badDuration = spec019AdoptSeed('spec019-adopt-baddur', $owned, [['day' => 1, 'title' => 'Bad duration', 'stops' => [
            ['title' => 'Too long', 'duration_minutes' => 1441],
        ]]], ['title' => 'Bad duration tour', 'itinerary' => null]);
        $longTitle = spec019AdoptSeed('spec019-adopt-longtitle', $owned, [['day' => 1, 'title' => str_repeat('t', 161)]], ['title' => 'Long title tour', 'itinerary' => null]);
        // Omitted stops is the allowed legacy shape and normalizes to [].
        $omittedStops = spec019AdoptSeed('spec019-adopt-omitstops', $owned, [['day' => 1, 'title' => 'No stops key']], ['title' => 'Omitted stops tour', 'itinerary' => null]);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);

        foreach ([$missingDay, $stringDay, $nullStops, $badDuration, $longTitle] as $tourId) {
            expect(spec019AdoptCanonical(spec019AdoptEnglish($tourId)->itinerary))->toBe(spec019AdoptCanonical([]));
        }
        expect(spec019AdoptCanonical(spec019AdoptEnglish($omittedStops)->itinerary))->toBe(spec019AdoptCanonical([
            ['day' => 1, 'title' => 'No stops key', 'description' => null, 'stops' => []],
        ]));
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('adopts exact boundary values: day 30, title 160, description 2000, 20 stops, duration 1440', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $stops = [];
        for ($i = 1; $i <= 20; $i++) {
            $stops[] = ['title' => "Stop {$i}", 'description' => null, 'duration_minutes' => $i === 20 ? 1440 : $i];
        }
        $legacy = [['day' => 30, 'title' => str_repeat('v', 160), 'description' => str_repeat('d', 2000), 'stops' => $stops]];
        $tourId = spec019AdoptSeed('spec019-adopt-bounds', $owned, $legacy, ['title' => 'Boundary tour', 'itinerary' => null]);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourId)->itinerary))->toBe(spec019AdoptCanonical($legacy));
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourId)))->toBe(spec019AdoptCanonical($legacy));
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('dry-run writes absolutely nothing: no content, state, or queue changes', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $eligible = spec019AdoptSeed('spec019-adopt-dry', $owned, ['Dry run day'], ['title' => 'Dry tour', 'itinerary' => null]);
        $kept = spec019AdoptSeed('spec019-adopt-dry-kept', $owned, ['Noise'], ['title' => 'Dry kept', 'itinerary' => []]);
        $service = app(TourTranslationService::class);
        $beforeKept = $service->sourceHash(spec019AdoptEnglish($kept)->fresh());
        $statesBefore = TourTranslationState::count();
        // Fixture creation legitimately schedules index work. Observe only
        // jobs scheduled by the command itself, including any job class.
        Queue::fake();

        expect(Artisan::call('tours:adopt-itineraries', ['--dry-run' => true, '--after-id' => '0', '--limit' => '100']))->toBe(0);
        $output = Artisan::output();

        expect(spec019AdoptEnglish($eligible)->itinerary)->toBeNull();
        expect($service->sourceHash(spec019AdoptEnglish($kept)->fresh()))->toBe($beforeKept);
        expect(TourTranslationState::count())->toBe($statesBefore);
        Queue::assertNothingPushed();
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($eligible)))->toBe(spec019AdoptCanonical(['Dry run day']));
        expect(strtolower($output))->toContain('dry-run');
        expect($output)->not->toContain('Dry run day');
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('rejects non-strict resume cursors', function (string $cursor) {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => $cursor, '--limit' => '100']))->toBe(1);
        expect(Artisan::output())->toContain('After ID must be a non-negative integer.');
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
})->with([
    '-1',
    'abc',
    '1.5',
    '',
    str_repeat('9', 40),
    PHP_INT_SIZE === 8 ? '9223372036854775808' : '2147483648',
]);

it('rejects limits outside the strict 1-500 range', function (string $limit) {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => $limit]))->toBe(1);
        expect(Artisan::output())->toContain('Limit must be between 1 and 500.');
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
})->with(['0', '501', 'abc', '-5', '10.5', '']);

it('pages ID-ordered bounded runs with counts and a resume cursor', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        Queue::fake();

        $first = spec019AdoptSeed('spec019-adopt-page', $owned, ['Page one'], ['title' => 'Page one tour', 'itinerary' => null]);
        $second = spec019AdoptSeed('spec019-adopt-page', $owned, ['Page two'], ['title' => 'Page two tour', 'itinerary' => null]);
        $third = spec019AdoptSeed('spec019-adopt-page', $owned, ['Page three'], ['title' => 'Page three tour', 'itinerary' => null]);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '2']))->toBe(0);
        $output = Artisan::output();

        // Bounded ID-ordered page: first two adopted, third untouched.
        expect(spec019AdoptEnglish($first)->itinerary)->not->toBeNull();
        expect(spec019AdoptEnglish($second)->itinerary)->not->toBeNull();
        expect(spec019AdoptEnglish($third)->itinerary)->toBeNull();
        expect($output)->toContain('Inspected 2');
        expect($output)->toContain('--after-id=' . $second);
        expect($output)->not->toContain('Page one');

        // Resume from the reported cursor adopts the remainder.
        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => (string) $second, '--limit' => '2']))->toBe(0);
        expect(spec019AdoptEnglish($third)->itinerary)->not->toBeNull();
    } finally {
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('adoption inside a holding transaction composes with a blocked source edit: adopted days survive the later title patch', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    $holdingOpen = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        expect((string) config('services.gemini.api_key'))->toBe('');
        Queue::fake();

        $legacy = ['Old town walk', 'Harbor sunset'];
        $tourId = spec019AdoptSeed('spec019-adopt-race', $owned, $legacy, ['title' => 'Original title', 'itinerary' => null]);

        // Parent holds the real Tour row lock BEFORE spawning, so the child
        // provably blocks on it (no sleep-based simulation).
        DB::beginTransaction();
        Tour::where('id', $tourId)->lockForUpdate()->first();
        $holdingOpen = true;

        $barrierDir = sys_get_temp_dir() . '/spec019-adopt-' . uniqid();
        mkdir($barrierDir, 0700, true);

        [$proc, $paths] = Spec019AdoptionRaceHelper::spawnSourceEdit($tourId, 'title', 'Midday harbor walk', $barrierDir, 'srctitle');
        $procs[] = $proc;
        $files = array_values($paths);

        Spec019TranslationProcess::waitForFile($paths['arrival'], 60.0, 'source-writer arrival', $paths['err']);
        Spec019TranslationProcess::waitForFile($paths['release'], 60.0, 'source-writer attempting', $paths['err']);
        expect($proc->isAlive())->toBeTrue();
        expect($proc->pid())->not->toBeNull();
        expect($proc->pid())->not->toBe(getmypid());

        $childPg = Spec019AdoptionRaceHelper::backendPid($paths['arrival']);
        $evidence = Spec019AdoptionRaceHelper::awaitPgLockWaits([$childPg], 30.0);
        expect($evidence)->not->toBe('');

        // Adoption runs on the same connection while the real child writer
        // is lock-waiting; the null-EN eligibility recheck under the common
        // Tour-first lock must hold here.
        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        expect(Artisan::output())->not->toContain('Old town walk');

        DB::commit();
        $holdingOpen = false;

        Spec019TranslationProcess::waitForFile($paths['done'], 60.0, 'source-writer done', $paths['err']);
        expect($proc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($paths['done'])), 'ok'))->toBeTrue();

        // Populated source wins: the concurrent title patch stands, the
        // adopted days survive alongside it, and the raw source is intact.
        $english = spec019AdoptEnglish($tourId);
        expect($english)->not->toBeNull();
        expect($english->title)->toBe('Midday harbor walk');
        expect(spec019AdoptCanonical($english->itinerary))->toBe(spec019AdoptCanonical([
            ['day' => 1, 'title' => 'Old town walk', 'description' => null, 'stops' => []],
            ['day' => 2, 'title' => 'Harbor sunset', 'description' => null, 'stops' => []],
        ]));
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourId)))->toBe(spec019AdoptCanonical($legacy));
    } finally {
        $holdError = null;
        if ($holdingOpen) {
            try {
                DB::rollBack();
            } catch (Throwable $e) {
                $holdError = $e;
            }
        }
        Spec019AdoptionRaceHelper::cleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
        if ($holdError !== null) {
            throw $holdError;
        }
    }
});

it('an adopted source stays committed when translation dispatch fails after commit', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $guardPassed = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        expect((string) config('services.gemini.api_key'))->toBe('');

        $tourId = spec019AdoptSeed('spec019-adopt-commit', $owned, ['Commit day'], ['title' => 'Commit probe', 'itinerary' => null]);

        // Real post-commit mechanics (not Queue::fake): translation pushes
        // fail at execution, so the adopted source must already be durable.
        Spec019DispatchFailureQueue::bind(true);

        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);

        $english = spec019AdoptEnglish($tourId)->fresh();
        expect(spec019AdoptCanonical($english->itinerary))->toBe(spec019AdoptCanonical([
            ['day' => 1, 'title' => 'Commit day', 'description' => null, 'stops' => []],
        ]));
        $hash = app(TourTranslationService::class)->sourceHash($english);
        foreach (['es', 'it'] as $locale) {
            expect(TourTranslationState::where('tour_id', $tourId)->where('locale', $locale)->firstOrFail()->source_hash)->toBe($hash);
        }
        $attempts = Spec019DispatchFailureQueue::translationAttempts();
        expect($attempts)->toHaveCount(2);
        foreach ($attempts as $attempt) {
            expect($attempt['level'])->toBe(0);
            expect($attempt['failed'])->toBeTrue();
        }
    } finally {
        Spec019DispatchFailureQueue::restore();
        Spec019AdoptionRaceHelper::cleanup([], [], '', $owned, $previousDefault, $guardPassed);
    }
});

it('a waiting adoption preserves an already populated itinerary: populated source wins', function () {
    $previousDefault = null;
    Spec019AdoptionRaceHelper::useOwnerConnection($previousDefault);
    $owned = spec019AdoptOwned();
    $procs = [];
    $files = [];
    $barrierDir = '';
    $guardPassed = false;
    $holdingOpen = false;
    try {
        Spec019AdoptionRaceHelper::assertSafeDatabase();
        $guardPassed = true;
        config()->set('services.gemini.api_key', '');
        expect((string) config('services.gemini.api_key'))->toBe('');
        Queue::fake();

        // Complementary interleave to the title race above: here ADOPTION is
        // the waiter. The fixture starts with EN itinerary NULL so the child
        // adoption run is eligible at spawn time; the parent then populates
        // a DISTINCT canonical itinerary while still holding the lock. The
        // released child must recheck EN under its Tour-first lock and skip,
        // preserving the exact populated source instead of adopting stale
        // legacy strings over it.
        $legacy = ['Legacy first walk', 'Legacy second walk'];
        $tourId = spec019AdoptSeed('spec019-adopt-wait', $owned, $legacy, ['title' => 'Waiting adoption tour', 'itinerary' => null]);

        // Parent holds the real Tour row lock BEFORE spawning, so the child
        // adoption provably blocks on it (no sleep-based simulation).
        DB::beginTransaction();
        Tour::where('id', $tourId)->lockForUpdate()->first();
        $holdingOpen = true;

        $barrierDir = sys_get_temp_dir() . '/spec019-adopt-' . uniqid();
        mkdir($barrierDir, 0700, true);

        [$proc, $paths] = Spec019AdoptionRaceHelper::spawnAdoption($tourId, $barrierDir, 'adoptwait');
        $procs[] = $proc;
        $files = array_values($paths);

        Spec019TranslationProcess::waitForFile($paths['arrival'], 60.0, 'adoption-worker arrival', $paths['err']);
        Spec019TranslationProcess::waitForFile($paths['release'], 60.0, 'adoption-worker attempting', $paths['err']);
        expect($proc->isAlive())->toBeTrue();
        // A real child PHP process: concrete pid, never the parent pid.
        expect($proc->pid())->not->toBeNull();
        expect($proc->pid())->not->toBe(getmypid());

        // The adoption child is provably blocked on the real Tour row lock:
        // its exact backend pid waits in pg_stat_activity/pg_locks.
        $childPg = Spec019AdoptionRaceHelper::backendPid($paths['arrival']);
        $evidence = Spec019AdoptionRaceHelper::awaitPgLockWaits([$childPg], 30.0);
        expect($evidence)->not->toBe('');

        // While adoption waits, the parent populates a DISTINCT canonical
        // itinerary through the ACTUAL TourService path (still holding the
        // lock), so the child's post-release view must see populated EN.
        $populated = [
            ['day' => 7, 'title' => 'Seventh day ridge', 'description' => 'Ridge walk', 'stops' => [
                ['title' => 'Ridge gate', 'description' => null, 'duration_minutes' => 90],
            ]],
        ];
        app(TourService::class)->updateTour(Tour::findOrFail($tourId), ['itinerary' => $populated]);
        $newHash = app(TourTranslationService::class)->sourceHash(spec019AdoptEnglish($tourId)->fresh());

        DB::commit();
        $holdingOpen = false;

        Spec019TranslationProcess::waitForFile($paths['done'], 60.0, 'adoption-worker done', $paths['err']);
        expect($proc->waitExit(30.0))->toBe(0);
        expect(str_starts_with(trim((string) file_get_contents($paths['done'])), 'ok'))->toBeTrue();

        // Populated source wins: the exact new itinerary stands (no stale
        // legacy adoption over it), the raw legacy source is intact, the
        // revision hash matches the populated write, desired states still
        // point at it, and no derivatives were fabricated.
        $english = spec019AdoptEnglish($tourId);
        expect($english)->not->toBeNull();
        expect(spec019AdoptCanonical($english->itinerary))->toBe(spec019AdoptCanonical($populated));
        expect(spec019AdoptCanonical(spec019AdoptRawLegacy($tourId)))->toBe(spec019AdoptCanonical($legacy));
        expect(app(TourTranslationService::class)->sourceHash($english->fresh()))->toBe($newHash);
        foreach (['es', 'it'] as $locale) {
            $state = TourTranslationState::where('tour_id', $tourId)->where('locale', $locale)->first();
            if ($state !== null) {
                expect($state->source_hash)->toBe($newHash);
            }
        }
        expect(TourTranslation::where('tour_id', $tourId)->whereIn('locale', ['es', 'it'])->count())->toBe(0);

        // Zero adoption afterward: a repeat run changes nothing.
        expect(Artisan::call('tours:adopt-itineraries', ['--after-id' => '0', '--limit' => '100']))->toBe(0);
        expect(spec019AdoptCanonical(spec019AdoptEnglish($tourId)->itinerary))->toBe(spec019AdoptCanonical($populated));
    } finally {
        $holdError = null;
        if ($holdingOpen) {
            try {
                DB::rollBack();
            } catch (Throwable $e) {
                $holdError = $e;
            }
        }
        Spec019AdoptionRaceHelper::cleanup($procs, $files, $barrierDir, $owned, $previousDefault, $guardPassed);
        if ($holdError !== null) {
            throw $holdError;
        }
    }
});
