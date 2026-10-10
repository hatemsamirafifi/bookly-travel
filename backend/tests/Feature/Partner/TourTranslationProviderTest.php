<?php

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Services\GeminiTourTranslator;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Partner\Services\TransientTranslationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\TourContentFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.gemini.api_key', 'fake-test-key');
    config()->set('services.gemini.translation_model', 'gemini-3.5-flash-lite');
});

function t035GeminiBody(array $translations, string $finishReason = 'STOP'): array
{
    return [
        'candidates' => [[
            'finishReason' => $finishReason,
            'content' => ['parts' => [['text' => json_encode(
                ['translations' => $translations],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )]]],
        ]],
    ];
}

function t035FakeSuccess(array $translations, string $finishReason = 'STOP'): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            t035GeminiBody($translations, $finishReason)
        ),
    ]);
}

function t035ValidSource(): array
{
    return [
        'title' => 'Tuscan Wine Walk',
        'description' => 'Walk the vineyard rows at sunset.',
        'highlights' => ['Sunset views'],
        'inclusions' => ['Wine tasting'],
        'exclusions' => null,
        'meeting_point' => 'Florence station',
        'cancellation_policy' => 'Free cancellation 24h before.',
        'itinerary' => [
            [
                'day' => 1,
                'title' => 'Vineyard walk',
                'description' => 'Explore the vines',
                'stops' => [
                    ['title' => 'Historic cellar', 'description' => null, 'duration_minutes' => 30],
                ],
            ],
            [
                'day' => 2,
                'title' => 'Hill town',
                'description' => null,
                'stops' => [],
            ],
        ],
        'important_information' => ['Wear comfortable shoes'],
    ];
}

it('sends backend header with configured model and structured json payload', function () {
    config()->set('services.gemini.translation_model', 'gemini-3.5-flash-lite');
    t035FakeSuccess([['key' => 'title', 'text' => 'Título de prueba']]);

    $result = app(GeminiTourTranslator::class)->translate(['title' => 'Test title'], 'es');

    expect($result)->toBe(['title' => 'Título de prueba']);
    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return str_contains($request->url(), 'models/gemini-3.5-flash-lite:generateContent')
            && $request->hasHeader('x-goog-api-key', 'fake-test-key')
            && ($payload['generationConfig']['responseFormat']['text']['mimeType'] ?? null) === 'APPLICATION_JSON'
            && ($payload['generationConfig']['responseFormat']['text']['schema']['required'] ?? null) === ['translations']
            && is_string($payload['contents'][0]['parts'][0]['text'] ?? null);
    });
});

it('uses a forty second provider timeout', function () {
    $captured = null;
    Http::globalMiddleware(function (callable $handler) use (&$captured) {
        return function ($request, array $options) use ($handler, &$captured) {
            $captured = $options;

            return $handler($request, $options);
        };
    });
    t035FakeSuccess([['key' => 'title', 'text' => 'Título']]);

    app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');

    expect($captured['timeout'] ?? null)->toBe(40);
});

it('reconstructs out of order provider items into source path order', function () {
    $strings = [
        'title' => 'Alpha',
        'description' => 'Beta',
        'meeting_point' => 'Gamma',
    ];
    t035FakeSuccess([
        ['key' => 'meeting_point', 'text' => 'ES Gamma'],
        ['key' => 'title', 'text' => 'ES Alpha'],
        ['key' => 'description', 'text' => 'ES Beta'],
    ]);

    $result = app(GeminiTourTranslator::class)->translate($strings, 'es');

    expect(array_keys($result))->toBe(['title', 'description', 'meeting_point']);
    expect(array_values($result))->toBe(['ES Alpha', 'ES Beta', 'ES Gamma']);
});

