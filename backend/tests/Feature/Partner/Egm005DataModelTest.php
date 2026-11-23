<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Resources\PartnerBookingResource;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\assertDatabaseHas;

/**
 * EGM-005 Slice S1 — Contract & Data Model.
 *
 * Verifies the additive migrations landed as designed (design §3.2) and that
 * the model surface (TourDeparture, PricingTier, Booking, TourDraft) exposes
 * the D1–D5 contract: draft enhancement fields, explicit departures with
 * independent capacity and cutoffs, option-scoped tiered pricing in cents,
 * and booking-level departure / tier / snapshot persistence.
 */
uses(RefreshDatabase::class);

if (! function_exists('egm005Tour')) {
    function egm005Tour(?Partner $partner = null): Tour
    {
        $partner ??= Partner::create([
            'user_id' => User::factory()->partner()->create()->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(['slug' => 'egm005-wine'], ['name' => 'Wine & Food']);

        return Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'egm005-' . uniqid(),
            'source_locale' => 'en',
            'location' => 'Florence, Italy',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 8000,
            'status' => 'published',
        ]);
    }
}

if (! function_exists('egm005Option')) {
    function egm005Option(Tour $tour, array $overrides = []): TourOption
    {
        return TourOption::create(array_merge([
            'tour_id' => $tour->id,
            'title' => 'Morning small group',
            'guide_languages' => ['en'],
            'price_amount' => 8000,
            'group_size_max' => 10,
            'start_time' => '09:00:00',
            'status' => TourOption::STATUS_ACTIVE,
        ], $overrides));
    }
}

if (! function_exists('egm005Departure')) {
    function egm005Departure(Tour $tour, TourOption $option, array $overrides = []): TourDeparture
    {
        return TourDeparture::create(array_merge([
            'tour_id' => $tour->id,
            'tour_option_id' => $option->id,
            'starts_at' => now()->addDays(7)->setTime(8, 0, 0),
            'timezone' => 'Europe/Rome',
            'local_date' => now()->addDays(7)->format('Y-m-d'),
            'local_time' => '09:00:00',
            'capacity' => 10,
            'booking_cutoff_hours' => 2,
            'status' => TourDeparture::STATUS_ACTIVE,
        ], $overrides));
    }
}

if (! function_exists('egm005Booking')) {
    function egm005Booking(
        Tour $tour,
        TourDeparture $departure,
        int $participantCount,
        string $status,
        ?Carbon $pendingExpiresAt = null,
    ): Booking {
        static $sequence = 0;
        $sequence++;

        return Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => User::factory()->traveler()->create()->id,
            'tour_id' => $tour->id,
            'tour_date' => $departure->local_date,
            'participant_count' => $participantCount,
            'price_per_person' => 8000,
            'total_price' => 8000 * $participantCount,
            'currency' => 'EUR',
            'status' => $status,
            'tour_departure_id' => $departure->id,
            'pending_expires_at' => $pendingExpiresAt,
        ]);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-17 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('persists the EGM-005 draft enhancement fields with a language-first default step', function () {
    $partner = makePartner();
    $tour = egm005Tour($partner);

    $draft = TourDraft::create([
        'tour_id' => $tour->id,
        'partner_id' => $partner->id,
        'payload' => ['title' => 'Borrador en curso'],
        'status' => 'draft',
        'creation_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        'concurrency_token' => str_repeat('a', 64),
        'current_step' => 'options',
        'source_locale' => 'es',
    ]);

    assertDatabaseHas('tour_drafts', [
        'id' => $draft->id,
        'creation_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        'concurrency_token' => str_repeat('a', 64),
        'current_step' => 'options',
        'source_locale' => 'es',
    ]);

    // Unselected source language stays null (language-first unselected state)
    // and a draft saved without a step defaults to the language step.
    $bare = TourDraft::create([
        'tour_id' => $tour->id,
        'partner_id' => $partner->id,
        'payload' => ['title' => 'New draft'],
        'status' => 'draft',
    ]);

    $bare = $bare->fresh();
    expect($bare->creation_id)->toBeNull()
        ->and($bare->concurrency_token)->toBeNull()
        ->and($bare->source_locale)->toBeNull()
        ->and($bare->current_step)->toBe('language');
});

