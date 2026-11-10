<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Payment\Services\StripeService;
use App\Domains\Translation\Contracts\ContentTranslatorContract;
use App\Domains\Translation\Jobs\TranslateContentFieldJob;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Domains\Translation\Services\TranslationQualityValidator;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
use App\Models\TourOptionTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Tests\Support\Spec019FakeTranslator;

uses(RefreshDatabase::class);

if (! function_exists('makeOptionsPartner')) {
    function makeOptionsPartner(): array
    {
        $partnerUser = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);

        return [
            $partner,
            $partnerUser->createToken('test', ['partner'])->plainTextToken,
        ];
    }
}

if (! function_exists('optionsHeaders')) {
    function optionsHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}

if (! function_exists('optionsAvailabilityRule')) {
    function optionsAvailabilityRule(Tour $tour): AvailabilityRule
    {
        return AvailabilityRule::create([
            'tour_id' => $tour->id,
            'rule_type' => 'recurring',
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'start_time' => '09:00:00',
            'start_date' => null,
            'end_date' => null,
            'capacity' => $tour->group_size_max ?? 10,
        ]);
    }
}

if (! function_exists('optionsPublishTour')) {
    function optionsPublishTour(Tour $tour, User $admin): Tour
    {
        optionsAvailabilityRule($tour);

        // Draft → pending_review → published through the guarded submit gate
        // (source title/description + pricing + cover are already present).
        app(TourService::class)->submitForReview($tour->fresh());
        $tour = app(ApproveTourAction::class)->execute($admin, $tour->fresh());

        return $tour->fresh();
    }
}

if (! function_exists('runOptionTranslations')) {
    /**
     * Drain the generation queue for a tour through the deterministic fake
     * provider (no network): runs every queued GenerateTourTranslationJob for
     * the given locales and clears the queue between tests.
     */
    function runOptionTranslations(Tour $tour, Spec019FakeTranslator $translator, array $locales): void
    {
        foreach ($locales as $locale) {
            Queue::fake();
            $state = TourTranslationState::query()
                ->where('tour_id', $tour->id)
                ->where('locale', $locale)
                ->firstOrFail();
            (new GenerateTourTranslationJob($tour->id, $locale, $state->source_hash))
                ->handle(app(TourTranslationService::class), $translator);
        }
        Queue::fake();
    }
}

beforeEach(function () {
    Queue::fake();

    Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);

    [$this->partner, $this->token] = makeOptionsPartner();
    $this->admin = User::factory()->admin()->create();

    $common = [
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'cover_image_url' => 'https://cdn.bookly.com/tours/options/cover.jpg',
        'pricing_tiers' => [['name' => 'Standard', 'price' => 89.0]],
    ];
    $this->esTourPayload = array_merge($common, [
        'source_locale' => 'es',
        'title' => 'Cata de vinos en la Toscana',
        'description' => str_repeat('Recorre los viñedos de la Toscana y prueba sus vinos. ', 4),
        'options' => [
            [
                'title' => 'Mañana en grupo pequeño',
                'pickup_instructions' => 'Punto de recogida en la Piazza della Signoria a las 08:40.',
                'guide_languages' => ['ar', 'ES'],
                'price_amount' => 5900,
                'group_size_max' => 6,
                'start_time' => '9:00',
                'weekdays' => null,
            ],
            [
                'title' => 'Atardecer con sommelier',
                'pickup_instructions' => null,
                'guide_languages' => ['it'],
                'price_amount' => 7900,
                'group_size_max' => null,
                'start_time' => '14:00',
                'weekdays' => [1, 4],
            ],
        ],
    ]);
});

