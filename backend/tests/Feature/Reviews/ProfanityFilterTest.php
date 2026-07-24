<?php

use App\Domains\Reviews\Services\ProfanityFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('flags review containing profanity keyword', function () {
    $service = new ProfanityFilterService;
    $matches = $service->scan('This is a shit tour');

    expect($matches)->not->toBeEmpty();
    expect($matches)->toContain('shit');
});

it('does not flag clean review', function () {
    $service = new ProfanityFilterService;
    $matches = $service->scan('Amazing tour, highly recommended!');

    expect($matches)->toBeEmpty();
});

it('matches case-insensitively', function () {
    $service = new ProfanityFilterService;
    $matches = $service->scan('This is SHIT!');

    expect($matches)->toContain('shit');
});

it('matches word boundaries only', function () {
    $service = new ProfanityFilterService;

    $matches = $service->scan('classic tour');
    expect($matches)->toBeEmpty();

    $matches = $service->scan('This is ass');
    expect($matches)->toContain('ass');
});

it('handles null and empty input', function () {
    $service = new ProfanityFilterService;

    expect($service->scan(null))->toBeEmpty();
    expect($service->scan(''))->toBeEmpty();
    expect($service->scan('   '))->toBeEmpty();
});

it('loads the tracked default keyword list with no runtime file (FR-014)', function () {
    // The default constructor relies solely on the tracked
    // resources/profanity_keywords.json (override absent in a clean clone).
    $service = new ProfanityFilterService(
        config('profanity.default_path'),
        '/nonexistent/override.json'
    );

    expect($service->scan('this is shit'))->toContain('shit');
    expect($service->scan('what an ass'))->toContain('ass');
});

it('fully replaces the default list when an override file is present', function () {
    $overridePath = tempnam(sys_get_temp_dir(), 'profanity_override');
    file_put_contents($overridePath, json_encode(['cromulent']));

    try {
        $service = new ProfanityFilterService(
            config('profanity.default_path'),
            $overridePath
        );

        // 'shit' is in the default, but the override REPLACES it → not flagged.
        expect($service->scan('this is shit'))->toBeEmpty();
        // Only the override's keyword is matched.
        expect($service->scan('cromulent tour'))->toContain('cromulent');
    } finally {
        unlink($overridePath);
    }
});

it('logs a warning when no keywords are loaded (observable, not silent)', function () {
    Log::shouldReceive('warning')->atLeast()->once();

    $service = new ProfanityFilterService(
        '/nonexistent/default.json',
        '/nonexistent/override.json'
    );

    expect($service->scan('anything at all'))->toBeEmpty();
});

it('ships a tracked, non-empty profanity keyword list (CI guard)', function () {
    $path = resource_path('profanity_keywords.json');

    expect(file_exists($path))->toBeTrue('resources/profanity_keywords.json must be tracked in the repo');

    $keywords = json_decode((string) file_get_contents($path), true);

    expect($keywords)->not->toBeEmpty()
        ->and($keywords)->toContain('shit')
        ->and($keywords)->toContain('ass');
});
