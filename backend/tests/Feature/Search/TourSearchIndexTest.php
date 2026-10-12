<?php

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

use Tests\Support\Spec019DispatchFailureQueue;

// makeSearchableTour() and addTranslation() are shared from tests/Pest.php.

uses(RefreshDatabase::class);

it('derives upcoming available dates from a specific_date rule', function () {
    $tour = makeSearchableTour();
    $date = Carbon::today()->addDays(5)->toDateString();

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => $date,
        'capacity' => 10,
    ]);

    // Re-load relations: Tour::create fires the Scout observer which calls
    // shouldBeSearchable()->upcomingAvailableDates(), caching an empty
    // availabilityRules collection on the in-memory instance before the rule
    // exists. A fresh load mirrors how IndexTourAction indexes the tour.
    $tour->load(['availabilityRules', 'availabilityExceptions']);

    expect($tour->upcomingAvailableDates(30, 30))->toContain($date);
});

it('excludes blocked exception dates from upcoming availability', function () {
    $tour = makeSearchableTour();
    $today = Carbon::today()->toDateString();

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);
    AvailabilityException::create([
        'tour_id' => $tour->id,
        'exception_type' => 'block',
        'date' => $today,
    ]);

    $tour->load(['availabilityRules', 'availabilityExceptions']);
    $dates = $tour->upcomingAvailableDates(3, 3);

    expect($dates)->not->toContain($today)->and($dates)->toHaveCount(2);
});

it('indexes a published tour with pricing and availability in Scout', function () {
    config(['scout.driver' => 'collection']);
    $tour = makeSearchableTour('published', 'unique-sunset-sail');
    addTranslation($tour, 'en', 'Unique Sunset Sail Journey');
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);

    // Re-load with the relations the indexer eager-loads, mirroring IndexTourAction.
    $tour = Tour::with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])->find($tour->id);

    expect($tour->shouldBeSearchable())->toBeTrue()
        ->and($tour->upcomingAvailableDates())->not->toBeEmpty();

    $tour->searchable();

    $results = Tour::search('Unique Sunset Sail Journey')->get();
    expect($results->pluck('id'))->toContain($tour->id);
});

it('does not consider a draft tour searchable even with rules and pricing', function () {
    $tour = makeSearchableTour('draft');
    addTranslation($tour, 'en', 'Draft Hidden Journey');
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);

    expect($tour->shouldBeSearchable())->toBeFalse();
});

it('spec019 defers the search projection until after the source transaction commits', function () {
    config(['scout.driver' => 'collection']);
    Spec019DispatchFailureQueue::bind(false);
    Spec019DispatchFailureQueue::clear();

    $tour = makeSearchableTour('published', 'spec019-defer-' . uniqid());
    addTranslation($tour, 'en', 'Spec019 Deferred Projection Tour');
    addAvailabilityRule($tour);
    $tour = $tour->fresh();
    expect($tour->shouldBeSearchable())->toBeTrue();
    Spec019DispatchFailureQueue::clear();

    DB::beginTransaction();
    try {
        // Model update so the Tour::saved projection event actually fires.
        $tour->update(['location' => 'Spec019 Elsewhere']);
    } finally {
        DB::rollBack();
    }

    // Desired: Tour::saved queues IndexTourAction after commit, so nothing
    // executes inside the transaction. Red while the model event dispatches
    // before commit; the spy records the real SyncQueue execution attempt.
    expect(Spec019DispatchFailureQueue::attemptsForJob(IndexTourAction::class))->toBeEmpty();

    Spec019DispatchFailureQueue::restore();
});

it('spec019 refreshes the latest English and current derivatives after a lost ready projection', function () {
    config(['scout.driver' => 'collection']);
    $tour = makeSearchableTour('published', 'spec019-lost-' . uniqid());
    addTranslation($tour, 'en', 'Spec019 Lost Projection Tour');
    addAvailabilityRule($tour);

    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hashV1 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    $tour->translationStates()->updateOrCreate(
        ['locale' => 'es'],
        ['source_hash' => $hashV1, 'translated_hash' => null, 'status' => 'pending', 'last_error_code' => null]
    );
    (new GenerateTourTranslationJob($tour->id, 'es', $hashV1))->handle($service, $translator);
    $tour = $tour->fresh();
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('ready');

    // English source moves to v2; the stored projection lags behind.
    $tour->translations()->where('locale', 'en')->update(['title' => 'Spec019 Revised Projection Tour']);
    $tour = $tour->fresh();
    $hashV2 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    expect($hashV2)->not->toBe($hashV1);
    $tour->translationStates()->where('locale', 'es')->update([
        'source_hash' => $hashV2, 'translated_hash' => $hashV1, 'status' => 'stale',
    ]);

    // A delayed v1 job loads fresh source/state and must no-op.
    (new GenerateTourTranslationJob($tour->id, 'es', $hashV1))->handle($service, $translator);
    $tour = $tour->fresh();
    expect($tour->translations()->where('locale', 'es')->firstOrFail()->title)->toBe('es: Spec019 Lost Projection Tour');
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('stale');

    // The reconciled v2 job becomes ready, then a real index refresh runs.
    (new GenerateTourTranslationJob($tour->id, 'es', $hashV2))->handle($service, $translator);
    $tour = $tour->fresh();
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('ready');
    expect($tour->translations()->where('locale', 'es')->firstOrFail()->title)->toBe('es: Spec019 Revised Projection Tour');

    (new IndexTourAction($tour->id))->handle();

    $projected = $tour->fresh()->toSearchableArray();
    expect($projected['title_en'])->toBe('Spec019 Revised Projection Tour');
    expect($projected['title_es'])->toBe('es: Spec019 Revised Projection Tour');
    // Only the current derivative is projected; missing locales fall back.
    expect($projected['title_it'])->toBe('Spec019 Revised Projection Tour');

    $results = Tour::search('Spec019 Revised Projection Tour')->get();
    expect($results->pluck('id'))->toContain($tour->id);
});