it('creates a Spanish-authored tour with options and returns them on owned detail in authored order', function () {
    $payload = $this->esTourPayload;
    $payload['options'][0]['weekdays'] = [1, 1];
    $payload['options'][1]['guide_languages'][] = 'ar';

    $created = postJson('/api/partner/tours', $payload, optionsHeaders($this->token))
        ->assertCreated()
        ->assertJsonPath('data.source_locale', 'es');

    $tourId = $created->json('data.id');
    $tour = Tour::findOrFail($tourId);

    // Structured normalization: start_time H:mm → H:i:s, guide codes lowercased.
    $options = $tour->options()->orderBy('sort_order')->get();
    expect($created->json('data.options.0.id'))->toBe($options[0]->id)
        ->and($created->json('data.options.1.id'))->toBe($options[1]->id);
    expect($options)->toHaveCount(2)
        ->and($options[0]->title)->toBe('Mañana en grupo pequeño')
        ->and($options[0]->startTimeString())->toBe('09:00:00')
        ->and($options[0]->guideLanguages())->toBe(['ar', 'es'])
        ->and($options[0]->price_amount)->toBe(5900)
        ->and($options[0]->isActive())->toBeTrue()
        ->and($options[0]->weekdays)->toBe([1])
        ->and($options[1]->guideLanguages())->toBe(['it', 'ar'])
        ->and($options[1]->weekdays)->toBe([1, 4])
        ->and($options[1]->pickup_instructions)->toBeNull();

    // Own-detail exposes exactly the authored list shape with stable ids.
    $detail = getJson("/api/partner/tours/{$tourId}", optionsHeaders($this->token))->assertOk();
    $returned = $detail->json('data.options');
    expect($returned)->toHaveCount(2)
        ->and($returned[0]['id'])->toBe($options[0]->id)
        ->and($returned[0]['title'])->toBe('Mañana en grupo pequeño')
        ->and($returned[0]['guide_languages'])->toBe(['ar', 'es'])
        ->and($returned[0]['price_amount'])->toBe(5900)
        ->and($returned[1]['id'])->toBe($options[1]->id);

    // Option payloads pass through partner drafts opaquely.
    postJson("/api/partner/tours/{$tourId}/drafts/save", [
        'payload' => ['options' => [['title' => 'Borrador de opción', 'price_amount' => 4200]]],
    ], optionsHeaders($this->token))->assertOk();

    $draft = getJson("/api/partner/tours/{$tourId}/drafts/latest", optionsHeaders($this->token))->assertOk();
    expect($draft->json('payload.options.0.title'))->toBe('Borrador de opción');
});

it('validates duplicate ids, foreign ids, create-time ids and field bounds', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $otherTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $tourId > 0 ? Tour::find($tourId)->category_id : null,
        'slug' => 'options-scope-other-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'price_amount' => 5000,
        'status' => 'draft',
    ]);
    $foreign = TourOption::create([
        'tour_id' => $otherTour->id,
        'title' => 'Foreign option',
        'guide_languages' => ['en'],
        'price_amount' => 1000,
    ]);

    $headers = optionsHeaders($this->token);
    $ids = Tour::findOrFail($tourId)->options()->pluck('id')->all();

    // Duplicate ids.
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $ids[0], 'title' => 'A', 'guide_languages' => ['en'], 'price_amount' => 1000],
            ['id' => $ids[0], 'title' => 'B', 'guide_languages' => ['en'], 'price_amount' => 1000],
        ],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.1.id']);

    // Foreign (other-partner-tour) option id is scoped out.
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $foreign->id, 'title' => 'A', 'guide_languages' => ['en'], 'price_amount' => 1000],
        ],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.0.id']);

    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => [$ids[0]], 'title' => 'A', 'guide_languages' => ['en'], 'price_amount' => 1000],
        ],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.0.id']);

    // Create must never carry authored ids.
    postJson('/api/partner/tours', array_merge($this->esTourPayload, [
        'options' => [
            ['id' => $ids[0], 'title' => 'A', 'guide_languages' => ['en'], 'price_amount' => 1000],
        ],
    ]), $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.0.id']);

    // Field bounds: price must be positive, weekdays 0-6, title nonblank.
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [['title' => 'X', 'guide_languages' => ['en'], 'price_amount' => 0]],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.0.price_amount']);

    putJson("/api/partner/tours/{$tourId}", [
        'options' => [['title' => 'X', 'guide_languages' => ['en'], 'price_amount' => 100, 'weekdays' => [1, 7]]],
    ], $headers)->assertUnprocessable();

    putJson("/api/partner/tours/{$tourId}", [
        'options' => [['title' => '  ', 'guide_languages' => ['en'], 'price_amount' => 100]],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['options.0.title']);
});

