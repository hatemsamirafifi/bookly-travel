<?php

// Foundation (T005): canonical source-hash identity and the shared
// Tour-first transaction boundary. Real save/completion interleavings stay
// US3 acceptance; these tests pin hash semantics, lock acquisition order
// (via the query log) and fresh-source rechecks without claiming
// concurrency proof.

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\Spec019FakeTranslator;
use Tests\Support\TourContentFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $user = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
});

function spec019MakeContentTour(): Tour
{
    return Tour::create([
        'partner_id' => test()->partner->id,
        'category_id' => Category::where('slug', 'wine-food')->firstOrFail()->id,
        'slug' => 'revision-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'draft',
    ]);
}

function spec019MakeEnglish(Tour $tour, array $attributes): TourTranslation
{
    return $tour->translations()->create(array_merge(['locale' => 'en'], $attributes));
}

it('hashes fixed outer source field order regardless of input key order', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();

    $base = TourContentFixtures::englishAttributes([
        'itinerary' => [['day' => 1, 'title' => 'Old town', 'description' => null, 'stops' => []]],
    ]);

    $en = spec019MakeEnglish($tour, $base);
    $hashOrdered = $service->sourceHash($en);

    // Same values assigned in a different PHP key order must hash identically.
    $shuffled = array_reverse($base, true);
    $tour2 = spec019MakeContentTour();
    $en2 = spec019MakeEnglish($tour2, $shuffled);

    expect($service->sourceHash($en2))->toBe($hashOrdered);
});

it('ignores nested day/stop object key order but preserves array order and null/empty distinctions', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();

    $canonical = [
        'title' => 'T',
        'description' => 'D',
        'itinerary' => [
            ['day' => 1, 'title' => 'Old town', 'description' => null, 'stops' => [
                ['title' => 'Main square', 'description' => null, 'duration_minutes' => 30],
            ]],
            ['day' => 2, 'title' => 'Gallery', 'description' => 'Art', 'stops' => []],
        ],
    ];
    $en = spec019MakeEnglish($tour, $canonical);
    $hash = $service->sourceHash($en);

    // Reordered object keys (day/stop fields in different order) must NOT change the hash.
    $reordered = [
        'title' => 'T',
        'description' => 'D',
        'itinerary' => [
            ['stops' => [
                ['duration_minutes' => 30, 'description' => null, 'title' => 'Main square'],
            ], 'description' => null, 'title' => 'Old town', 'day' => 1],
            ['stops' => [], 'description' => 'Art', 'title' => 'Gallery', 'day' => 2],
        ],
    ];
    $tour2 = spec019MakeContentTour();
    $en2 = spec019MakeEnglish($tour2, $reordered);
    expect($service->sourceHash($en2))->toBe($hash);

    // Array order matters: swapped days must change the hash.
    $swapped = $canonical;
    $swapped['itinerary'] = array_reverse($swapped['itinerary']);
    $tour3 = spec019MakeContentTour();
    $en3 = spec019MakeEnglish($tour3, $swapped);
    expect($service->sourceHash($en3))->not->toBe($hash);

    // Null vs empty-array itinerary must hash differently.
    $tour4 = spec019MakeContentTour();
    $enNull = spec019MakeEnglish($tour4, ['title' => 'T', 'description' => 'D', 'itinerary' => null]);
    $tour5 = spec019MakeContentTour();
    $enEmpty = spec019MakeEnglish($tour5, ['title' => 'T', 'description' => 'D', 'itinerary' => []]);
    expect($service->sourceHash($enNull))->not->toBe($service->sourceHash($enEmpty));

    // Null vs empty-string description must hash differently.
    $tour6 = spec019MakeContentTour();
    $enNullDesc = spec019MakeEnglish($tour6, ['title' => 'T', 'description' => null]);
    $tour7 = spec019MakeContentTour();
    $enEmptyDesc = spec019MakeEnglish($tour7, ['title' => 'T', 'description' => '']);
    expect($service->sourceHash($enNullDesc))->not->toBe($service->sourceHash($enEmptyDesc));
});

it('keeps malformed persisted source explicit instead of hashing it as empty', function () {
    $service = app(TourTranslationService::class);

    $tour = spec019MakeContentTour();
    $malformed = spec019MakeEnglish($tour, ['title' => 'T', 'description' => 'D', 'itinerary' => ['Just a string']]);

    // Raw malformed structure is preserved verbatim, not rebuilt into an
    // invented titled object, and hashes distinctly from empty/null.
    expect($service->sourcePayload($malformed)['itinerary'])->toBe(['Just a string']);
    $malformedHash = $service->sourceHash($malformed);

    $tour2 = spec019MakeContentTour();
    $empty = spec019MakeEnglish($tour2, ['title' => 'T', 'description' => 'D', 'itinerary' => []]);
    expect($malformedHash)->not->toBe($service->sourceHash($empty));

    $tour3 = spec019MakeContentTour();
    $null = spec019MakeEnglish($tour3, ['title' => 'T', 'description' => 'D', 'itinerary' => null]);
    expect($malformedHash)->not->toBe($service->sourceHash($null));

    // Malformed source cannot pass provider-output validation either.
    expect(fn () => $service->applyStrings(
        $service->sourcePayload($malformed),
        ['title' => 'T es', 'description' => 'D es', 'itinerary.0' => 'Solo una cadena']
    ))->toThrow(ValidationException::class);
});