it('creates tour_departures with option-scoped uniqueness and intact relationships', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);
    $optionB = egm005Option($tour, ['title' => 'Sunset with sommelier', 'start_time' => '14:00:00']);

    $departure = egm005Departure($tour, $option, [
        'starts_at' => '2026-12-01 08:00:00',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
    ]);

    // Same option at a different instant is fine; a different option at the
    // same instant is fine; the same (option, starts_at) pair is not.
    TourDeparture::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'starts_at' => '2026-12-08 08:00:00',
        'timezone' => 'Europe/Rome',
        'local_date' => '2026-12-08',
        'local_time' => '09:00:00',
        'capacity' => 10,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);
    TourDeparture::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $optionB->id,
        'starts_at' => '2026-12-01 08:00:00',
        'timezone' => 'Europe/Rome',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
        'capacity' => 8,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);

    // The (option, starts_at) pair is unique at the constraint level — verify
    // the index definition rather than via an insert, because on pgsql a
    // constraint violation aborts the whole test transaction.
    $uniqueIndexes = collect(Schema::getIndexes('tour_departures'))
        ->filter(fn ($index) => $index['unique'] && ! $index['primary'])
        ->map(fn ($index) => $index['columns'])->values();
    expect($uniqueIndexes)->toContain(['tour_option_id', 'starts_at']);

    // Relationships resolve to the owning tour and option.
    expect($departure->tour->id)->toBe($tour->id)
        ->and($departure->tourOption->id)->toBe($option->id)
        ->and($option->departures()->count())->toBe(2)
        ->and($optionB->departures()->count())->toBe(1);

    // Cutoff and timezone defaults from the migration.
    expect($departure->booking_cutoff_hours)->toBe(2)
        ->and($departure->timezone)->toBe('Europe/Rome')
        ->and(TourDeparture::query()->active()->count())->toBe(3)
        ->and(TourDeparture::query()->where('status', 'active')->count())->toBe(3);

    // FK cascade: deleting the option drops its departure rows.
    $option->delete();
    expect(TourDeparture::query()->where('tour_option_id', $option->id)->count())->toBe(0)
        ->and($optionB->departures()->count())->toBe(1);
});

it('computes bookability from status, cutoff and independent per-departure capacity', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);
    $other = egm005Option($tour, ['title' => 'Other departure']);

    $departure = egm005Departure($tour, $option, [
        'starts_at' => '2026-12-01 08:00:00',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
        'capacity' => 10,
        'booking_cutoff_hours' => 2,
    ]);

    // Empty departure: fully bookable up to capacity.
    expect($departure->reservedCapacity())->toBe(0)
        ->and($departure->remainingCapacity())->toBe(10)
        ->and($departure->isBookable(10))->toBeTrue()
        ->and($departure->isBookable(11))->toBeFalse();

    // Confirmed + completed count unconditionally.
    egm005Booking($tour, $departure, 4, Booking::STATUS_CONFIRMED);
    egm005Booking($tour, $departure, 1, Booking::STATUS_COMPLETED);

    // Pending payment with a live hold counts; an expired hold releases.
    egm005Booking($tour, $departure, 2, Booking::STATUS_PENDING_PAYMENT, now()->addHour());
    egm005Booking($tour, $departure, 3, Booking::STATUS_PENDING_PAYMENT, now()->subHour());

    // Cancelled / no-show / expired statuses never hold a seat.
    egm005Booking($tour, $departure, 5, Booking::STATUS_CANCELLED);

    expect($departure->reservedCapacity())->toBe(7)
        ->and($departure->remainingCapacity())->toBe(3)
        ->and($departure->isBookable(3))->toBeTrue()
        ->and($departure->isBookable(4))->toBeFalse();

    // Capacity is independent: a fully filled sibling departure leaves this
    // one untouched (D4 — no shared pools).
    $sibling = egm005Departure($tour, $other, [
        'starts_at' => '2026-12-02 08:00:00',
        'local_date' => '2026-12-02',
        'local_time' => '09:00:00',
        'capacity' => 2,
    ]);
    egm005Booking($tour, $sibling, 2, Booking::STATUS_CONFIRMED);
    expect($sibling->isBookable(1))->toBeFalse()
        ->and($departure->remainingCapacity())->toBe(3);
});