it('retires omitted options without deleting rows, retires all on an empty list, and re-listing reactivates', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $options = Tour::findOrFail($tourId)->options()->orderBy('sort_order')->get();

    // Drop the second option from the list: it retires (no hard-delete).
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $options[0]->id, 'title' => 'Mañana actualizada', 'guide_languages' => ['en'], 'price_amount' => 6100],
        ],
    ], optionsHeaders($this->token))->assertOk();

    $after = TourOption::query()->where('tour_id', $tourId)->orderBy('id')->get();
    expect($after)->toHaveCount(2)
        ->and($after->firstWhere('id', $options[0]->id)->isActive())->toBeTrue()
        ->and($after->firstWhere('id', $options[0]->id)->title)->toBe('Mañana actualizada')
        ->and($after->firstWhere('id', $options[1]->id)->status)->toBe(TourOption::STATUS_RETIRED)
        ->and($after->firstWhere('id', $options[1]->id)->retired_at)->not->toBeNull();

    // Provided list only with active rows means unchanged set — omitted list
    // preserves, so a details-only update keeps options untouched.
    putJson("/api/partner/tours/{$tourId}", ['destination' => 'Siena', 'options' => []], optionsHeaders($this->token))->assertOk();
    expect(TourOption::query()->where('tour_id', $tourId)->where('status', TourOption::STATUS_ACTIVE)->count())->toBe(0);

    // Re-listing the retired id re-activates the original row identity.
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $options[1]->id, 'title' => 'Atardecer de nuevo', 'guide_languages' => ['it'], 'price_amount' => 7500],
        ],
    ], optionsHeaders($this->token))->assertOk();

    $reactivated = TourOption::query()->find($options[1]->id);
    expect($reactivated->isActive())->toBeTrue()
        ->and($reactivated->title)->toBe('Atardecer de nuevo');

    // The first option was omitted this time → retired, still rows only.
    expect(TourOption::query()->where('tour_id', $tourId)->count())->toBe(2)
        ->and(TourOption::query()->find($options[0]->id)->isActive())->toBeFalse();
});

it('does not invalidate translation on structured-only option changes', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);
    $hashBefore = TourTranslationState::query()->where('tour_id', $tourId)->where('locale', 'en')->firstOrFail()->source_hash;
    Queue::fake();

    // Price, capacity, start time, weekdays and guide codes are structured:
    // same authored texts, same order, same active flags → same hash.
    $existing = $tour->options()->orderBy('sort_order')->get();
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $existing[0]->id, 'title' => 'Mañana en grupo pequeño', 'pickup_instructions' => 'Punto de recogida en la Piazza della Signoria a las 08:40.', 'guide_languages' => ['en', 'it'], 'price_amount' => 6400, 'group_size_max' => 8, 'start_time' => '10:30', 'weekdays' => [2, 3]],
            ['id' => $existing[1]->id, 'title' => 'Atardecer con sommelier', 'pickup_instructions' => null, 'guide_languages' => ['ar'], 'price_amount' => 8100, 'group_size_max' => null, 'start_time' => '15:00', 'weekdays' => [5]],
        ],
    ], optionsHeaders($this->token))->assertOk();

    Queue::assertNotPushed(GenerateTourTranslationJob::class);
    expect(TourTranslationState::query()->where('tour_id', $tourId)->where('locale', 'en')->firstOrFail()->source_hash)
        ->toBe($hashBefore)
        ->and(TourOption::find($existing[0]->id)->price_amount)->toBe(6400);
});

