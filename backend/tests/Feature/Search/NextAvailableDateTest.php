<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Search\Actions\GetTourDetailAction;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// makeSearchableTour() and addTranslation() are shared from tests/Pest.php.

it('exposes a strictly-future next_available_date on the tour detail payload', function () {
    $tour = makeSearchableTour('published', 'next-date-fixture-tour');
    addTranslation($tour, 'en', 'Next Date Fixture Tour');

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);

    $tour = Tour::with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])
        ->where('slug', 'next-date-fixture-tour')->firstOrFail();

    // upcomingAvailableDates() is the search-index approximation and may
    // legitimately include today...
    $dates = $tour->upcomingAvailableDates();
    expect($dates)->not->toBeEmpty();

    // ...but the booking deep-link hint must satisfy CreateBookingAction's
    // strictly-future rule, so the UI Book Now link can never carry a date
    // the booking API would reject with 422 "Tour date must be in the future."
    $result = app(GetTourDetailAction::class)->execute('next-date-fixture-tour', 'en');
    $next = $result['data']['availability']['next_available_date'];

    expect($next)->not->toBeNull()
        ->and($next)->toBeGreaterThan(Carbon::today()->toDateString())
        ->and($next)->toBeIn($dates);
});

it('returns a null next_available_date when only today is available', function () {
    $tour = makeSearchableTour('published', 'today-only-tour');
    addTranslation($tour, 'en', 'Today Only Tour');

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => Carbon::today()->toDateString(),
        'capacity' => 10,
    ]);

    $result = app(GetTourDetailAction::class)->execute('today-only-tour', 'en');

    expect($result['data']['availability']['next_available_date'])->toBeNull()
        ->and($result['data']['availability']['available_dates'])->not->toBeEmpty();
});