it('spec019 delayed translation jobs load fresh source and state', function () {
    config(['scout.driver' => 'collection']);
    $tour = makeSearchableTour('published', 'spec019-delayed-' . uniqid());
    addTranslation($tour, 'en', 'Spec019 Delayed Job Tour');
    addAvailabilityRule($tour);

    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hashV1 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    $tour->translationStates()->updateOrCreate(
        ['locale' => 'it'],
        ['source_hash' => $hashV1, 'translated_hash' => null, 'status' => 'pending', 'last_error_code' => null]
    );

    // Source advances before the queued job runs.
    $tour->translations()->where('locale', 'en')->update(['title' => 'Spec019 Delayed Job Revised']);
    $tour = $tour->fresh();
    $hashV2 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    expect($hashV2)->not->toBe($hashV1);

    (new GenerateTourTranslationJob($tour->id, 'it', $hashV1))->handle($service, $translator);
    $tour = $tour->fresh();
    expect($tour->translations()->where('locale', 'it')->exists())->toBeFalse();
    expect($tour->translationStates()->where('locale', 'it')->firstOrFail()->status)->toBe('pending');

    $tour->translationStates()->where('locale', 'it')->update(['source_hash' => $hashV2]);
    (new GenerateTourTranslationJob($tour->id, 'it', $hashV2))->handle($service, $translator);
    $tour = $tour->fresh();
    expect($tour->translations()->where('locale', 'it')->firstOrFail()->title)->toBe('it: Spec019 Delayed Job Revised');
    expect($tour->translationStates()->where('locale', 'it')->firstOrFail()->status)->toBe('ready');
});

it('exposes the rating contract on the tour detail endpoint', function () {
    $tour = makeSearchableTour('published', 'contracted-tour');
    addTranslation($tour, 'en', 'Contracted Tour');

    getJson('/api/public/tours/contracted-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.slug', 'contracted-tour')
        ->assertJsonStructure([
            'data' => [
                'rating' => ['average', 'count'],
                'reviews' => ['average_rating', 'count', 'distribution'],
            ],
        ]);
});