it('regenerates en/it derivatives through the fake provider when option texts or order change', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);

    $fake = new Spec019FakeTranslator;
    runOptionTranslations($tour, $fake, ['en', 'it']);

    // Derivative texts are keyed by the stable option ids.
    $ids = $tour->options()->orderBy('sort_order')->pluck('id');
    $en = TourOptionTranslation::query()->where('locale', 'en')->whereIn('tour_option_id', $ids)->get()->keyBy('tour_option_id');
    $it = TourOptionTranslation::query()->where('locale', 'it')->whereIn('tour_option_id', $ids)->get()->keyBy('tour_option_id');
    expect($en)->toHaveCount(2)
        ->and($en[$ids[0]]->title)->toBe('Mañana en grupo pequeño (en)')
        ->and($en[$ids[0]]->pickup_instructions)->toBe('Punto de recogida en la Piazza della Signoria a las 08:40. (en)')
        ->and($en[$ids[1]]->pickup_instructions)->toBeNull()
        ->and($it[$ids[1]]->title)->toBe('Atardecer con sommelier (it)');

    // Capture the current (pre-change) revision hash for the assertion below.
    $beforeChangeHash = TourTranslationState::query()->where('tour_id', $tourId)->where('locale', 'en')->firstOrFail()->source_hash;

    // Option-only TEXT change: the source hash moves and both locales requeue
    // (all authored texts belong to the same revision identity).
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $ids[0], 'title' => 'Mañana renombrada', 'pickup_instructions' => 'Nueva recogida.', 'guide_languages' => ['en'], 'price_amount' => 5900],
        ],
    ], optionsHeaders($this->token))->assertOk();

    Queue::assertPushed(GenerateTourTranslationJob::class, 2);

    // Hash actually moved (option texts participate in the revision identity).
    $afterChangeHash = TourTranslationState::query()->where('tour_id', $tourId)->where('locale', 'en')->firstOrFail()->source_hash;
    expect($afterChangeHash)->not->toBe($beforeChangeHash);

    runOptionTranslations(Tour::findOrFail($tourId), $fake, ['en', 'it']);

    // The regenerated texts are keyed by the same stable ids in both locales.
    $enAfter = TourOptionTranslation::query()->where('tour_option_id', $ids[0])->where('locale', 'en')->firstOrFail();
    expect($enAfter->title)->toBe('Mañana renombrada (en)')
        ->and($enAfter->pickup_instructions)->toBe('Nueva recogida. (en)');

    Queue::fake();

    // Reordering the same active set also invalidates the revision: authored
    // order is part of the hash, so a reorder requeues generation.
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $ids[1], 'title' => 'Atardecer con sommelier', 'pickup_instructions' => null, 'guide_languages' => ['it'], 'price_amount' => 7900, 'start_time' => '14:00', 'weekdays' => [1, 4]],
            ['id' => $ids[0], 'title' => 'Mañana renombrada', 'pickup_instructions' => 'Nueva recogida.', 'guide_languages' => ['en'], 'price_amount' => 5900],
        ],
    ], optionsHeaders($this->token))->assertOk();

    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
    expect(Tour::findOrFail($tourId)->options()->orderBy('sort_order')->pluck('id')->all())->toBe([$ids[1], $ids[0]]);

    runOptionTranslations(Tour::findOrFail($tourId), $fake, ['en', 'it']);
    // Same stable-id keys survive the reorder; per-option texts are unchanged.
    expect(TourOptionTranslation::query()->where('tour_option_id', $ids[1])->where('locale', 'en')->firstOrFail()->title)
        ->toBe('Atardecer con sommelier (en)');
});

