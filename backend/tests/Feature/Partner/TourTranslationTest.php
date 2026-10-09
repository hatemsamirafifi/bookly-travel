<?php

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\GeminiTourTranslator;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Partner\Services\TransientTranslationException;
use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Actions\RemoveFromIndexAction;
use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spec019CommittedFixtures;
use Tests\Support\Spec019DispatchFailureQueue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // RefreshDatabase simulates commits above its wrapping transaction.
    // A separate connection and production transaction manager verify real
    // outermost commits. Scoped cleanup restores the original test manager.
    $this->spec019PreviousDefault = config('database.default');
    $this->spec019PreviousManager = app('db.transactions');
    $this->spec019GuardPassed = false;
    $this->spec019Fixture = ['tables' => []];
    Spec019CommittedFixtures::boot();
    app()->instance('db.transactions', new DatabaseTransactionsManager);
    config()->set('database.default', Spec019CommittedFixtures::CONNECTION);
    Spec019CommittedFixtures::assertPrerequisites();
    $this->spec019GuardPassed = true;

    $owned = Spec019CommittedFixtures::createOwner('suite-' . bin2hex(random_bytes(4)), $this->spec019Fixture);
    $this->spec019Fixture['tables'] = $owned['tables'];
    $this->categorySlug = $owned['categorySlug'];
    $this->token = $owned['token'];
    $this->partnerId = Partner::where(
        'user_id',
        $this->spec019Fixture['tables'][(new User)->getTable()][0]
    )->value('id');

    $wineFood = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    if ($wineFood->wasRecentlyCreated) {
        $this->spec019Fixture['tables'][$wineFood->getTable()][] = $wineFood->getKey();
    }
});

afterEach(function () {
    try {
        if ($this->spec019GuardPassed) {
            Spec019CommittedFixtures::deleteOwnerScope($this->spec019Fixture);
        }
    } finally {
        config()->set('database.default', $this->spec019PreviousDefault ?? 'pgsql');
        app()->instance('db.transactions', $this->spec019PreviousManager);
        Spec019DispatchFailureQueue::restore();
        DB::purge(Spec019CommittedFixtures::CONNECTION);
    }
});

function createEnglishSourceTour(): Tour
{
    $response = test()->postJson('/api/partner/tours', [
        'title' => 'Tuscan Wine Experience',
        'description' => str_repeat('Explore the Tuscan vineyards and local wines. ', 3),
        'category' => test()->categorySlug,
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'guide_languages' => ['de', 'en', 'es'],
    ], ['Authorization' => 'Bearer ' . test()->token]);
    $response->assertCreated();

    return Tour::findOrFail($response->json('data.id'));
}

it('queues Spanish and Italian from English without treating guide codes as content locales', function () {
    $tour = createEnglishSourceTour();

    expect($tour->translations()->pluck('locale')->all())->toBe(['en']);
    expect($tour->guideLanguages())->toBe(['de', 'en', 'es']);
    expect($tour->translationStates()->orderBy('locale')->pluck('status', 'locale')->all())
        ->toBe(['es' => 'pending', 'it' => 'pending']);
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
});

it('marks derived translations stale only when English source changes', function () {
    $tour = createEnglishSourceTour();
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    Queue::fake();

    $this->putJson("/api/partner/tours/{$tour->id}", ['location' => 'Siena'], $headers)->assertOk();
    Queue::assertNotPushed(GenerateTourTranslationJob::class);

    $this->putJson("/api/partner/tours/{$tour->id}", ['title' => 'New English tour title'], $headers)->assertOk();
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
    expect($tour->fresh()->translationStates()->orderBy('locale')->pluck('status', 'locale')->all())
        ->toBe(['es' => 'pending', 'it' => 'pending']);
});

it('requeues derived content when the English description changes without a title edit', function () {
    $tour = createEnglishSourceTour();
    $oldHash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;
    Queue::fake();

    $description = str_repeat('Discover the updated vineyard route. ', 4);
    $this->putJson("/api/partner/tours/{$tour->id}", ['description' => $description], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk();

    expect($tour->translations()->where('locale', 'en')->firstOrFail()->description)->toBe(trim($description));
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash)->not->toBe($oldHash);
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
});