it('rejects duplicate provider keys as permanent output invalid', function () {
    t035FakeSuccess([
        ['key' => 'title', 'text' => 'Uno'],
        ['key' => 'title', 'text' => 'Dos'],
    ]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(
        ['title' => 'Hello', 'description' => 'World'], 'es'
    ))->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects missing provider keys as permanent output invalid', function () {
    t035FakeSuccess([['key' => 'title', 'text' => 'Solo uno']]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(
        ['title' => 'Hello', 'description' => 'World'], 'es'
    ))->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects unknown provider keys as permanent output invalid', function () {
    t035FakeSuccess([
        ['key' => 'title', 'text' => 'ES Hello'],
        ['key' => 'unknown.path', 'text' => 'ES extra'],
    ]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(
        ['title' => 'Hello', 'description' => 'World'], 'es'
    ))->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects non string provider text as permanent output invalid', function () {
    t035FakeSuccess([['key' => 'title', 'text' => 42]]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects blank provider text as permanent output invalid', function () {
    t035FakeSuccess([['key' => 'title', 'text' => '   ']]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects malformed provider body as permanent output invalid', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => 'not-json-at-all']]],
            ]],
        ]),
    ]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(PermanentTranslationException::class, 'translation_output_invalid');
});

it('rejects blocked provider finish reasons as permanent blocked', function () {
    t035FakeSuccess([['key' => 'title', 'text' => 'X']], 'SAFETY');

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(PermanentTranslationException::class, 'translation_provider_blocked');
});

it('rejects client errors as permanent provider rejected', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad'], 400)]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(PermanentTranslationException::class, 'translation_provider_rejected');
});

it('treats rate limit and server errors as transient provider temporary', function (int $status) {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], $status)]);

    try {
        app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');
        expect(false)->toBeTrue('expected transient failure');
    } catch (TransientTranslationException $e) {
        expect($e->getMessage())->toBe('translation_provider_temporary');
    }
})->with([408, 429, 500, 503]);

it('treats connection failures as transient network errors', function () {
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es'))
        ->toThrow(TransientTranslationException::class, 'translation_network');
});

it('rejects unsupported locales and empty input as invalid requests', function () {
    t035FakeSuccess([['key' => 'title', 'text' => 'X']]);

    expect(fn () => app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'fr'))
        ->toThrow(PermanentTranslationException::class, 'invalid_translation_request');
    expect(fn () => app(GeminiTourTranslator::class)->translate([], 'es'))
        ->toThrow(PermanentTranslationException::class, 'invalid_translation_request');
});

it('rejects missing provider configuration without leaking secrets', function () {
    config()->set('services.gemini.api_key', '');
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(t035GeminiBody([
        ['key' => 'title', 'text' => 'X'],
    ]))]);

    try {
        app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');
        expect(false)->toBeTrue('expected configuration failure');
    } catch (PermanentTranslationException $e) {
        expect($e->getMessage())->toBe('translation_not_configured')
            ->and($e->getMessage())->not->toContain('fake-test-key');
    }
    Http::assertNothingSent();
});

it('emits only fixed sanitized transient categories', function () {
    $allowed = [
        'invalid_translation_request',
        'translation_not_configured',
        'translation_network',
        'translation_provider_temporary',
        'translation_provider_rejected',
        'translation_provider_blocked',
        'translation_output_invalid',
    ];
    $secret = 'super-secret-marker-xyz';

    config()->set('services.gemini.api_key', $secret);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
    try {
        app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');
        expect(false)->toBeTrue('expected transient failure');
    } catch (TransientTranslationException $e) {
        expect($e->getMessage())->toBeIn($allowed)
            ->and($e->getMessage())->not->toContain($secret);
    }
});

it('emits only fixed sanitized permanent categories', function () {
    $allowed = [
        'invalid_translation_request',
        'translation_not_configured',
        'translation_network',
        'translation_provider_temporary',
        'translation_provider_rejected',
        'translation_provider_blocked',
        'translation_output_invalid',
    ];
    $secret = 'super-secret-marker-xyz';

    config()->set('services.gemini.api_key', $secret);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => $secret], 400)]);
    try {
        app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');
        expect(false)->toBeTrue('expected permanent failure');
    } catch (PermanentTranslationException $e) {
        expect($e->getMessage())->toBeIn($allowed)
            ->and($e->getMessage())->not->toContain($secret);
    }
});