it('refuses bookings once the cutoff window has passed or the departure is inactive', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);

    // Inside the 2-hour cutoff window: starts in 1h, cutoff 2h → not bookable.
    $insideCutoff = egm005Departure($tour, $option, [
        'starts_at' => now()->addHour(),
        'local_date' => now()->addHour()->format('Y-m-d'),
        'local_time' => now()->addHour()->format('H:i:s'),
        'booking_cutoff_hours' => 2,
    ]);

    // Past the start entirely → not bookable.
    $past = egm005Departure($tour, $option, [
        'starts_at' => now()->subDay(),
        'local_date' => now()->subDay()->format('Y-m-d'),
        'local_time' => '09:00:00',
    ]);

    $cancelled = egm005Departure($tour, $option, [
        'starts_at' => now()->addDays(30),
        'local_date' => now()->addDays(30)->format('Y-m-d'),
        'local_time' => '09:00:00',
        'status' => TourDeparture::STATUS_CANCELLED,
    ]);

    expect($insideCutoff->isBookable())->toBeFalse()
        ->and($past->isBookable())->toBeFalse()
        ->and($cancelled->isBookable())->toBeFalse();

    // A 0-hour cutoff departure starting now is still bookable.
    $imminent = egm005Departure($tour, $option, [
        'starts_at' => now()->addSeconds(1),
        'local_date' => now()->format('Y-m-d'),
        'local_time' => now()->format('H:i:s'),
        'booking_cutoff_hours' => 0,
    ]);
    expect($imminent->isBookable())->toBeTrue();
});

it('floors remaining capacity at zero when holds exceed the departure capacity', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);
    $departure = egm005Departure($tour, $option, [
        'starts_at' => '2026-12-01 08:00:00',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
        'capacity' => 2,
    ]);

    egm005Booking($tour, $departure, 2, Booking::STATUS_CONFIRMED);
    egm005Booking($tour, $departure, 2, Booking::STATUS_PENDING_PAYMENT, now()->addHour());

    expect($departure->reservedCapacity())->toBe(4)
        ->and($departure->remainingCapacity())->toBe(0)
        ->and($departure->isBookable(1))->toBeFalse();
});