it('clears optional English content and requeues derived translations', function () {
    $tour = createEnglishSourceTour();
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['en' => ['meeting_point' => 'Florence station']],
    ], $headers)->assertOk();
    $previousHash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;
    Queue::fake();

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['en' => ['meeting_point' => null]],
    ], $headers)->assertOk();

    expect($tour->translations()->where('locale', 'en')->firstOrFail()->meeting_point)->toBeNull();
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash)->not->toBe($previousHash);
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
});

it('translates traveler-facing itinerary and information while retaining structural values', function () {
    $tour = createEnglishSourceTour();
    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['en' => [
            'itinerary' => [[
                'day' => 1,
                'title' => 'Vineyard walk',
                'description' => 'Explore the vines',
                'stops' => [['title' => 'Historic cellar', 'duration_minutes' => 30]],
            ]],
            'important_information' => ['Wear comfortable shoes'],
            'inclusions' => ['Wine tasting'],
        ]],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();
    $hash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;

    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    (new GenerateTourTranslationJob($tour->id, 'es', $hash))
        ->handle(app(TourTranslationService::class), $translator);

    $translated = $tour->translations()->where('locale', 'es')->firstOrFail();
    expect($translated->itinerary[0]['day'])->toBe(1);
    expect($translated->itinerary[0]['title'])->toBe('es: Vineyard walk');
    expect($translated->itinerary[0]['stops'][0]['duration_minutes'])->toBe(30);
    expect($translated->itinerary[0]['stops'][0]['title'])->toBe('es: Historic cellar');
    expect($translated->important_information)->toBe(['es: Wear comfortable shoes']);
    expect($translated->inclusions)->toBe(['es: Wine tasting']);
    expect($tour->fresh()->guideLanguages())->toBe(['de', 'en', 'es']);
});

it('rejects partner-authored Spanish while keeping English source writable', function () {
    $tour = createEnglishSourceTour();

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['es' => ['title' => 'Título manual']],
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.es']);
});