it('falls back to authored source option texts while the derivative is stale', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);
    optionsPublishTour($tour, $this->admin);
    $tour = $tour->fresh();
    $sourceTitle = 'Mañana en grupo pequeño';

    $fake = new Spec019FakeTranslator;
    runOptionTranslations($tour, $fake, ['en']);

    $detail = getJson('/api/public/tours/' . $tour->slug . '?locale=en')->assertOk();
    expect($detail->json('data.options.0.title'))->toBe("{$sourceTitle} (en)")
        ->and($detail->json('data.options.0.content_locale'))->toBe('en')
        ->and($detail->json('data.translation_warning'))->toBeNull();

    // Option-only text change → stale revision: the served option text falls
    // back to the authored source with its truthful locale, disclosed as a
    // partial translation like the tour-level text.
    Queue::fake();
    $ids = $tour->options()->orderBy('sort_order')->pluck('id');
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $ids[0], 'title' => 'Mañana reeditada', 'pickup_instructions' => 'Recogida nueva.', 'guide_languages' => ['en'], 'price_amount' => 5900],
        ],
    ], optionsHeaders($this->token))->assertOk();
    Queue::fake();

    $stale = getJson('/api/public/tours/' . $tour->slug . '?locale=en')->assertOk();
    expect($stale->json('data.options.0.title'))->toBe('Mañana reeditada')
        ->and($stale->json('data.options.0.content_locale'))->toBe('es')
        ->and($stale->json('data.translation_warning'))->toBe('partial_translation');
});

it('serves translated option texts with live structured values and per-option availability', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);
    optionsPublishTour($tour, $this->admin);
    $tour = $tour->fresh();

    $fake = new Spec019FakeTranslator;
    runOptionTranslations($tour, $fake, ['en']);

    $detail = getJson('/api/public/tours/' . $tour->slug . '?locale=en')->assertOk();
    $options = $detail->json('data.options');
    expect($options)->toHaveCount(2)
        ->and($options[0]['pricing']['base_price']['amount'])->toBe(5900)
        ->and($options[0]['pricing']['tiered_pricing'])->toBeNull()
        ->and($options[0]['group_size']['min'])->toBe(1)
        ->and($options[0]['group_size']['max'])->toBe(6)
        ->and($options[0]['start_time'])->toBe('09:00:00')
        ->and($options[1]['guide_languages'])->toBe(['it']);

    // The Monday/Thursday-only option intersects the tour's operating days.
    expect($options[1]['availability']['available_dates'])->toBeArray()
        ->each(fn ($date, $key) => $date->toBeString());

    $dates = $options[1]['availability']['available_dates'];
    foreach ($dates as $date) {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek;
        expect(in_array($dayOfWeek, [1, 4], true))->toBeTrue();
    }

    // Structured-only price change keeps the current derivative texts and
    // updates the live structured values on the same served options.
    Queue::fake();
    $all = $tour->options()->orderBy('sort_order')->get();
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $all[0]->id, 'title' => 'Mañana en grupo pequeño', 'pickup_instructions' => 'Punto de recogida en la Piazza della Signoria a las 08:40.', 'guide_languages' => ['ar', 'es'], 'price_amount' => 6100],
            ['id' => $all[1]->id, 'title' => 'Atardecer con sommelier', 'pickup_instructions' => null, 'guide_languages' => ['it'], 'price_amount' => 7900, 'start_time' => '14:00', 'weekdays' => [1, 4]],
        ],
    ], optionsHeaders($this->token))->assertOk();

    $updated = getJson('/api/public/tours/' . $tour->slug . '?locale=en')->assertOk();
    expect($updated->json('data.options'))->toHaveCount(2)
        ->and($updated->json('data.options.0.title'))->toBe('Mañana en grupo pequeño (en)')
        ->and($updated->json('data.options.0.pricing.base_price.amount'))->toBe(6100);
});