it('scopes pricing tiers to options with participant ranges and authoritative cents', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);

    // Design §5 table: 1 → 1000¢, 2 → 900¢, 3–4 → 800¢, 5+ → 700¢ (open-ended).
    $solo = PricingTier::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'name' => 'Solo',
        'price' => 10.00,
        'price_amount' => 1000,
        'currency' => 'EUR',
        'min_participants' => 1,
        'max_participants' => 1,
        'sort_order' => 0,
    ]);
    PricingTier::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'name' => 'Couple',
        'price' => 9.00,
        'price_amount' => 900,
        'currency' => 'EUR',
        'min_participants' => 2,
        'max_participants' => 2,
        'sort_order' => 1,
    ]);
    $group = PricingTier::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'name' => 'Standard Group',
        'price' => 8.00,
        'price_amount' => 800,
        'currency' => 'EUR',
        'min_participants' => 3,
        'max_participants' => 4,
        'sort_order' => 2,
    ]);
    PricingTier::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'name' => 'Large Group',
        'price' => 7.00,
        'price_amount' => 700,
        'currency' => 'EUR',
        'min_participants' => 5,
        'max_participants' => null, // open-ended top tier
        'sort_order' => 3,
    ]);

    // Option-scoped list is ordered by sort_order then min_participants.
    $ordered = $option->pricingTiers()->get();
    expect($ordered)->toHaveCount(4)
        ->and($ordered->pluck('name')->all())->toBe(['Solo', 'Couple', 'Standard Group', 'Large Group'])
        ->and($ordered->last()->max_participants)->toBeNull();

    // Range resolution: min <= count <= max (null max = unbounded).
    expect($solo->min_participants)->toBe(1)
        ->and($group->min_participants <= 3 && (3 <= ($group->max_participants ?? PHP_INT_MAX)))->toBeTrue()
        ->and(PricingTier::query()->where('tour_option_id', $option->id)->count())->toBe(4);
    $inRange = fn (PricingTier $tier, int $n) => $tier->min_participants <= $n && $n <= ($tier->max_participants ?? PHP_INT_MAX);
    expect($inRange($solo, 2))->toBeFalse()
        ->and($inRange($group, 4))->toBeTrue()
        ->and($inRange($group, 5))->toBeFalse()
        ->and($inRange($ordered->last(), 10))->toBeTrue();

    // Authoritative unit price is the integer cents column; the legacy
    // decimal `price` is only a fallback when price_amount is unset.
    expect($group->unitPriceCents())->toBe(800);

    $legacy = PricingTier::create([
        'tour_id' => $tour->id,
        'name' => 'Legacy tour-level',
        'price' => 12.34,
        'min_participants' => 1,
    ]);
    expect($legacy->tour_option_id)->toBeNull()
        ->and($legacy->price_amount)->toBeNull()
        ->and($legacy->currency)->toBe('EUR')
        ->and($legacy->sort_order)->toBe(0)
        ->and($legacy->unitPriceCents())->toBe(1234)
        ->and($option->pricingTiers()->count())->toBe(4); // legacy tier not option-scoped
});

it('persists booking departure, pricing tier and the immutable pricing snapshot', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour);
    $departure = egm005Departure($tour, $option, [
        'starts_at' => '2026-12-01 08:00:00',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
    ]);

    $tier = PricingTier::create([
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'name' => 'Standard Group',
        'price' => 8.00,
        'price_amount' => 800,
        'currency' => 'EUR',
        'min_participants' => 3,
        'max_participants' => 4,
    ]);

    $snapshot = [
        'pricing_mode' => 'tiered_per_person',
        'tier_id' => $tier->id,
        'tier_name' => 'Standard Group',
        'min_participants' => 3,
        'max_participants' => 4,
        'unit_price_cents' => 800,
        'total_cents' => 2400,
        'currency' => 'EUR',
        'participant_count' => 3,
    ];

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => User::factory()->traveler()->create()->id,
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'tour_date' => $departure->local_date,
        'participant_count' => 3,
        'price_per_person' => 800,
        'total_price' => 2400,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'tour_departure_id' => $departure->id,
        'pricing_tier_id' => $tier->id,
        'pricing_snapshot' => $snapshot,
    ]);

    assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'tour_departure_id' => $departure->id,
        'pricing_tier_id' => $tier->id,
    ]);

    // Snapshot round-trips through the array cast and the live relations
    // resolve; the departure's capacity accounting sees the booking.
    $booking = $booking->fresh();
    expect($booking->departure->id)->toBe($departure->id)
        ->and($booking->pricingTier->id)->toBe($tier->id)
        ->and($booking->pricing_snapshot)->toEqual($snapshot)
        ->and($departure->bookings()->count())->toBe(1)
        ->and($departure->reservedCapacity())->toBe(3)
        ->and($departure->remainingCapacity())->toBe(7);

    // A snapshot survives the live tier being re-priced: the booking keeps
    // what the traveler was sold.
    $tier->update(['price_amount' => 850, 'price' => 8.50]);
    $booking = $booking->fresh();
    expect($booking->pricing_snapshot['unit_price_cents'])->toBe(800)
        ->and($booking->pricingTier->unitPriceCents())->toBe(850);

    // Legacy bookings with no departure keep working and stay outside the
    // departure capacity accounting.
    $legacy = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => User::factory()->traveler()->create()->id,
        'tour_id' => $tour->id,
        'tour_date' => '2026-12-01',
        'participant_count' => 2,
        'price_per_person' => 8000,
        'total_price' => 16000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);
    expect($legacy->tour_departure_id)->toBeNull()
        ->and($legacy->pricing_tier_id)->toBeNull()
        ->and($legacy->pricing_snapshot)->toBeNull()
        ->and($legacy->departure)->toBeNull()
        ->and($legacy->pricingTier)->toBeNull()
        ->and($departure->reservedCapacity())->toBe(3);
});