it('accepts only the latest English revision when an older translation job finishes late', function () {
    $tour = createEnglishSourceTour();
    $oldHash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;
    $this->putJson("/api/partner/tours/{$tour->id}", [
        'title' => 'Updated English title',
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();
    $newHash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;
    expect($newHash)->not->toBe($oldHash);

    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $service = app(TourTranslationService::class);
    (new GenerateTourTranslationJob($tour->id, 'es', $oldHash))->handle($service, $translator);
    expect($tour->translations()->where('locale', 'es')->exists())->toBeFalse();

    (new GenerateTourTranslationJob($tour->id, 'es', $newHash))->handle($service, $translator);
    expect($tour->translations()->where('locale', 'es')->firstOrFail()->title)
        ->toBe('es: Updated English title');
    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('ready');
});

it('schedules a fresh search projection after a derived translation becomes ready', function () {
    $tour = createEnglishSourceTour();
    $tour->update(['status' => 'published', 'price_amount' => 5000]);
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);
    $hash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;
    Queue::fake();

    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    Tour::withoutEvents(fn () => (new GenerateTourTranslationJob($tour->id, 'es', $hash))
        ->handle(app(TourTranslationService::class), $translator));

    expect($tour->translationStates()->where('locale', 'es')->firstOrFail()->status)->toBe('ready');
    expect($tour->fresh()->toSearchableArray()['title_es'])->toBe('es: Tuscan Wine Experience');
    expect(app(TourCardTransformer::class)->transform($tour->fresh(), 'es')['title'])
        ->toBe('es: Tuscan Wine Experience');
    Queue::assertPushed(IndexTourAction::class, 1);

    $this->putJson("/api/partner/tours/{$tour->id}", ['title' => 'Revised English tour'], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk();

    expect($tour->fresh()->toSearchableArray()['title_es'])->toBe('Revised English tour');
    expect(app(TourCardTransformer::class)->transform($tour->fresh(), 'es')['title'])
        ->toBe('Revised English tour');
});

it('serves a visible English fallback until generated content is ready, then stales it on an edit', function () {
    $tour = createEnglishSourceTour();
    $tour->update(['status' => 'published']);
    $hash = $tour->translationStates()->where('locale', 'es')->firstOrFail()->source_hash;

    $this->getJson("/api/public/tours/{$tour->slug}?locale=es")
        ->assertOk()
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_status', 'pending')
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.guide_languages', ['de', 'en', 'es']);

    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    (new GenerateTourTranslationJob($tour->id, 'es', $hash))
        ->handle(app(TourTranslationService::class), $translator);

    $this->getJson("/api/public/tours/{$tour->slug}?locale=es")
        ->assertOk()
        ->assertJsonPath('data.title', 'es: Tuscan Wine Experience')
        ->assertJsonPath('data.content_locale', 'es')
        ->assertJsonPath('data.translation_status', 'ready')
        ->assertJsonMissingPath('data.translation_warning');

    $this->putJson("/api/partner/tours/{$tour->id}", ['title' => 'Updated English title'], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk();
    $this->getJson("/api/public/tours/{$tour->slug}?locale=es")
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated English title')
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_status', 'stale')
        ->assertJsonPath('data.translation_warning', 'partial_translation');
});

it('marks a rejected provider result failed while keeping the published English fallback', function () {
    $tour = createEnglishSourceTour();
    $tour->update(['status' => 'published']);
    $hash = $tour->translationStates()->where('locale', 'it')->firstOrFail()->source_hash;
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            throw new PermanentTranslationException('translation_provider_rejected');
        }
    };

    (new GenerateTourTranslationJob($tour->id, 'it', $hash))
        ->handle(app(TourTranslationService::class), $translator);

    expect($tour->translationStates()->where('locale', 'it')->firstOrFail()->status)->toBe('failed');
    $this->getJson("/api/public/tours/{$tour->slug}?locale=it")
        ->assertOk()
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_status', 'failed')
        ->assertJsonPath('data.translation_warning', 'partial_translation');
});

it('uses backend-only Gemini structured output and rejects transient provider failures', function () {
    config()->set('services.gemini.api_key', 'fake-test-key');
    config()->set('services.gemini.translation_model', 'gemini-3.5-flash-lite');
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => json_encode([
                    'translations' => [['key' => 'title', 'text' => 'Título de prueba']],
                ], JSON_THROW_ON_ERROR)]]],
            ]],
        ]),
    ]);

    expect(app(GeminiTourTranslator::class)->translate(['title' => 'Test title'], 'es'))
        ->toBe(['title' => 'Título de prueba']);
    Http::assertSent(function ($request) {
        return $request->hasHeader('x-goog-api-key', 'fake-test-key')
            && $request['generationConfig']['responseFormat']['text']['mimeType'] === 'APPLICATION_JSON';
    });

});

it('retries only transient Gemini failures', function () {
    config()->set('services.gemini.api_key', 'fake-test-key');
    config()->set('services.gemini.translation_model', 'gemini-3.5-flash-lite');
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);
    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Test title'], 'es'))
        ->toThrow(TransientTranslationException::class);
});

it('spec019 ignores reordered English object keys without content change', function () {
    $tour = createEnglishSourceTour();
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['en' => [
            'itinerary' => [[
                'day' => 1,
                'title' => 'Vineyard walk',
                'description' => 'Explore the vines',
                'stops' => [['title' => 'Historic cellar', 'duration_minutes' => 30]],
            ]],
        ]],
    ], $headers)->assertOk();

    $service = app(TourTranslationService::class);
    $hash = $service->sourceHash($tour->fresh()->translations()->where('locale', 'en')->firstOrFail());
    Queue::fake();

    // Same content, only object-key order differs: canonical projection is identical.
    $this->putJson("/api/partner/tours/{$tour->id}", [
        'translations' => ['en' => [
            'itinerary' => [[
                'stops' => [['duration_minutes' => 30, 'description' => null, 'title' => 'Historic cellar']],
                'description' => 'Explore the vines',
                'title' => 'Vineyard walk',
                'day' => 1,
            ]],
        ]],
    ], $headers)->assertOk();

    expect($service->sourceHash($tour->fresh()->translations()->where('locale', 'en')->firstOrFail()))->toBe($hash);
    expect($tour->fresh()->translationStates()->orderBy('locale')->pluck('status', 'locale')->all())
        ->toBe(['es' => 'pending', 'it' => 'pending']);
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
});

