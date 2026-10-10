<?php

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\TourTranslationState;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TourContentFixtures;

beforeEach(function () {
    Queue::fake();
    $this->tour = makeSearchableTour('draft');
    $this->english = $this->tour->translations()->create(['locale' => 'en'] + TourContentFixtures::englishAttributes());
    $this->service = app(TourTranslationService::class);
    $this->hash = $this->service->sourceHash($this->english);
    $this->service->queueIfChanged($this->tour, null);
});

function spec019CountingTranslator(): TourContentTranslator
{
    return new class implements TourContentTranslator
    {
        public int $calls = 0;

        public function translate(array $strings, string $locale): array
        {
            $this->calls++;

            return array_map(fn (string $value): string => $locale . ': ' . $value, $strings);
        }
    };
}

it('skips provider and derivative rewrites for an already current ready job', function () {
    TourContentFixtures::seedDerivative($this->tour, 'es', 'current');
    $before = $this->tour->translations()->where('locale', 'es')->firstOrFail()->getRawOriginal();
    $beforeState = $this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal();
    $translator = spec019CountingTranslator();
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->handle($this->service, $translator);

    expect($translator->calls)->toBe(0);
    expect($this->tour->translations()->where('locale', 'es')->firstOrFail()->getRawOriginal())->toBe($before);
    expect($this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal())->toBe($beforeState);
});

it('repairs a matching ready state whose derivative row is missing', function () {
    $this->tour->translationStates()->where('locale', 'es')->update(['status' => 'ready', 'translated_hash' => $this->hash]);
    $translator = spec019CountingTranslator();
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->handle($this->service, $translator);

    expect($translator->calls)->toBe(1);
    expect($this->service->currentTranslation($this->tour->fresh(), 'es')?->title)->toBe('es: ' . $this->english->title);
});

it('does not let terminal failure demote ready content or newer desired content', function (string $kind) {
    if ($kind === 'ready') {
        TourContentFixtures::seedDerivative($this->tour, 'es', 'current');
    } else {
        $this->tour->translationStates()->where('locale', 'es')->update(['source_hash' => str_repeat('a', 64)]);
    }
    $before = $this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal();
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->failed(new RuntimeException('FAKE_PRIVATE_PROVIDER_BODY_019'));
    expect($this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal())->toBe($before);
})->with(['ready', 'newer']);

it('checks fresh English before recording terminal failure even when desired state is behind', function () {
    $this->english->update(['title' => 'New English source already committed']);
    $before = $this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal();
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->failed(new RuntimeException('FAKE_PRIVATE_PROVIDER_BODY_019'));
    expect($this->tour->translationStates()->where('locale', 'es')->firstOrFail()->getRawOriginal())->toBe($before);
});

it('persists a fixed safe category for unrecognized permanent exception text', function (string $message) {
    $translator = new class($message) implements TourContentTranslator
    {
        public function __construct(private readonly string $message) {}

        public function translate(array $strings, string $locale): array
        {
            throw new PermanentTranslationException($this->message);
        }
    };
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->handle($this->service, $translator);
    $state = $this->tour->translationStates()->where('locale', 'es')->firstOrFail();
    expect($state->status)->toBe('failed');
    expect($state->last_error_code)->toBe('translation_output_invalid');
    expect($state->last_error_code)->not->toContain($message);
})->with(['FAKE_PRIVATE_PROVIDER_BODY_019', ' arbitrary response ', str_repeat('FAKE_BODY_', 10)]);

it('keeps recognized permanent failure categories', function (string $code) {
    $translator = new class($code) implements TourContentTranslator
    {
        public function __construct(private readonly string $code) {}

        public function translate(array $strings, string $locale): array
        {
            throw new PermanentTranslationException($this->code);
        }
    };
    (new GenerateTourTranslationJob($this->tour->id, 'es', $this->hash))->handle($this->service, $translator);
    expect($this->tour->translationStates()->where('locale', 'es')->firstOrFail()->last_error_code)->toBe($code);
})->with(['translation_not_configured', 'translation_provider_rejected', 'translation_provider_blocked']);

it('does not serialize internal revision hashes or error categories from the state model', function () {
    $state = TourTranslationState::where('tour_id', $this->tour->id)->where('locale', 'es')->firstOrFail();
    $state->update(['last_error_code' => 'translation_not_configured']);
    foreach (['source_hash', 'translated_hash', 'last_error_code'] as $key) {
        expect(array_key_exists($key, $state->toArray()))->toBeFalse();
    }
});

it('does not turn malformed persisted day or stop objects into a valid derivative', function (bool $malformedStops) {
    $day = ['day' => 1, 'title' => 'Real day', 'description' => null, 'stops' => []];
    if ($malformedStops) {
        $day['stops'] = ['named-stop' => ['title' => 'Real stop', 'description' => null, 'duration_minutes' => 30]];
        $itinerary = [$day];
    } else {
        $itinerary = ['named-day' => $day];
    }
    $this->english->update(['itinerary' => $itinerary]);
    $hash = $this->service->sourceHash($this->english->fresh());
    $this->tour->translationStates()->where('locale', 'es')->update(['source_hash' => $hash]);
    $beforeSource = $this->english->fresh()->getRawOriginal('itinerary');
    $translator = spec019CountingTranslator();
    (new GenerateTourTranslationJob($this->tour->id, 'es', $hash))->handle($this->service, $translator);

    expect($this->tour->translations()->where('locale', 'es')->exists())->toBeFalse();
    $state = $this->tour->translationStates()->where('locale', 'es')->firstOrFail();
    expect($state->status)->toBe('failed');
    expect($state->last_error_code)->toBe('translation_output_invalid');
    expect($this->english->fresh()->getRawOriginal('itinerary'))->toBe($beforeSource);
})->with(['day object' => false, 'stops object' => true]);