it('exposes the EGM-005 column shapes declared by the migrations', function () {
    expect(Schema::hasTable('tour_departures'))->toBeTrue();

    $columns = Schema::getColumnListing('tour_departures');
    foreach ([
        'id', 'tour_id', 'tour_option_id', 'starts_at', 'timezone', 'local_date',
        'local_time', 'capacity', 'booking_cutoff_hours', 'status', 'recurrence_rule_id',
    ] as $col) {
        expect($columns)->toContain($col);
    }

    $draftColumns = Schema::getColumnListing('tour_drafts');
    foreach (['creation_id', 'creation_payload_hash', 'concurrency_token', 'current_step', 'source_locale'] as $col) {
        expect($draftColumns)->toContain($col);
    }

    $tierColumns = Schema::getColumnListing('pricing_tiers');
    foreach (['tour_option_id', 'price_amount', 'currency', 'sort_order'] as $col) {
        expect($tierColumns)->toContain($col);
    }

    $bookingColumns = Schema::getColumnListing('bookings');
    foreach (['tour_departure_id', 'pricing_tier_id', 'pricing_snapshot'] as $col) {
        expect($bookingColumns)->toContain($col);
    }
});

it('enforces Wave 3A PostgreSQL unique draft index, capacity check constraint, and booking index', function () {
    $indexes = collect(DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename IN ('tour_drafts', 'bookings')"
    ))->pluck('indexname')->all();

    expect($indexes)->toContain('tour_drafts_partner_id_creation_id_unique')
        ->and($indexes)->toContain('bookings_departure_status_expiry_idx');

    $constraints = collect(DB::select(
        "SELECT conname FROM pg_constraint WHERE conrelid = 'tour_departures'::regclass AND contype = 'c'"
    ))->pluck('conname')->all();

    expect($constraints)->toContain('tour_departures_capacity_check');

    $partner1 = makePartner();
    $partner2 = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $sharedUuid = '8b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    // Multiple NULL creation_ids for the same partner remain permitted.
    TourDraft::create([
        'partner_id' => $partner1->id,
        'payload' => ['title' => 'Null creation 1'],
        'status' => 'draft',
    ]);
    TourDraft::create([
        'partner_id' => $partner1->id,
        'payload' => ['title' => 'Null creation 2'],
        'status' => 'draft',
    ]);

    // Same non-null creation_id across DIFFERENT partners succeeds.
    TourDraft::create([
        'partner_id' => $partner1->id,
        'creation_id' => $sharedUuid,
        'payload' => ['title' => 'Partner 1 draft'],
        'status' => 'draft',
    ]);
    TourDraft::create([
        'partner_id' => $partner2->id,
        'creation_id' => $sharedUuid,
        'payload' => ['title' => 'Partner 2 draft'],
        'status' => 'draft',
    ]);

    // Duplicate non-null (partner_id, creation_id) raises SQLSTATE 23505 inside savepoint.
    $dupException = null;
    try {
        DB::transaction(fn () => TourDraft::create([
            'partner_id' => $partner1->id,
            'creation_id' => $sharedUuid,
            'payload' => ['title' => 'Duplicate partner 1 draft'],
            'status' => 'draft',
        ]));
    } catch (QueryException $e) {
        $dupException = $e;
    }
    expect($dupException)->toBeInstanceOf(QueryException::class)
        ->and($dupException->errorInfo[0] ?? null)->toBe('23505');

    // tour_departures CHECK (capacity >= 1) rejects 0 and negative values (SQLSTATE 23514).
    $tour = egm005Tour($partner1);
    $option = egm005Option($tour);

    $zeroCapException = null;
    try {
        DB::transaction(fn () => egm005Departure($tour, $option, ['capacity' => 0]));
    } catch (QueryException $e) {
        $zeroCapException = $e;
    }
    expect($zeroCapException)->toBeInstanceOf(QueryException::class)
        ->and($zeroCapException->errorInfo[0] ?? null)->toBe('23514');

    $negCapException = null;
    try {
        DB::transaction(fn () => egm005Departure($tour, $option, [
            'starts_at' => now()->addDays(8)->setTime(8, 0, 0),
            'capacity' => -2,
        ]));
    } catch (QueryException $e) {
        $negCapException = $e;
    }
    expect($negCapException)->toBeInstanceOf(QueryException::class)
        ->and($negCapException->errorInfo[0] ?? null)->toBe('23514');
});