it('retiring every option makes public detail and bookability accurately unavailable', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);
    $tour = optionsPublishTour($tour, $this->admin);

    $detailBefore = getJson('/api/public/tours/' . $tour->slug . '?locale=en')->assertOk();
    expect($detailBefore->json('data.options'))->toHaveCount(2)
        ->and($detailBefore->json('data.availability.is_unavailable'))->toBeFalse();

    $first = $tour->options()->orderBy('sort_order')->firstOrFail();
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $first->id, 'title' => 'Mañana en grupo pequeño', 'pickup_instructions' => 'P.', 'guide_languages' => ['en'], 'price_amount' => 5900],
        ],
    ], optionsHeaders($this->token))->assertOk();
    expect(TourOption::query()->where('tour_id', $tourId)->where('status', TourOption::STATUS_ACTIVE)->count())->toBe(1);

    // Retire everything: an explicitly empty list retires all remaining rows
    // (never deletes) and the tour has nothing left to sell on any date.
    putJson("/api/partner/tours/{$tourId}", ['options' => []], optionsHeaders($this->token))->assertOk();
    expect(TourOption::query()->where('tour_id', $tourId)->count())->toBe(2)
        ->and(TourOption::query()->where('tour_id', $tourId)->where('status', TourOption::STATUS_ACTIVE)->count())->toBe(0);

    // All retired now: the served option list collapses and availability is
    // accurately unavailable.
    $noSale = getJson('/api/public/tours/' . $tour->fresh()->slug . '?locale=en')->assertOk();
    expect($noSale->json('data.options'))->toHaveCount(0)
        ->and($noSale->json('data.availability.is_unavailable'))->toBeTrue()
        ->and($noSale->json('data.availability.available_dates'))->toBe([])
        ->and($noSale->json('data.availability.next_available_date'))->toBeNull();

    expect($tour->fresh()->isPubliclyBookable())->toBeFalse()
        ->and($tour->fresh()->shouldBeSearchable())->toBeFalse();

    // Legacy tours that never configured options keep the existing behavior.
    // (fresh() refreshes the scout-created observer's cached empty relations.)
    $legacy = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => Tour::find($tourId)->category_id,
        'slug' => 'options-legacy-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    addTranslation($legacy, 'en', 'Legacy tour');
    optionsAvailabilityRule($legacy);
    $legacy = $legacy->fresh();
    expect($legacy->isPubliclyBookable())->toBeTrue()
        ->and(getJson('/api/public/tours/' . $legacy->slug . '?locale=en')->json('data.options'))->toBe([]);
});

it('captures the currently served option text and its locale on option bookings', function () {
    Carbon::setTestNow('2026-06-01 00:00:00');

    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);
    $tour = optionsPublishTour($tour, $this->admin);
    $tour = $tour->fresh();

    $fake = new Spec019FakeTranslator;
    runOptionTranslations($tour, $fake, ['en']);

    $option = $tour->options()->orderBy('sort_order')->firstOrFail();

    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->andReturnUsing(fn () => 'pi_' . Str::random(12) . '_secret_' . Str::random(12));

    $traveler = User::factory()->traveler()->create();

    // Requested booking locale en, derivative current → derivative snapshot.
    $created = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
        'tour_option_id' => $option->id,
    ], [
        'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->assertStatus(201);

    expect($created->json('data.option'))->not->toBeNull()
        ->and($created->json('data.option.id'))->toBe($option->id)
        ->and($created->json('data.option.title'))->toBe('Mañana en grupo pequeño (en)')
        ->and($created->json('data.option.pickup_instructions'))->toBe('Punto de recogida en la Piazza della Signoria a las 08:40. (en)')
        ->and($created->json('data.option.content_locale'))->toBe('en');

    assertDatabaseHas('bookings', [
        'tour_option_id' => $option->id,
        'tour_option_title' => 'Mañana en grupo pequeño (en)',
        'tour_option_content_locale' => 'en',
        'tour_option_pickup_instructions' => 'Punto de recogida en la Piazza della Signoria a las 08:40. (en)',
    ]);

    // Partner and traveler detail resources expose the same snapshot.
    $partnerDetail = getJson('/api/partner/bookings/' . $created->json('data.reference'), optionsHeaders($this->token))
        ->assertOk();
    expect($partnerDetail->json('data.option.id'))->toBe($option->id)
        ->and($partnerDetail->json('data.option.title'))->toBe('Mañana en grupo pequeño (en)')
        ->and($partnerDetail->json('data.option.content_locale'))->toBe('en')
        // Tour title follows the booking locale's stored derivative when the
        // row exists (the tour-level chain), the snapshot text is option-level.
        ->and($partnerDetail->json('data.tour.title'))->toBe('Cata de vinos en la Toscana (en)');

    $travelerDetail = getJson('/api/public/traveler/bookings/' . $created->json('data.reference'), [
        'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
    ])->assertOk();
    expect($travelerDetail->json('data.option.id'))->toBe($option->id)
        ->and($travelerDetail->json('data.option.pickup_instructions'))->toBe('Punto de recogida en la Piazza della Signoria a las 08:40. (en)');

    // Stale revision (option-only text change) → next booking snapshots the
    // authored source text under its truthful locale.
    Queue::fake();
    putJson("/api/partner/tours/{$tourId}", [
        'options' => [
            ['id' => $option->id, 'title' => 'Mañana reeditada', 'pickup_instructions' => 'Recogida renovada.', 'guide_languages' => ['en'], 'price_amount' => 5900],
        ],
    ], optionsHeaders($this->token))->assertOk();

    $staleBooking = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => '2026-06-16',
        'participant_count' => 1,
        'locale' => 'en',
        'tour_option_id' => $option->id,
    ], [
        'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->assertStatus(201);

    expect($staleBooking->json('data.option.title'))->toBe('Mañana reeditada')
        ->and($staleBooking->json('data.option.pickup_instructions'))->toBe('Recogida renovada.')
        ->and($staleBooking->json('data.option.content_locale'))->toBe('es');

    Carbon::setTestNow();
});

