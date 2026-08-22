<?php

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 | Comprehensive availability-resolution regression test (spec 006 FR-003/FR-036).
 |
 | Exercises the hardest logical combination the search availability engine
 | has to resolve: a recurring rule + a specific_date rule + a block exception,
 | all interacting on one tour. Verifies precedence (block > any rule),
 | deduplication (a date produced by two rules appears once), chronological
 | ordering, and that shouldBeSearchable() tracks the final computed availability.
 |
 | "Today" is frozen to 2026-07-06 (a Monday) so the horizon and day-of-week
 | math is fully deterministic. All dates below are derived from that anchor:
 |   2026-07-06, 2026-07-13, 2026-07-20, 2026-07-27, 2026-08-03  -> Mondays
 |   2026-07-15                                                  -> Wednesday
 */

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow(null);
});

/**
 * Build a published, priced tour carrying the rule set both scenarios share:
 *   - one recurring rule, every Monday (dayOfWeek = 1)
 *   - one specific_date rule on 2026-07-15 (Wednesday — only the specific
 *     rule can yield it, so it isolates "specific_date contributes")
 *   - one specific_date rule on 2026-07-13 (Monday — overlaps the recurring
 *     rule, so it is the dedup probe)
 *
 * Relations are reloaded because Tour::create fires the Scout `saved`
 * observer, which calls shouldBeSearchable()->upcomingAvailableDates() and
 * caches an empty availabilityRules/availabilityExceptions collection on the
 * in-memory instance before the rules below exist.
 */
function regressionTourWithRules(): Tour
{
    $tour = makeSearchableTour('published', 'regression-availability-tour');

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [1], // every Monday
        'capacity' => 10,
    ]);

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => '2026-07-15', // Wednesday — unique to this rule
        'capacity' => 10,
    ]);

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => '2026-07-13', // Monday — overlaps the recurring rule
        'capacity' => 10,
    ]);

    $tour->load(['availabilityRules', 'availabilityExceptions']);

    return $tour;
}

it('resolves recurring + specific_date + block precedence with dedup and ordering', function () {
    $tour = regressionTourWithRules();

    // Block one date produced by the recurring rule (Monday 2026-07-20). The
    // exception must override the rule that produced it.
    AvailabilityException::create([
        'tour_id' => $tour->id,
        'exception_type' => 'block',
        'date' => '2026-07-20',
    ]);
    $tour->load(['availabilityRules', 'availabilityExceptions']);

    $dates = $tour->upcomingAvailableDates(30, 60);

    // Final expected list: the five recurring Mondays (07-06, 07-13, 07-20,
    // 07-27, 08-03) minus the blocked 07-20, plus the specific 07-15, with the
    // overlapping specific 07-13 collapsed onto the recurring Monday (dedup),
    // chronologically sorted.
    expect($dates)->toBe([
        '2026-07-06',
        '2026-07-13',
        '2026-07-15',
        '2026-07-27',
        '2026-08-03',
    ]);

    // Recurring rule contributes its Mondays.
    expect($dates)->toContain('2026-07-06')
        ->and($dates)->toContain('2026-07-27')
        ->and($dates)->toContain('2026-08-03');

    // specific_date rule contributes the Wednesday the recurring rule cannot.
    expect($dates)->toContain('2026-07-15');

    // Availability exception overrides the rule that produced the date.
    expect($dates)->not->toContain('2026-07-20');

    // Dedup: 2026-07-13 is produced by BOTH the recurring rule and a
    // specific_date rule, yet appears exactly once.
    expect(array_count_values($dates)['2026-07-13'] ?? 0)->toBe(1);

    // Chronological ordering is preserved (independent of the hardcoded list).
    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted);

    // No duplicate dates in the final list.
    expect($dates)->toHaveCount(count(array_unique($dates)));

    // At least one valid date remains, so the tour is still searchable.
    expect($tour->shouldBeSearchable())->toBeTrue();
});

it('returns an empty list and is not searchable when every produced date is blocked', function () {
    $tour = regressionTourWithRules();

    // shouldBeSearchable() resolves availability over the DEFAULT 90-day
    // horizon (hasUpcomingAvailability() calls upcomingAvailableDates() with
    // no arguments), so we must block every Monday the recurring rule yields in
    // that full window — not just the first 30 days — plus the specific
    // Wednesday. 07-13 is both a recurring Monday and a specific_date, so
    // blocking it overrides both rules at once.
    $blockDates = [];
    $anchor = Carbon::parse('2026-07-06');
    for ($i = 0; $i < 90; $i++) {
        $day = (clone $anchor)->addDays($i);
        if ($day->dayOfWeek === 1) { // every Monday in the 90-day window
            $blockDates[] = $day->toDateString();
        }
    }
    $blockDates[] = '2026-07-15'; // the specific Wednesday

    foreach ($blockDates as $date) {
        AvailabilityException::create([
            'tour_id' => $tour->id,
            'exception_type' => 'block',
            'date' => $date,
        ]);
    }
    $tour->load(['availabilityRules', 'availabilityExceptions']);

    expect($tour->upcomingAvailableDates())->toBe([])
        ->and($tour->shouldBeSearchable())->toBeFalse();
});
