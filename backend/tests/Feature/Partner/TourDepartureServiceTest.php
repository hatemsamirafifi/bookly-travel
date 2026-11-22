<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourDepartureService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['slug' => 'dept-cat'], ['name' => 'Departure Category']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'departure-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    // Create recurring rule: operates Mon-Fri (1,2,3,4,5) at 09:00:00
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '09:00:00',
        'start_date' => Carbon::parse('2026-12-01')->toDateString(),
        'end_date' => Carbon::parse('2027-06-01')->toDateString(),
        'capacity' => 10,
    ]);

    $this->option = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Morning Option',
        'price_amount' => 5000,
        'group_size_max' => 8,
        'start_time' => '09:00:00',
        'weekdays' => [1, 2, 3, 4, 5],
        'status' => 'active',
    ]);

    $this->departureService = app(TourDepartureService::class);
});

test('it generates explicit departures bounded by horizon and filters non-operating days', function () {
    // 2026-12-01 is a Tuesday (operating)
    // 2026-12-05 is a Saturday (non-operating)
    // 2026-12-06 is a Sunday (non-operating)
    // Range: 2026-12-01 to 2026-12-07 (7 days: Tue, Wed, Thu, Fri, Sat, Sun, Mon) -> 5 operating days
    $start = Carbon::parse('2026-12-01');
    $end = Carbon::parse('2026-12-07');

    $departures = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome');

    expect($departures)->toHaveCount(5);

    foreach ($departures as $dep) {
        expect($dep->tour_id)->toBe($this->tour->id)
            ->and($dep->tour_option_id)->toBe($this->option->id)
            ->and($dep->capacity)->toBe(8)
            ->and($dep->timezone)->toBe('Europe/Rome')
            ->and($dep->local_time)->toBe('09:00:00')
            ->and($dep->status)->toBe('active');
    }

    // Idempotent generation: running again creates 0 new rows and updates existing
    $again = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome');
    expect($again)->toHaveCount(5)
        ->and(TourDeparture::where('tour_option_id', $this->option->id)->count())->toBe(5);
});

test('it detects blackout exceptions and applies capacity overrides', function () {
    // Blackout on 2026-12-02 (Wednesday)
    AvailabilityException::create([
        'tour_id' => $this->tour->id,
        'exception_type' => 'closed',
        'date' => '2026-12-02',
    ]);

    // Capacity override on 2026-12-03 (Thursday) to 15
    AvailabilityException::create([
        'tour_id' => $this->tour->id,
        'exception_type' => 'capacity',
        'date' => '2026-12-03',
        'capacity' => 15,
    ]);

    $start = Carbon::parse('2026-12-01');
    $end = Carbon::parse('2026-12-04'); // Tue, Wed, Thu, Fri

    $departures = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome');

    // Wednesday 2026-12-02 was skipped due to blackout closed exception
    expect($departures)->toHaveCount(3);
    expect($departures->pluck('local_date')->toArray())->not->toContain('2026-12-02');

    // Thursday 2026-12-03 has capacity = 15
    $thu = $departures->firstWhere('local_date', '2026-12-03');
    expect($thu)->not->toBeNull()
        ->and($thu->capacity)->toBe(15);
});

test('it enforces D4 Rule 10 rejecting capacity reduction below reserved seats', function () {
    $start = Carbon::parse('2026-12-01');
    $end = Carbon::parse('2026-12-01');

    $departures = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome');
    $departure = $departures->first();

    // Create a confirmed booking consuming 5 seats on this departure
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->partnerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $departure->id,
        'tour_date' => '2026-12-01',
        'start_time' => '09:00:00',
        'participant_count' => 5,
        'price_per_person' => 5000,
        'total_price' => 25000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => 'idemp-cap-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    expect($departure->reservedCapacity())->toBe(5)
        ->and($departure->remainingCapacity())->toBe(3);

    // Reducing to 5 or higher is allowed
    $updated = $this->departureService->updateDeparture($departure, ['capacity' => 6]);
    expect($updated->capacity)->toBe(6);

    // Reducing below 5 must be rejected
    expect(fn () => $this->departureService->updateDeparture($departure, ['capacity' => 4]))
        ->toThrow(ValidationException::class);
});