it('locks Tour then EN then states in es/it order inside the shared boundary', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();
    spec019MakeEnglish($tour, ['title' => 'T', 'description' => 'D']);

    DB::enableQueryLog();
    try {
        $service->withContentLock($tour->id, fn () => 'locked-ok');
    } finally {
        $log = DB::getQueryLog();
        DB::disableQueryLog();
    }

    $locked = array_values(array_filter(
        $log,
        static fn (array $entry): bool => str_contains(strtolower($entry['query']), 'for update'),
    ));
    $tables = array_map(
        static fn (array $entry): string => strtolower($entry['query']),
        $locked
    );

    expect($tables)->toHaveCount(4);
    expect($tables[0])->toContain('"tours"');
    expect($tables[1])->toContain('"tour_translations"');
    expect($tables[2])->toContain('"tour_translation_states"');
    expect($tables[3])->toContain('"tour_translation_states"');
    expect($locked[1]['bindings'])->toBe([$tour->id, 'en']);
    // es before it: bindings carry the locale in the same order.
    expect($locked[2]['bindings'])->toBe([$tour->id, 'es']);
    expect($locked[3]['bindings'])->toBe([$tour->id, 'it']);
});

it('rolls back partial writes when the boundary callback fails', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();
    spec019MakeEnglish($tour, ['title' => 'T', 'description' => 'D']);

    try {
        $service->withContentLock($tour->id, function () use ($tour) {
            $tour->translations()->where('locale', 'en')->update(['title' => 'Half-written']);
            throw new RuntimeException('simulated failure inside lock');
        });
        $this->fail('Expected the boundary to rethrow the callback exception.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('simulated failure inside lock');
    }

    expect($tour->fresh()->translations()->where('locale', 'en')->firstOrFail()->title)->toBe('T');
});

it('observes fresh source written inside the boundary on completion', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();
    spec019MakeEnglish($tour, ['title' => 'T', 'description' => 'D']);

    // A stale completion rechecks the source under the same lock order: an
    // EN change made before completion must be visible, so a worker holding
    // an older desired hash can detect the mismatch instead of writing.
    $fresh = $service->withContentLock($tour->id, function () use ($tour, $service) {
        $tour->translations()->where('locale', 'en')->update(['title' => 'Updated inside lock']);
        $english = $tour->translations()->where('locale', 'en')->firstOrFail();

        return $service->sourceHash($english);
    });
    $english = $tour->fresh()->translations()->where('locale', 'en')->firstOrFail();
    expect($fresh)->toBe($service->sourceHash($english));
    expect($english->title)->toBe('Updated inside lock');
});

it('reaches the disposable database on an independent connection', function () {
    $second = TourContentFixtures::secondConnection();

    // Independent PDO to the same disposable database, never dev/prod.
    expect($second->selectOne('select current_database() as db')->db)->toBe('bookly_test');

    // Automated tests must never carry live provider credentials.
    expect((string) config('services.gemini.api_key'))->toBe('');
});

it('pauses the fake provider mid-call and resumes to completed output', function () {
    $fake = new Spec019FakeTranslator;
    $observed = [];
    $fake->onArrival(function (string $locale) use (&$observed): void {
        // Pause the worker fiber inside translate(): the arrival is
        // recorded but no output exists yet until the parent resumes.
        $observed[] = $locale;
        Fiber::suspend($locale);
    });

    $fiber = new Fiber(fn (): array => $fake->translate(['title' => 'Morning tour'], 'es'));
    $suspendedLocale = $fiber->start();

    // Suspended mid-call: arrival visible, worker not terminated, no
    // output produced yet.
    expect($suspendedLocale)->toBe('es');
    expect($observed)->toBe(['es']);
    expect($fake->arrivedCount('es'))->toBe(1);
    expect($fiber->isTerminated())->toBeFalse();

    // Release: the worker completes deterministically with no network.
    $fiber->resume();
    expect($fiber->isTerminated())->toBeTrue();
    expect($fiber->getReturn())->toBe(['title' => 'Morning tour (es)']);
});

