<?php

// Foundation (T008): English input-boundary normalization and forgery
// rejection at the reusable rule layer. Full create/update application is
// US1 (T015/T016); these tests pin boundary behavior through real
// validation, never by inspecting rule strings.

use App\Domains\Partner\Requests\StoreTourRequest;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Requests\UpdateTourRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function spec019BoundaryValidator(array $input, bool $isCreate = false): Illuminate\Validation\Validator
{
    $rules = array_merge(
        TourContentRules::sourceRules($isCreate),
        TourContentRules::itineraryRules('itinerary'),
        TourContentRules::itineraryRules('translations.en.itinerary')
    );
    $validator = Validator::make($input, $rules);
    $validator->after(function (Illuminate\Validation\Validator $validator) use ($input): void {
        foreach (TourContentRules::forbiddenPresentPaths($input) as $path => $message) {
            $validator->errors()->add($path, $message);
        }
    });

    return $validator;
}

it('prefers explicit nested English keys while shorthand fills absent keys', function () {
    $patch = TourContentRules::normalizeEnglishPatch([
        'title' => 'Shorthand title',
        'description' => 'Shorthand description.',
        'translations' => ['en' => ['title' => 'Nested title']],
    ]);

    expect($patch['title'])->toBe('Nested title');
    expect($patch['description'])->toBe('Shorthand description.');
});

it('preserves omitted keys and keeps null and empty-array meanings distinct', function () {
    $patch = TourContentRules::normalizeEnglishPatch([
        'title' => 'Kept title',
        'translations' => ['en' => ['meeting_point' => null, 'highlights' => []]],
    ]);

    expect($patch)->not->toHaveKey('description');
    expect($patch)->not->toHaveKey('itinerary');
    expect(array_key_exists('meeting_point', $patch))->toBeTrue();
    expect($patch['meeting_point'])->toBeNull();
    expect($patch['highlights'])->toBe([]);
});

it('normalizes absent stops to empty arrays while passing malformed values through to validation', function () {
    $normalized = TourContentRules::normalizeItinerary([
        ['day' => 2, 'title' => 'Second'],
        ['day' => 1, 'title' => 'First', 'stops' => [['title' => 'Stop']]],
        ['day' => 1, 'title' => 'Repeated day number stays valid', 'stops' => null],
    ]);

    expect($normalized[0]['stops'])->toBe([]);
    expect($normalized[1]['stops'])->toBe([['title' => 'Stop']]);
    expect($normalized[2]['stops'])->toBeNull();
    expect(array_column($normalized, 'day'))->toBe([2, 1, 1]);

    // Malformed input is preserved, never silently cleared to [].
    expect(TourContentRules::normalizeItinerary('Just a string'))->toBe('Just a string');
    expect(TourContentRules::normalizeItinerary(null))->toBeNull();
});

it('rejects over-limit source text and lists with exact field paths', function () {
    $validator = spec019BoundaryValidator(['translations' => ['en' => [
        'title' => str_repeat('x', 121),
        'description' => str_repeat('x', 5001),
        'highlights' => array_fill(0, 31, 'ok'),
        'inclusions' => [str_repeat('x', 501)],
        'meeting_point' => str_repeat('x', 501),
        'cancellation_policy' => str_repeat('x', 2001),
        'important_information' => array_fill(0, 31, 'ok'),
    ]]]);

    expect($validator->fails())->toBeTrue();
    foreach ([
        'translations.en.title',
        'translations.en.description',
        'translations.en.highlights',
        'translations.en.inclusions.0',
        'translations.en.meeting_point',
        'translations.en.cancellation_policy',
        'translations.en.important_information',
    ] as $path) {
        expect($validator->errors()->keys())->toContain($path);
    }
});

it('rejects null titles while accepting nullable draft descriptions', function () {
    $nullTitle = spec019BoundaryValidator(['translations' => ['en' => ['title' => null]]]);
    expect($nullTitle->fails())->toBeTrue();
    expect($nullTitle->errors()->keys())->toContain('translations.en.title');

    $nullDescription = spec019BoundaryValidator(['translations' => ['en' => [
        'title' => 'Valid title',
        'description' => null,
    ]]]);
    expect($nullDescription->passes())->toBeTrue();
});

it('rejects out-of-range itinerary values with exact nested paths', function () {
    $validator = spec019BoundaryValidator(['translations' => ['en' => ['itinerary' => [
        ['day' => 0, 'title' => 'Day zero'],
        ['day' => 31, 'title' => 'Day thirty-one'],
        ['day' => 1.5, 'title' => 'Fractional day'],
        ['day' => 1, 'title' => '   '],
        ['day' => 2, 'title' => 'Valid day', 'description' => str_repeat('x', 2001), 'stops' => [
            ['title' => ''],
            ['title' => '   '],
            ['title' => str_repeat('x', 161)],
            ['title' => 'Timed stop', 'duration_minutes' => 0],
            ['title' => 'Long stop', 'duration_minutes' => 1441],
            ['title' => 'Fractional stop', 'duration_minutes' => 1.5],
        ]],
    ]]]]);

    expect($validator->fails())->toBeTrue();
    foreach ([
        'translations.en.itinerary.0.day',
        'translations.en.itinerary.1.day',
        'translations.en.itinerary.2.day',
        'translations.en.itinerary.3.title',
        'translations.en.itinerary.4.description',
        'translations.en.itinerary.4.stops.0.title',
        'translations.en.itinerary.4.stops.1.title',
        'translations.en.itinerary.4.stops.2.title',
        'translations.en.itinerary.4.stops.3.duration_minutes',
        'translations.en.itinerary.4.stops.4.duration_minutes',
        'translations.en.itinerary.4.stops.5.duration_minutes',
    ] as $path) {
        expect($validator->errors()->keys())->toContain($path);
    }
});