it('makes exactly one http attempt for a transient failure without nested retries', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

    try {
        app(GeminiTourTranslator::class)->translate(['title' => 'Hello'], 'es');
    } catch (TransientTranslationException) {
    }

    Http::assertSentCount(1);
});

it('extracts only non blank strings while skipping structural integers and nulls', function () {
    $service = app(TourTranslationService::class);
    $strings = $service->strings(t035ValidSource());

    expect($strings['title'])->toBe('Tuscan Wine Walk')
        ->and($strings)->not->toHaveKey('itinerary.0.day')
        ->and($strings)->not->toHaveKey('itinerary.0.stops.0.duration_minutes')
        ->and($strings)->not->toHaveKey('exclusions')
        ->and($strings['itinerary.0.title'])->toBe('Vineyard walk')
        ->and($strings['itinerary.0.stops.0.title'])->toBe('Historic cellar');
});

it('reconstructs translated strings retaining integers nulls order and repeated days', function () {
    $service = app(TourTranslationService::class);
    $source = [
        'title' => 'Repeat days',
        'description' => null,
        'highlights' => ['One', 'Two'],
        'inclusions' => null,
        'exclusions' => null,
        'meeting_point' => null,
        'cancellation_policy' => null,
        'itinerary' => [
            ['day' => 1, 'title' => 'First', 'description' => null, 'stops' => []],
            ['day' => 1, 'title' => 'Second', 'description' => 'Desc', 'stops' => [
                ['title' => 'Stop', 'description' => null, 'duration_minutes' => null],
                ['title' => 'Long stop', 'description' => 'See', 'duration_minutes' => 90],
            ]],
        ],
        'important_information' => null,
    ];
    $expected = $service->strings($source);
    $translated = [];
    foreach ($expected as $path => $text) {
        $translated[$path] = "es: {$text}";
    }

    $result = $service->applyStrings($source, $translated);

    expect($result['itinerary'][0]['day'])->toBe(1)
        ->and($result['itinerary'][1]['day'])->toBe(1)
        ->and($result['itinerary'][0]['description'])->toBeNull()
        ->and($result['itinerary'][1]['stops'][0]['duration_minutes'])->toBeNull()
        ->and($result['itinerary'][1]['stops'][1]['duration_minutes'])->toBe(90)
        ->and($result['description'])->toBeNull()
        ->and($result['itinerary'][0]['title'])->toBe('es: First')
        ->and($result['itinerary'][1]['title'])->toBe('es: Second')
        ->and(array_keys($result))->toBe(array_keys($source));
});

it('allows valid empty itinerary and zero stop days', function () {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $source['itinerary'] = [];
    $result = $service->applyStrings($source, $service->strings($source) === [] ? [] : array_map(
        fn (string $t): string => "it: {$t}", $service->strings($source)
    ));

    expect($result['itinerary'])->toBe([]);

    $source['itinerary'] = [
        ['day' => 3, 'title' => 'Easy day', 'description' => null, 'stops' => []],
    ];
    $translated = [];
    foreach ($service->strings($source) as $path => $text) {
        $translated[$path] = "it: {$text}";
    }

    expect($service->applyStrings($source, $translated)['itinerary'][0]['stops'])->toBe([]);
});

it('accepts boundary valid shared limits', function () {
    $service = app(TourTranslationService::class);
    $source = [
        'title' => str_repeat('t', 120),
        'description' => str_repeat('d', 5000),
        'highlights' => array_fill(0, 30, str_repeat('h', 500)),
        'inclusions' => array_fill(0, 30, str_repeat('i', 500)),
        'exclusions' => array_fill(0, 30, str_repeat('e', 500)),
        'meeting_point' => str_repeat('m', 500),
        'cancellation_policy' => str_repeat('c', 2000),
        'itinerary' => array_map(
            fn (int $n): array => [
                'day' => $n,
                'title' => str_repeat('t', 160),
                'description' => str_repeat('d', 2000),
                'stops' => [
                    ['title' => str_repeat('s', 160), 'description' => str_repeat('x', 2000), 'duration_minutes' => 1440],
                ],
            ],
            range(1, 30)
        ),
        'important_information' => array_fill(0, 30, str_repeat('n', 500)),
    ];
    $source['itinerary'][0]['stops'] = array_fill(0, 20, [
        'title' => str_repeat('s', 160),
        'description' => str_repeat('x', 2000),
        'duration_minutes' => 1440,
    ]);
    $translated = $service->strings($source);

    $result = $service->applyStrings($source, $translated);

    expect($result['title'])->toBe(str_repeat('t', 120))
        ->and($result['itinerary'])->toHaveCount(30)
        ->and($result['itinerary'][0]['stops'])->toHaveCount(20)
        ->and($result['itinerary'][29]['stops'][0]['duration_minutes'])->toBe(1440);
});