it('spec019 ignores media and guide-language saves without English changes', function () {
    $tour = createEnglishSourceTour();
    $service = app(TourTranslationService::class);
    $hash = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    Queue::fake();

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'media' => [
            ['url' => 'https://images.example.com/side.jpg', 'is_cover' => false],
            ['url' => 'https://images.example.com/cover.jpg', 'is_cover' => true],
        ],
        'guide_languages' => ['es', 'de', 'en'],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    expect($service->sourceHash($tour->fresh()->translations()->where('locale', 'en')->firstOrFail()))->toBe($hash);
    expect($tour->fresh()->translationStates()->orderBy('locale')->pluck('status', 'locale')->all())
        ->toBe(['es' => 'pending', 'it' => 'pending']);
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
});

it('spec019 stales both locales while retaining old derivatives on an English edit', function () {
    $tour = createEnglishSourceTour();
    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hash = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    (new GenerateTourTranslationJob($tour->id, 'es', $hash))->handle($service, $translator);
    (new GenerateTourTranslationJob($tour->id, 'it', $hash))->handle($service, $translator);
    $oldEs = $tour->translations()->where('locale', 'es')->firstOrFail()->title;
    $oldIt = $tour->translations()->where('locale', 'it')->firstOrFail()->title;
    Queue::fake();

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'title' => 'Revised English title for stale check',
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $fresh = $tour->fresh();
    expect($fresh->translationStates()->orderBy('locale')->pluck('status', 'locale')->all())
        ->toBe(['es' => 'stale', 'it' => 'stale']);
    foreach ($fresh->translationStates as $state) {
        expect(in_array($state->status, ['pending', 'ready', 'stale', 'failed'], true))->toBeTrue();
    }
    // Old generated text is retained until the fresh jobs complete.
    expect($fresh->translations()->where('locale', 'es')->firstOrFail()->title)->toBe($oldEs);
    expect($fresh->translations()->where('locale', 'it')->firstOrFail()->title)->toBe($oldIt);
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
});

it('spec019 commits the English source when generation dispatch fails after commit on create', function () {
    config()->set('services.gemini.api_key', '');
    Spec019CommittedFixtures::assertPrerequisites();

    $previousDefault = config('database.default');
    config()->set('database.default', Spec019CommittedFixtures::CONNECTION);
    $fixture = ['tables' => []];
    try {
        $owned = Spec019CommittedFixtures::createOwner('create', $fixture);
        $fixture['tables'] = $owned['tables'];
        expect(DB::connection()->transactionLevel())->toBe(0);

        Spec019DispatchFailureQueue::bind(true);

        $title = 'Spec019 Committed Source ' . uniqid();
        $response = $this->postJson('/api/partner/tours', [
            'title' => $title,
            'description' => str_repeat('Explore the Tuscan vineyards and local wines. ', 3),
            'category' => $owned['categorySlug'],
            'destination' => 'Florence, Italy',
            'duration_value' => 3,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
            'guide_languages' => ['de', 'en', 'es'],
        ], ['Authorization' => 'Bearer ' . $owned['token']]);

        // The source transaction really committed: English row and desired
        // states exist on the committed connection even though the
        // post-commit generation push threw.
        $tourId = Tour::whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('title', $title))->value('id');
        expect($tourId)->not->toBeNull();
        Spec019CommittedFixtures::trackTour($fixture, (int) $tourId);
        $tour = Tour::findOrFail($tourId);
        $english = $tour->translations()->where('locale', 'en')->firstOrFail();
        expect($english->title)->toBe($title);

        $service = app(TourTranslationService::class);
        $states = $tour->translationStates()->orderBy('locale')->pluck('status', 'locale')->all();
        ksort($states);
        expect(array_keys($states))->toBe(['es', 'it']);
        foreach (['es', 'it'] as $locale) {
            expect(in_array($states[$locale], ['pending', 'stale'], true))->toBeTrue();
            $state = $tour->translationStates()->where('locale', $locale)->firstOrFail();
            expect($state->source_hash)->toBe($service->sourceHash($english));
            expect((string) ($state->last_error_code ?? ''))->not->toContain(Spec019DispatchFailureQueue::MARKER);
        }

        // The failure fired only after the commit, at transaction level 0.
        $attempts = Spec019DispatchFailureQueue::translationAttempts();
        expect($attempts)->not->toBeEmpty();
        foreach ($attempts as $attempt) {
            expect($attempt['level'])->toBe(0);
        }

        // Desired: source stays 201 with a sanitized recoverable state and no
        // raw probe text in the response. Red until production catches the
        // post-commit push failure per locale.
        $response->assertCreated();
        expect(Spec019DispatchFailureQueue::translationAttempts())->toHaveCount(2);
        expect((string) $response->getContent())->not->toContain(Spec019DispatchFailureQueue::MARKER);
    } finally {
        config()->set('database.default', $previousDefault);
        Spec019CommittedFixtures::deleteOnly($fixture);
        Spec019DispatchFailureQueue::restore();
    }
});

