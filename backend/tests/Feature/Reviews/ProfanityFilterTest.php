<?php

use App\Domains\Reviews\Services\ProfanityFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('flags review containing profanity keyword', function () {
    $service = new ProfanityFilterService();
    $matches = $service->scan('This is a shit tour');

    expect($matches)->not->toBeEmpty();
    expect($matches)->toContain('shit');
});

it('does not flag clean review', function () {
    $service = new ProfanityFilterService();
    $matches = $service->scan('Amazing tour, highly recommended!');

    expect($matches)->toBeEmpty();
});

it('matches case-insensitively', function () {
    $service = new ProfanityFilterService();
    $matches = $service->scan('This is SHIT!');

    expect($matches)->toContain('shit');
});

it('matches word boundaries only', function () {
    $service = new ProfanityFilterService();

    $matches = $service->scan('classic tour');
    expect($matches)->toBeEmpty();

    $matches = $service->scan('This is ass');
    expect($matches)->toContain('ass');
});

it('handles null and empty input', function () {
    $service = new ProfanityFilterService();

    expect($service->scan(null))->toBeEmpty();
    expect($service->scan(''))->toBeEmpty();
    expect($service->scan('   '))->toBeEmpty();
});
