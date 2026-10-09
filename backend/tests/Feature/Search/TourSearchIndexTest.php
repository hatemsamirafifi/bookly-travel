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