it('spec019 commits the English update when regeneration dispatch fails after commit', function () {
    config()->set('services.gemini.api_key', '');
    Spec019CommittedFixtures::assertPrerequisites();

    $previousDefault = config('database.default');
    config()->set('database.default', Spec019CommittedFixtures::CONNECTION);
    $fixture = ['tables' => []];
    try {
        $owned = Spec019CommittedFixtures::createOwner('update', $fixture);
        $fixture['tables'] = $owned['tables'];
        $headers = ['Authorization' => 'Bearer ' . $owned['token']];

        // Healthy queue for setup so the fixture starts pending, not failed.
        $createResponse = $this->postJson('/api/partner/tours', [
            'title' => 'Spec019 Update Fixture ' . uniqid(),
            'description' => str_repeat('Explore the Tuscan vineyards and local wines. ', 3),
            'category' => $owned['categorySlug'],
            'destination' => 'Florence, Italy',
            'duration_value' => 3,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
        ], $headers);
        $createResponse->assertCreated();
        $tourId = (int) $createResponse->json('data.id');
        Spec019CommittedFixtures::trackTour($fixture, $tourId);

        Spec019DispatchFailureQueue::bind(true);
        expect(DB::connection()->transactionLevel())->toBe(0);

        $response = $this->putJson("/api/partner/tours/{$tourId}", [
            'title' => 'Spec019 Revised After Commit ' . uniqid(),
        ], $headers);

        $tour = Tour::findOrFail($tourId);
        $english = $tour->translations()->where('locale', 'en')->firstOrFail();
        expect($english->title)->toStartWith('Spec019 Revised After Commit');

        $service = app(TourTranslationService::class);
        foreach (['es', 'it'] as $locale) {
            $state = $tour->translationStates()->where('locale', $locale)->firstOrFail();
            expect(in_array($state->status, ['pending', 'stale'], true))->toBeTrue();
            expect($state->source_hash)->toBe($service->sourceHash($english));
            expect((string) ($state->last_error_code ?? ''))->not->toContain(Spec019DispatchFailureQueue::MARKER);
        }
        expect(Spec019DispatchFailureQueue::translationAttempts())->not->toBeEmpty();
        foreach (Spec019DispatchFailureQueue::translationAttempts() as $attempt) {
            expect($attempt['level'])->toBe(0);
        }

        $response->assertOk();
        expect(Spec019DispatchFailureQueue::translationAttempts())->toHaveCount(2);
        expect((string) $response->getContent())->not->toContain(Spec019DispatchFailureQueue::MARKER);
    } finally {
        config()->set('database.default', $previousDefault);
        Spec019CommittedFixtures::deleteOnly($fixture);
        Spec019DispatchFailureQueue::restore();
    }
});