it('serializes departure, pricing snapshot, and option in PartnerBookingResource while keeping legacy fallback', function () {
    $tour = egm005Tour();
    $option = egm005Option($tour, ['title' => 'EGM005 Scenic Option']);
    $departure = egm005Departure($tour, $option, ['local_time' => '14:30:00']);

    $snapshot = [
        'mode' => 'per_person',
        'tier_id' => 99,
        'tier_name' => 'Small Group Tier',
        'unit_price_cents' => 8000,
        'total_price_cents' => 16000,
        'currency' => 'EUR',
    ];

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => User::factory()->traveler()->create()->id,
        'tour_id' => $tour->id,
        'tour_option_id' => $option->id,
        'tour_date' => $departure->local_date,
        'participant_count' => 2,
        'price_per_person' => 8000,
        'total_price' => 16000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'tour_departure_id' => $departure->id,
        'pricing_snapshot' => $snapshot,
    ]);

    $resource = (new PartnerBookingResource($booking->fresh(['tour.translations', 'departure'])))->toArray(request());

    expect($resource['tour_departure_id'])->toBe($departure->id)
        ->and($resource['departure'])->not->toBeNull()
        ->and($resource['departure']['id'])->toBe($departure->id)
        ->and($resource['tour_time'])->toBe('14:30')
        ->and($resource['participants'][0]['tier_id'])->toBe(99)
        ->and($resource['participants'][0]['tier_name'])->toBe('Small Group Tier')
        ->and($resource['participants'][0]['price_per_person'])->toEqual(80)
        ->and($resource['pricing_snapshot'])->toEqual($snapshot);

    // Legacy booking without departure or snapshot
    $legacy = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => User::factory()->traveler()->create()->id,
        'tour_id' => $tour->id,
        'tour_date' => '2026-12-01',
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $legacyResource = (new PartnerBookingResource($legacy->fresh(['tour.translations'])))->toArray(request());

    expect($legacyResource['tour_departure_id'])->toBeNull()
        ->and($legacyResource['departure'])->toBeNull()
        ->and($legacyResource['participants'][0]['tier_id'])->toBe('standard')
        ->and($legacyResource['participants'][0]['tier_name'])->toBe('Standard')
        ->and($legacyResource['pricing_snapshot'])->toBeNull();
});