it('accepts boundary-valid itineraries including empty lists and zero-stop days', function () {
    $validator = spec019BoundaryValidator(['translations' => ['en' => ['itinerary' => [
        ['day' => 1, 'title' => 'Zero stops valid', 'description' => null, 'stops' => []],
        ['day' => 30, 'title' => str_repeat('x', 160), 'description' => str_repeat('x', 2000), 'stops' => [
            ['title' => str_repeat('y', 160), 'description' => null, 'duration_minutes' => 1],
            ['title' => 'Max duration', 'duration_minutes' => 1440],
        ]],
    ]]]]);

    expect($validator->passes())->toBeTrue();
});

it('rejects explicit null stops instead of treating null as empty', function () {
    $validator = spec019BoundaryValidator(['translations' => ['en' => ['itinerary' => [
        ['day' => 1, 'title' => 'Null stops rejected', 'stops' => null],
    ]]]]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('translations.en.itinerary.0.stops');
});

it('rejects every authored non-English locale with its precise path', function () {
    foreach (['es', 'it', 'fr', 'de'] as $locale) {
        foreach ([['title' => 'Authored'], null, ''] as $index => $value) {
            $validator = spec019BoundaryValidator(['translations' => [$locale => $value]]);
            expect($validator->fails())->toBeTrue("locale {$locale} variant {$index} must fail");
            expect($validator->errors()->keys())->toContain("translations.{$locale}");
        }
    }

    $clean = spec019BoundaryValidator(['translations' => ['en' => ['title' => 'English only']]]);
    expect($clean->errors()->keys())->not->toContain('translations.en');
});

it('rejects platform-owned keys top-level and nested, even when null or empty', function () {
    foreach (['source_hash', 'translated_hash', 'translation_status', 'translation_statuses', 'readiness', 'last_error_code'] as $key) {
        foreach (['some-value', null, ''] as $index => $value) {
            $top = spec019BoundaryValidator([$key => $value]);
            expect($top->fails())->toBeTrue("top-level {$key} variant {$index} must fail");
            expect($top->errors()->keys())->toContain($key);

            $nested = spec019BoundaryValidator(['translations' => ['en' => ['title' => 'T', $key => $value]]]);
            expect($nested->fails())->toBeTrue("nested {$key} variant {$index} must fail");
            expect($nested->errors()->keys())->toContain("translations.en.{$key}");
        }
    }
});

it('rejects nested provider state and top-level provider/job/error state', function () {
    $nested = spec019BoundaryValidator(['translations' => ['en' => [
        'title' => 'English tour',
        'provider_body' => null,
    ]]]);
    expect($nested->fails())->toBeTrue();
    expect($nested->errors()->keys())->toContain('translations.en.provider_body');

    $topLevel = spec019BoundaryValidator([
        'title' => 'English tour',
        'provider_error' => null,
        'job_state' => [],
    ]);
    expect($topLevel->fails())->toBeTrue();
    expect($topLevel->errors()->keys())->toContain('provider_error', 'job_state');
});

it('rejects unexpected English source keys while keeping valid non-source input intact', function () {
    $unexpected = spec019BoundaryValidator(['translations' => ['en' => [
        'title' => 'English tour',
        'custom_field' => 'not a source field',
    ]]]);
    expect($unexpected->fails())->toBeTrue();
    expect($unexpected->errors()->keys())->toContain('translations.en.custom_field');

    $valid = spec019BoundaryValidator([
        'title' => 'English tour',
        'description' => null,
        'difficulty_level' => 'easy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'pricing_tiers' => [['name' => 'Adult', 'price' => 50]],
        'availability_rules' => [['rule_type' => 'weekly']],
        'translations' => ['en' => ['title' => 'English tour', 'itinerary' => []]],
    ]);
    expect($valid->passes())->toBeTrue();
});

it('wires the shared boundary into both partner requests with field-specific errors', function () {
    $store = new StoreTourRequest(['translations' => ['fr' => ['title' => 'Français']], 'source_hash' => null]);
    $storeValidator = Validator::make($store->all(), $store->rules());
    $store->withValidator($storeValidator);

    expect($storeValidator->fails())->toBeTrue();
    expect($storeValidator->errors()->keys())->toContain('translations.fr', 'source_hash');

    $update = new UpdateTourRequest(['translations' => ['en' => ['title' => null, 'description' => null]]]);
    $updateValidator = Validator::make($update->all(), $update->rules());
    $update->withValidator($updateValidator);

    expect($updateValidator->fails())->toBeTrue();
    expect($updateValidator->errors()->keys())->toContain('translations.en.title');
    expect($updateValidator->errors()->keys())->not->toContain('translations.en.description');
});