it('spec019 schedules no generation when the source transaction rolls back', function () {
    Spec019DispatchFailureQueue::bind(false);
    Spec019DispatchFailureQueue::clear();

    $category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $slug = 'spec019-rollback-' . uniqid();
    try {
        DB::transaction(function () use ($slug, $category) {
            app(TourService::class)->createTour($this->partnerId, [
                'slug' => $slug,
                'category_id' => $category->id,
                'title' => 'Spec019 rollback probe',
                'description' => str_repeat('Rollback probe description. ', 4),
                'location' => 'Rome, Italy',
            ]);

            expect(Tour::where('slug', $slug)->exists())->toBeTrue();

            // Desired after-commit: nothing executes inside the transaction.
            expect(Spec019DispatchFailureQueue::translationAttempts())->toBeEmpty();

            throw new RuntimeException('spec019_rollback_probe');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('spec019_rollback_probe');
    }

    expect(Tour::where('slug', $slug)->exists())->toBeFalse();
    expect(Spec019DispatchFailureQueue::translationAttempts())->toBeEmpty();
    // Desired: the rolled-back source schedules no search projection either.
    // Red while Tour::saved dispatches IndexTourAction before commit.
    expect(Spec019DispatchFailureQueue::attemptsForJob(IndexTourAction::class))->toBeEmpty();
    expect(Spec019DispatchFailureQueue::attemptsForJob(RemoveFromIndexAction::class))->toBeEmpty();

    Spec019DispatchFailureQueue::restore();
});

it('spec019 rejects non-bounded reconcile cursors and limits', function () {
    expect(Artisan::call('tours:queue-translations', ['--after-id' => '-5']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--after-id' => 'abc']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--after-id' => '2.5']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--limit' => '0']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--limit' => '501']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--limit' => 'many']))->not->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--after-id' => '999999999', '--limit' => '1']))->toBe(0);
});

it('spec019 resumes bounded reconcile runs in ID order with a reusable cursor', function () {
    Queue::fake();
    $cursor = (int) (Tour::max('id') ?? 0);
    $ids = [
        createEnglishSourceTour()->id,
        createEnglishSourceTour()->id,
        createEnglishSourceTour()->id,
    ];
    sort($ids);

    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $cursor, '--limit' => '2']))->toBe(0);
    expect(Artisan::output())->toContain("Inspected 2 tours. Continue with --after-id={$ids[1]}.");

    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $ids[1], '--limit' => '2']))->toBe(0);
    expect(Artisan::output())->toContain("Inspected 1 tours. Continue with --after-id={$ids[2]}.");
});