test('it handles DST transitions safely when generating departure instants', function () {
    // Sunday 2027-03-28 in Europe/Rome: Clocks spring forward 1 hour at 02:00 -> 03:00
    // Test that a departure at 09:00 local time generates the exact correct UTC instant
    $start = Carbon::parse('2027-03-26');
    $end = Carbon::parse('2027-03-30');

    $departures = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome');

    foreach ($departures as $dep) {
        expect($dep->starts_at)->toBeInstanceOf(Carbon::class);
        // Authoritative UTC instant converted back to Europe/Rome matches local_date and local_time
        $localReconstructed = $dep->starts_at->copy()->setTimezone('Europe/Rome');
        expect($localReconstructed->format('Y-m-d'))->toBe($dep->local_date)
            ->and($localReconstructed->format('H:i:s'))->toBe($dep->local_time);
    }
});

test('it honors block and capacity_override exceptions created via AvailabilityController HTTP endpoint', function () {
    $token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->postJson("/api/partner/tours/{$this->tour->id}/availability/exceptions", [
        'exception_type' => 'block',
        'date' => '2026-12-02',
        'note' => 'Maintenance closure',
    ])->assertStatus(201);

    $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->postJson("/api/partner/tours/{$this->tour->id}/availability/exceptions", [
        'exception_type' => 'capacity_override',
        'date' => '2026-12-03',
        'capacity' => 14,
        'note' => 'Larger group capacity',
    ])->assertStatus(201);

    $this->tour->refresh();

    $departures = $this->departureService->generateForOption(
        $this->option,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2026-12-04'),
        'Europe/Rome'
    );

    expect($departures)->toHaveCount(3);
    expect($departures->pluck('local_date')->toArray())->not->toContain('2026-12-02');

    $thu = $departures->firstWhere('local_date', '2026-12-03');
    expect($thu)->not->toBeNull()
        ->and($thu->capacity)->toBe(14);
});

test('updateDeparture rejects zero capacity and DST spring-forward gap keeps local_time synchronized with starts_at', function () {
    $start = Carbon::parse('2026-12-01');
    $end = Carbon::parse('2026-12-01');
    $dep = $this->departureService->generateForOption($this->option, $start, $end, 'Europe/Rome')->first();

    expect(fn () => $this->departureService->updateDeparture($dep, ['capacity' => 0]))
        ->toThrow(ValidationException::class);

    // Add daily rule + Sunday-operating option at 02:30 to hit Europe/Rome spring-forward gap (2027-03-28 02:00->03:00)
    $this->tour->availabilityRules()->delete();
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '02:30:00',
        'start_date' => '2027-03-01',
        'end_date' => '2027-04-01',
        'capacity' => 10,
    ]);
    $dstOption = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Early DST Slot',
        'price_amount' => 5000,
        'group_size_max' => 8,
        'start_time' => '02:30:00',
        'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active',
    ]);
    $this->tour->refresh();

    $dstDeps = $this->departureService->generateForOption(
        $dstOption,
        Carbon::parse('2027-03-28'),
        Carbon::parse('2027-03-28'),
        'Europe/Rome'
    );

    expect($dstDeps)->toHaveCount(1);
    $dstDep = $dstDeps->first();
    $projected = $dstDep->starts_at->copy()->setTimezone('Europe/Rome');
    expect($projected->format('Y-m-d'))->toBe($dstDep->local_date)
        ->and($projected->format('H:i:s'))->toBe($dstDep->local_time)
        ->and($dstDep->local_time)->toBe('03:30:00');
});
