<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Models\Tour;
use Illuminate\Support\Carbon;

/**
 * DB-free unit-style coverage for Tour::upcomingAvailableDates() and
 * Tour::shouldBeSearchable(). Relations are set via setRelation() so the
 * availability derivation is exercised without hitting the database.
 *
 * Date-cast attributes (start_date/end_date/date) are intentionally not
 * exercised here — Eloquent's date cast resolves the DB connection to obtain
 * the date format, so those paths are covered by the DB-backed
 * TourSearchIndexTest instead.
 */
it('derives upcoming available dates from a recurring daily rule', function () {
    $tour = new Tour(['status' => 'published', 'price_amount' => 5000]);
    $tour->setRelation('availabilityRules', collect([
        new AvailabilityRule(['rule_type' => 'recurring', 'days_of_week' => [0, 1, 2, 3, 4, 5, 6]]),
    ]));
    $tour->setRelation('availabilityExceptions', collect([]));

    $dates = $tour->upcomingAvailableDates(7, 7);

    expect($dates)->toHaveCount(7)
        ->and($dates[0])->toBe(Carbon::today()->toDateString());
});

it('returns no dates when there are no availability rules', function () {
    $tour = new Tour(['status' => 'published', 'price_amount' => 5000]);
    $tour->setRelation('availabilityRules', collect([]));
    $tour->setRelation('availabilityExceptions', collect([]));

    expect($tour->upcomingAvailableDates())->toBe([]);
});

it('respects the recurring rule day-of-week filter', function () {
    $tour = new Tour(['status' => 'published', 'price_amount' => 5000]);
    $tour->setRelation('availabilityRules', collect([
        // Only Mondays (dayOfWeek = 1)
        new AvailabilityRule(['rule_type' => 'recurring', 'days_of_week' => [1]]),
    ]));
    $tour->setRelation('availabilityExceptions', collect([]));

    $dates = $tour->upcomingAvailableDates(14, 14);

    // Every returned date must be a Monday.
    foreach ($dates as $date) {
        expect(Carbon::parse($date)->dayOfWeek)->toBe(1);
    }
    expect($dates)->not->toBeEmpty();
});

it('shouldBeSearchable is true only for published + priced + available tours', function () {
    $recurring = collect([new AvailabilityRule(['rule_type' => 'recurring', 'days_of_week' => [1]])]);

    $tour = new Tour(['status' => 'published', 'price_amount' => 5000]);
    $tour->setRelation('availabilityRules', $recurring);
    $tour->setRelation('availabilityExceptions', collect([]));
    expect($tour->shouldBeSearchable())->toBeTrue();

    $draft = new Tour(['status' => 'draft', 'price_amount' => 5000]);
    $draft->setRelation('availabilityRules', $recurring);
    $draft->setRelation('availabilityExceptions', collect([]));
    expect($draft->shouldBeSearchable())->toBeFalse();

    $unpriced = new Tour(['status' => 'published', 'price_amount' => 0]);
    $unpriced->setRelation('availabilityRules', $recurring);
    $unpriced->setRelation('availabilityExceptions', collect([]));
    expect($unpriced->shouldBeSearchable())->toBeFalse();

    $noAvailability = new Tour(['status' => 'published', 'price_amount' => 5000]);
    $noAvailability->setRelation('availabilityRules', collect([]));
    $noAvailability->setRelation('availabilityExceptions', collect([]));
    expect($noAvailability->shouldBeSearchable())->toBeFalse();
});