it('publishes a Spanish-authored tour end to end and keeps source immutable', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);

    $published = optionsPublishTour($tour, $this->admin);
    expect($published->status)->toBe('published')
        ->and($published->sourceLocale())->toBe('es');

    $headers = optionsHeaders($this->token);

    // Source language is immutable, everywhere.
    putJson("/api/partner/tours/{$tourId}", ['source_locale' => 'it'], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors(['source_locale']);
    putJson("/api/partner/tours/{$tourId}", [
        'translations' => ['en' => ['title' => 'Overwrite from field engine']],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['translations.en']);

    // Retries and legacy reads stay source-bound: the source row is es and the
    // submit gate keeps speaking the source language, not English.
    expect($tour->fresh()->translations()->where('locale', 'es')->exists())->toBeTrue()
        ->and($tour->fresh()->translations()->where('locale', 'en')->exists())->toBeFalse();
});

it('does not dispatch the English field engine for a non-English source tour', function () {
    $tourId = postJson('/api/partner/tours', $this->esTourPayload, optionsHeaders($this->token))
        ->assertCreated()->json('data.id');
    $tour = Tour::findOrFail($tourId);

    // Filament/admin field engine save path for the es-source tour: the
    // orchestration must skip dispatch entirely (the tour-generation pipeline
    // owns derivatives from the authored source).
    $dispatched = app(AutoTranslationService::class)
        ->syncAndDispatch($tour->fresh('translations'));
    expect($dispatched)->toBe([]);

    // An already-queued legacy field job (old generic engine) must fail safe:
    // no translation row for the source locale, no fabricated ready state.
    $fieldState = TranslationFieldState::query()->create([
        'translatable_type' => 'tour',
        'translatable_id' => $tour->id,
        'locale' => 'es',
        'field_name' => 'title',
        'origin' => TranslationFieldState::ORIGIN_AUTO,
        'status' => TranslationFieldState::STATUS_QUEUED,
    ]);
    (new TranslateContentFieldJob('tour', $tour->id, 'es', 'title'))
        ->handle(
            app(ContentTranslatorContract::class),
            app(TranslationQualityValidator::class),
            app(AutoTranslationService::class),
        );

    expect($fieldState->fresh()->status)->toBe(TranslationFieldState::STATUS_FAILED)
        ->and($fieldState->fresh()->last_error_code)->toBe('TOUR_SOURCE_NOT_ENGLISH')
        ->and($tour->translations()->where('locale', 'es')->firstOrFail()->title)->toBe('Cata de vinos en la Toscana')
        ->and(TourTranslationState::query()->where('tour_id', $tour->id)->where('locale', 'es')->exists())->toBeFalse();
});