it('spec019 delayed index refresh never presents a stale derivative as current and preserves links aliases money eligibility', function () {
    config(['scout.driver' => 'collection']);
    $slug = 'spec019-stale-index-' . uniqid();
    $tour = makeSearchableTour('published', $slug);
    addTranslation($tour, 'en', 'Spec019 Stale Index Source Tour');
    addAvailabilityRule($tour);

    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hashV1 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    $tour->translationStates()->updateOrCreate(
        ['locale' => 'es'],
        ['source_hash' => $hashV1, 'translated_hash' => null, 'status' => 'pending', 'last_error_code' => null]
    );
    (new GenerateTourTranslationJob($tour->id, 'es', $hashV1))->handle($service, $translator);
    expect($tour->fresh()->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('ready');

    // English source advances; the stored derivative is genuinely stale.
    $tour->translations()->where('locale', 'en')->update(['title' => 'Spec019 Stale Index Revised Source']);
    $tour = $tour->fresh();
    $hashV2 = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    expect($hashV2)->not->toBe($hashV1);
    $tour->translationStates()->where('locale', 'es')->update([
        'source_hash' => $hashV2, 'translated_hash' => $hashV1, 'status' => 'stale',
    ]);

    // Baseline detail before the delayed/queued index refresh runs: the
    // current English source is served with a disclosed fallback.
    $before = getJson("/api/public/tours/{$slug}?locale=es")->assertOk()->json('data');
    expect($before['content_locale'])->toBe('en')
        ->and($before['title'])->toBe('Spec019 Stale Index Revised Source')
        ->and($before['translation_status'])->toBe('stale')
        ->and($before['translation_warning'])->toBe('partial_translation')
        ->and($before['languages'])->toBe($before['guide_languages'])
        ->and($before['seo']['canonical_url'])->toContain("/es/tours/{$slug}")
        ->and($before['seo']['hreflang']['en'])->toContain("/en/tours/{$slug}")
        ->and($before['seo']['hreflang']['es'])->toContain("/es/tours/{$slug}")
        ->and($before['seo']['hreflang']['it'])->toContain("/it/tours/{$slug}");
    $money = $before['pricing'];
    $availability = $before['availability'];
    expect($money['base_price']['amount'])->toBe(5000)
        ->and($money['base_price']['currency'])->toBe('EUR')
        ->and($availability['available_dates'])->not->toBeEmpty();

    // The delayed index refresh re-reads current source/state; it must not
    // promote the old derivative to current in the search projection.
    (new IndexTourAction($tour->id))->handle();

    $projected = $tour->fresh()->toSearchableArray();
    expect($projected['title_en'])->toBe('Spec019 Stale Index Revised Source')
        ->and($projected['title_es'])->toBe('Spec019 Stale Index Revised Source')
        ->and($projected['title_it'])->toBe('Spec019 Stale Index Revised Source')
        ->and($projected['slug'])->toBe($slug)
        ->and($projected['languages'])->toBe($projected['guide_languages'])
        ->and($projected['price_amount'])->toBe(5000)
        ->and($projected['price_currency'])->toBe('EUR')
        ->and($projected['available_dates'])->toBe($availability['available_dates']);

    // Detail still serves the current source with identical links, spoken
    // aliases, money and booking availability after the delayed refresh.
    $after = getJson("/api/public/tours/{$slug}?locale=es")->assertOk()->json('data');
    expect($after['title'])->toBe($before['title'])
        ->and($after['content_locale'])->toBe('en')
        ->and($after['translation_status'])->toBe('stale')
        ->and($after['translation_warning'])->toBe('partial_translation')
        ->and($after['pricing'])->toBe($money)
        ->and($after['availability'])->toBe($availability)
        ->and($after['seo'])->toBe($before['seo'])
        ->and($after['languages'])->toBe($before['languages'])
        ->and($after['guide_languages'])->toBe($before['guide_languages']);

    expect($tour->fresh()->shouldBeSearchable())->toBeTrue();

    $results = Tour::search('Spec019 Stale Index Revised Source')->get();
    expect($results->pluck('id'))->toContain($tour->id);
});

it('spec019 delayed index keeps an unbookable tour out of search while detail keeps money links and unavailable semantics', function () {
    config(['scout.driver' => 'collection']);
    $slug = 'spec019-unbookable-index-' . uniqid();
    $tour = makeSearchableTour('published', $slug);
    addTranslation($tour, 'en', 'Spec019 Unbookable Index Tour');
    // No availability rule: the tour genuinely fails the public bookability
    // invariant (published + valid pricing + upcoming availability).

    expect($tour->fresh()->shouldBeSearchable())->toBeFalse();

    $before = getJson("/api/public/tours/{$slug}?locale=en")->assertOk()->json('data');
    expect($before['content_locale'])->toBe('en')
        ->and($before['translation_status'])->toBe('source')
        ->and($before['availability']['is_unavailable'])->toBeTrue()
        ->and($before['pricing']['base_price']['amount'])->toBe(5000)
        ->and($before['pricing']['base_price']['currency'])->toBe('EUR')
        ->and($before['languages'])->toBe($before['guide_languages'])
        ->and($before['seo']['canonical_url'])->toContain("/en/tours/{$slug}")
        ->and($before['seo']['hreflang']['en'])->toContain("/en/tours/{$slug}")
        ->and($before['seo']['hreflang']['es'])->toContain("/es/tours/{$slug}")
        ->and($before['seo']['hreflang']['it'])->toContain("/it/tours/{$slug}");
    $money = $before['pricing'];
    $availability = $before['availability'];
    $seo = $before['seo'];

    // A delayed index run must no-op for the unbookable tour and must not
    // alter the served detail contract.
    (new IndexTourAction($tour->id))->handle();

    expect($tour->fresh()->shouldBeSearchable())->toBeFalse();

    $after = getJson("/api/public/tours/{$slug}?locale=en")->assertOk()->json('data');
    expect($after['title'])->toBe($before['title'])
        ->and($after['content_locale'])->toBe('en')
        ->and($after['translation_status'])->toBe('source')
        ->and($after['availability'])->toBe($availability)
        ->and($after['availability']['is_unavailable'])->toBeTrue()
        ->and($after['pricing'])->toBe($money)
        ->and($after['seo'])->toBe($seo)
        ->and($after['languages'])->toBe($before['languages'])
        ->and($after['guide_languages'])->toBe($before['guide_languages']);
});