it('spec019 requeues orphaned, stale, failed, and projection-less translations', function () {
    Queue::fake();
    $cursor = (int) (Tour::max('id') ?? 0);
    $tourA = createEnglishSourceTour();
    $tourB = createEnglishSourceTour();
    $service = app(TourTranslationService::class);

    // Tour A es: stale projection (ready status, translated hash mismatch, old derivative kept).
    $hashA = $service->sourceHash($tourA->translations()->where('locale', 'en')->firstOrFail());
    $tourA->translations()->updateOrCreate(['locale' => 'es'], ['title' => 'Old title es', 'description' => 'Old description es']);
    $tourA->translationStates()->updateOrCreate(
        ['locale' => 'es'],
        ['source_hash' => $hashA, 'translated_hash' => str_repeat('0', 64), 'status' => 'ready', 'last_error_code' => null]
    );
    // Tour A it: failed with a sanitized code and the old derivative kept.
    $tourA->translations()->updateOrCreate(['locale' => 'it'], ['title' => 'Old title it', 'description' => 'Old description it']);
    $tourA->translationStates()->updateOrCreate(
        ['locale' => 'it'],
        ['source_hash' => $hashA, 'translated_hash' => null, 'status' => 'failed', 'last_error_code' => 'translation_provider_temporary']
    );
    // Tour B es: apparently ready but the derivative row is missing.
    $hashB = $service->sourceHash($tourB->translations()->where('locale', 'en')->firstOrFail());
    $tourB->translations()->updateOrCreate(['locale' => 'es'], ['title' => 'Title es', 'description' => 'Description es']);
    $tourB->translationStates()->updateOrCreate(
        ['locale' => 'es'],
        ['source_hash' => $hashB, 'translated_hash' => $hashB, 'status' => 'ready', 'last_error_code' => null]
    );
    $tourB->translations()->where('locale', 'es')->delete();
    // Tour B it: orphan pending on the current hash (creation default).
    Queue::fake();

    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $cursor, '--limit' => '500']))->toBe(0);

    // Desired: all four recoverable projections requeue. Red while
    // queueIfChanged skips pending/ready rows that already carry the hash.
    Queue::assertPushed(GenerateTourTranslationJob::class, 4);
    foreach ([$tourA->id => ['es', 'it'], $tourB->id => ['es', 'it']] as $tourId => $locales) {
        foreach ($locales as $locale) {
            Queue::assertPushed(
                GenerateTourTranslationJob::class,
                fn (GenerateTourTranslationJob $job): bool => $job->tourId === $tourId && $job->locale === $locale
            );
        }
    }
});

it('spec019 bounded reruns never rewrite current derivatives or source text', function () {
    Queue::fake();
    $cursor = (int) (Tour::max('id') ?? 0);
    $tour = createEnglishSourceTour();
    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hash = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    (new GenerateTourTranslationJob($tour->id, 'es', $hash))->handle($service, $translator);
    (new GenerateTourTranslationJob($tour->id, 'it', $hash))->handle($service, $translator);

    $enBefore = $tour->translations()->where('locale', 'en')->firstOrFail()->getAttributes();
    $esBefore = $tour->translations()->where('locale', 'es')->firstOrFail()->getAttributes();
    $itBefore = $tour->translations()->where('locale', 'it')->firstOrFail()->getAttributes();
    Queue::fake();

    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $cursor]))->toBe(0);
    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $cursor]))->toBe(0);

    Queue::assertNotPushed(GenerateTourTranslationJob::class);
    expect($tour->fresh()->translations()->where('locale', 'en')->firstOrFail()->getAttributes())->toBe($enBefore);
    expect($tour->fresh()->translations()->where('locale', 'es')->firstOrFail()->getAttributes())->toBe($esBefore);
    expect($tour->fresh()->translations()->where('locale', 'it')->firstOrFail()->getAttributes())->toBe($itBefore);
});

it('spec019 refresh-ready requeues only the search projection for current derivatives', function () {
    Queue::fake();
    $cursor = (int) (Tour::max('id') ?? 0);
    $tour = createEnglishSourceTour();
    $service = app(TourTranslationService::class);
    $translator = new class implements TourContentTranslator
    {
        public function translate(array $strings, string $locale): array
        {
            return array_map(fn (string $text): string => "{$locale}: {$text}", $strings);
        }
    };
    $hash = $service->sourceHash($tour->translations()->where('locale', 'en')->firstOrFail());
    (new GenerateTourTranslationJob($tour->id, 'es', $hash))->handle($service, $translator);
    (new GenerateTourTranslationJob($tour->id, 'it', $hash))->handle($service, $translator);
    $esBefore = $tour->translations()->where('locale', 'es')->firstOrFail()->getAttributes();
    Queue::fake();

    // Desired: --refresh-ready queues a fresh IndexTourAction for tours whose
    // derivatives are current, without provider calls or derivative rewrites.
    // Red: the command has no --refresh-ready flag; no source fix here.
    expect(Artisan::call('tours:queue-translations', ['--after-id' => (string) $cursor, '--refresh-ready' => true]))->toBe(0);
    Queue::assertPushed(IndexTourAction::class, 1);
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
    expect($tour->fresh()->translations()->where('locale', 'es')->firstOrFail()->getAttributes())->toBe($esBefore);
});