it('rejects overbound shared limits', function (callable $mutate) {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $mutate($source);
    $translated = [];
    foreach ($service->strings($source) as $path => $text) {
        $translated[$path] = "es: {$text}";
    }

    expect(fn () => $service->applyStrings($source, $translated))->toThrow(ValidationException::class);
})->with([
    'title over 120' => [fn (array &$s) => $s['title'] = str_repeat('t', 121)],
    'description over 5000' => [fn (array &$s) => $s['description'] = str_repeat('d', 5001)],
    'highlights over 30 items' => [fn (array &$s) => $s['highlights'] = array_fill(0, 31, 'ok')],
    'highlight item over 500' => [fn (array &$s) => $s['highlights'] = [str_repeat('h', 501)]],
    'inclusions over 30 items' => [fn (array &$s) => $s['inclusions'] = array_fill(0, 31, 'ok')],
    'inclusion item over 500' => [fn (array &$s) => $s['inclusions'] = [str_repeat('i', 501)]],
    'exclusions over 30 items' => [fn (array &$s) => $s['exclusions'] = array_fill(0, 31, 'ok')],
    'exclusion item over 500' => [fn (array &$s) => $s['exclusions'] = [str_repeat('e', 501)]],
    'information over 30 items' => [fn (array &$s) => $s['important_information'] = array_fill(0, 31, 'ok')],
    'information item over 500' => [fn (array &$s) => $s['important_information'] = [str_repeat('n', 501)]],
    'meeting point over 500' => [fn (array &$s) => $s['meeting_point'] = str_repeat('m', 501)],
    'cancellation over 2000' => [fn (array &$s) => $s['cancellation_policy'] = str_repeat('c', 2001)],
    'itinerary over 30 days' => [fn (array &$s) => $s['itinerary'] = array_fill(0, 31, [
        'day' => 1, 'title' => 'Day', 'description' => null, 'stops' => [],
    ])],
    'day number zero' => [fn (array &$s) => $s['itinerary'][0]['day'] = 0],
    'day number over 30' => [fn (array &$s) => $s['itinerary'][0]['day'] = 31],
    'day title over 160' => [fn (array &$s) => $s['itinerary'][0]['title'] = str_repeat('t', 161)],
    'day description over 2000' => [fn (array &$s) => $s['itinerary'][0]['description'] = str_repeat('d', 2001)],
    'stops over 20' => [fn (array &$s) => $s['itinerary'][0]['stops'] = array_fill(0, 21, ['title' => 'Stop'])],
    'stop title over 160' => [fn (array &$s) => $s['itinerary'][0]['stops'][0]['title'] = str_repeat('s', 161)],
    'stop description over 2000' => [fn (array &$s) => $s['itinerary'][0]['stops'][0]['description'] = str_repeat('x', 2001)],
    'duration zero' => [fn (array &$s) => $s['itinerary'][0]['stops'][0]['duration_minutes'] = 0],
    'duration over 1440' => [fn (array &$s) => $s['itinerary'][0]['stops'][0]['duration_minutes'] = 1441],
    'explicit null stops rejected' => [fn (array &$s) => $s['itinerary'][0]['stops'] = null],
    'blank day title rejected' => [fn (array &$s) => $s['itinerary'][0]['title'] = '   '],
    'blank stop title rejected' => [fn (array &$s) => $s['itinerary'][0]['stops'][0]['title'] = '   '],
]);

