<?php

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\GeminiTourTranslator;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Partner\Services\TransientTranslationException;
use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $user = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $this->categorySlug = $category->slug;
    $this->partnerId = $partner->id;
    $this->token = $user->createToken('test', ['partner'])->plainTextToken;
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
    expect($tour->translationStates()->pluck('status', 'locale')->all())
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
    expect($tour->fresh()->translationStates()->pluck('status', 'locale')->all())
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