it('holds real Tour/EN/state row locks across separate connections until release', function () {
    // Pest wraps this test in a RefreshDatabase transaction on the default
    // connection, which a second connection can never see. The owner below
    // therefore works on its own named default connection whose fixtures
    // commit for real; everything is cleaned and the default restored in
    // the finally block. This proves conflicting lock acquisition on real
    // rows, not actual concurrent writes or job interleavings — those
    // remain US3 acceptance.
    $previousDefault = config('database.default');
    config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('spec019_owner');

    $slug = 'spec019-lock-' . uniqid();
    try {
        $category = Category::create(['slug' => $slug, 'name' => 'Lock proof']);
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => 'complete',
            'is_active' => true,
        ]);
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => $slug,
            'location' => 'Florence, Italy',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 7500,
            'status' => 'draft',
        ]);
        $service = app(TourTranslationService::class);
        $tour->translations()->create(['locale' => 'en'] + TourContentFixtures::englishAttributes());
        $hash = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
        foreach (['es', 'it'] as $locale) {
            $tour->translationStates()->create([
                'locale' => $locale, 'source_hash' => $hash, 'status' => 'pending',
            ]);
        }
        $tourId = $tour->id;
        $contender = TourContentFixtures::secondConnection();

        $tryLockRow = function (string $table, string $where, array $bindings) use ($contender): bool {
            try {
                $contender->selectOne("select id from {$table} where {$where} for update nowait", $bindings);

                return true;
            } catch (QueryException $e) {
                expect($e->getPrevious()?->getCode())->toBe('55P03');

                return false;
            }
        };

        $held = $service->withContentLock($tourId, function () use ($tourId, $tryLockRow) {
            // While the boundary holds its locks, a separate connection
            // cannot lock any of the same rows, even without waiting.
            expect($tryLockRow('tours', 'id = ?', [$tourId]))->toBeFalse();
            expect($tryLockRow('tour_translations', 'tour_id = ? and locale = ?', [$tourId, 'en']))->toBeFalse();
            expect($tryLockRow('tour_translation_states', 'tour_id = ? and locale = ?', [$tourId, 'es']))->toBeFalse();
            expect($tryLockRow('tour_translation_states', 'tour_id = ? and locale = ?', [$tourId, 'it']))->toBeFalse();

            return 'held';
        });
        expect($held)->toBe('held');

        // After release the contender locks every row cleanly.
        expect($tryLockRow('tours', 'id = ?', [$tourId]))->toBeTrue();
        expect($tryLockRow('tour_translations', 'tour_id = ? and locale = ?', [$tourId, 'en']))->toBeTrue();
        expect($tryLockRow('tour_translation_states', 'tour_id = ? and locale = ?', [$tourId, 'es']))->toBeTrue();
        expect($tryLockRow('tour_translation_states', 'tour_id = ? and locale = ?', [$tourId, 'it']))->toBeTrue();
    } finally {
        if (isset($tour)) {
            $tour->translationStates()->delete();
            $tour->translations()->delete();
            $tour->delete();
        }
        if (isset($partner)) {
            $partner->delete();
        }
        if (isset($user)) {
            $user->delete();
        }
        if (isset($category)) {
            $category->delete();
        }
        DB::setDefaultConnection($previousDefault);
        DB::purge('spec019_owner');
        DB::purge('spec019_second');
    }
});

it('guarantees partner record IDs distinct from user IDs for owner separation', function () {
    [$first, $second] = TourContentFixtures::partnerPairWithDistinctIds();

    $userIds = [$first->user_id, $second->user_id];
    expect($first->id)->not->toBeIn($userIds);
    expect($second->id)->not->toBeIn($userIds);
    expect($first->id)->not->toBe($second->id);
    expect($first->user->id)->toBe($first->user_id);
    expect($second->user->id)->toBe($second->user_id);
});

it('coordinates fake provider arrivals without network calls', function () {
    $fake = new Spec019FakeTranslator;
    $out = $fake->translate(['title' => 'Morning tour', 'itinerary.0.title' => 'Old town'], 'es');

    expect($out)->toBe(['title' => 'Morning tour (es)', 'itinerary.0.title' => 'Old town (es)']);
    expect($fake->arrivedCount())->toBe(1);
    expect($fake->arrivedCount('es'))->toBe(1);
    expect($fake->arrivedCount('it'))->toBe(0);
});

it('reports current/ready only for matching translated hash via publicStatus', function () {
    $service = app(TourTranslationService::class);
    $tour = spec019MakeContentTour();
    $en = spec019MakeEnglish($tour, ['title' => 'T', 'description' => 'D']);
    $hash = $service->sourceHash($en);

    expect($service->publicStatus($tour, 'en'))->toBe('source');

    // No state row yet -> pending (missing derivative is not ready).
    expect($service->publicStatus($tour->fresh(), 'es'))->toBe('pending');

    // Ready with matching hash and a stored derivative -> current.
    $tour->translationStates()->create([
        'locale' => 'es', 'source_hash' => $hash, 'translated_hash' => $hash, 'status' => 'ready',
    ]);
    $tour->translations()->create(['locale' => 'es', 'title' => 'T es', 'description' => 'D es']);
    expect($service->publicStatus($tour->fresh(), 'es'))->toBe('ready');

    // Apparent ready with mismatched hash -> stale, never current.
    $tour->translationStates()->where('locale', 'es')->update(['translated_hash' => str_repeat('0', 64)]);
    $fresh = $tour->fresh();
    expect($service->publicStatus($fresh, 'es'))->toBe('stale');
    expect($service->currentTranslation($fresh, 'es'))->toBeNull();
});