it('rejects objects in place of translated source lists', function (string $field) {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $source[$field] = ['named' => 'Authored text'];

    expect(fn () => $service->applyStrings($source, $service->strings($source)))
        ->toThrow(ValidationException::class);
})->with(['highlights', 'inclusions', 'exclusions', 'important_information']);

it('rejects objects in place of translated day and stop lists', function (bool $replaceStops) {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    if ($replaceStops) {
        $source['itinerary'][0]['stops'] = ['named' => $source['itinerary'][0]['stops'][0]];
    } else {
        $source['itinerary'] = ['named' => $source['itinerary'][0]];
    }

    expect(fn () => $service->applyStrings($source, $service->strings($source)))
        ->toThrow(ValidationException::class);
})->with([false, true]);

it('rejects persisted digit strings instead of translating or coercing structural numbers', function (bool $isDuration) {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    if ($isDuration) {
        $source['itinerary'][0]['stops'][0]['duration_minutes'] = '30';
    } else {
        $source['itinerary'][0]['day'] = '1';
    }

    expect(fn () => $service->applyStrings($source, $service->strings($source)))
        ->toThrow(ValidationException::class);
})->with([false, true]);

it('measures translated text bounds in Unicode characters', function () {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $source['title'] = str_repeat('🌍', 120);

    expect($service->applyStrings($source, $service->strings($source))['title'])
        ->toBe($source['title']);

    $source['title'] .= '🌍';
    expect(fn () => $service->applyStrings($source, $service->strings($source)))
        ->toThrow(ValidationException::class);
});

it('never emits malformed persisted day strings as translated prose', function () {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $source['itinerary'][0]['day'] = '1.5';

    expect($service->strings($source))->not->toHaveKey('itinerary.0.day');

    $translated = [];
    foreach ($service->strings($source) as $path => $text) {
        $translated[$path] = "es: {$text}";
    }

    expect(fn () => $service->applyStrings($source, $translated))
        ->toThrow(ValidationException::class);
});

it('never emits malformed persisted duration strings as translated prose', function () {
    $service = app(TourTranslationService::class);
    $source = t035ValidSource();
    $source['itinerary'][0]['stops'][0]['duration_minutes'] = 'soon';

    expect($service->strings($source))->not->toHaveKey('itinerary.0.stops.0.duration_minutes');

    $translated = [];
    foreach ($service->strings($source) as $path => $text) {
        $translated[$path] = "es: {$text}";
    }

    expect(fn () => $service->applyStrings($source, $translated))
        ->toThrow(ValidationException::class);
});

it('retries translation jobs four times with five fifteen sixty backoff', function () {
    $job = new GenerateTourTranslationJob(1, 'es', hash('sha256', 'source'));

    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([5, 15, 60]);
});

it('rejects overlong generated text even when the complete English source is valid', function (string $path, int $maximum) {
    $service = app(TourTranslationService::class);
    $source = TourContentFixtures::englishAttributes([
        'exclusions' => ['Meals'],
        'itinerary' => [[
            'day' => 1, 'title' => 'Old town', 'description' => 'A real walking route.',
            'stops' => [['title' => 'Main square', 'description' => 'Meet the guide.', 'duration_minutes' => 30]],
        ]],
    ]);
    $service->validateTranslatablePayload($source);
    $translated = $service->strings($source);
    expect(array_key_exists($path, $translated))->toBeTrue();
    $translated[$path] = str_repeat('A', $maximum + 1);

    expect(fn () => $service->applyStrings($source, $translated))->toThrow(ValidationException::class);
})->with([
    'generated title' => ['title', 120],
    'generated description' => ['description', 5000],
    'generated meeting point' => ['meeting_point', 500],
    'generated cancellation' => ['cancellation_policy', 2000],
    'generated highlight' => ['highlights.0', 500],
    'generated inclusion' => ['inclusions.0', 500],
    'generated exclusion' => ['exclusions.0', 500],
    'generated information' => ['important_information.0', 500],
    'generated day title' => ['itinerary.0.title', 160],
    'generated day description' => ['itinerary.0.description', 2000],
    'generated stop title' => ['itinerary.0.stops.0.title', 160],
    'generated stop description' => ['itinerary.0.stops.0.description', 2000],
]);
