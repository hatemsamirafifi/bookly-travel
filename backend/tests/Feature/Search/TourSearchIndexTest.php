<?php

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

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

it('exposes the rating contract on the tour detail endpoint', function () {
    $tour = makeSearchableTour('published', 'contracted-tour');
    addTranslation($tour, 'en', 'Contracted Tour');

    getJson("/api/public/tours/contracted-tour?locale=en")
        ->assertOk()
        ->assertJsonPath('data.slug', 'contracted-tour')
        ->assertJsonStructure([
            'data' => [
                'rating' => ['average', 'count'],
                'reviews' => ['average_rating', 'count', 'distribution'],
            ],
        ]);
});