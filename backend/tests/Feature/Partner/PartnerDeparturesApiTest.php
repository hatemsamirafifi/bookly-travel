<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Jobs\MaintainDepartureHorizonJob;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourDepartureService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $this->token = $this->partnerUser->createToken('partner-a', ['partner'])->plainTextToken;

    $this->otherPartnerUser = User::factory()->partner()->create();
    $this->otherPartner = Partner::create([
        'user_id' => $this->otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $this->otherToken = $this->otherPartnerUser->createToken('partner-b', ['partner'])->plainTextToken;

    $this->traveler = User::factory()->traveler()->create();

    $category = Category::firstOrCreate(['slug' => 'api-dept-cat'], ['name' => 'API Departure Category']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'api-departures-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 12,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Colosseum Early Walk',
        'description' => str_repeat('Guided tour of historic Rome. ', 6),
        'highlights' => ['Small group'],
        'inclusions' => ['Guide'],
        'exclusions' => ['Lunch'],
        'meeting_point' => 'Colosseum Arch',
        'cancellation_policy' => 'Free cancellation up to 24h before start.',
    ]);

    $this->option = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Morning Group',
        'price_amount' => 5000,
        'group_size_max' => 10,
        'start_time' => '09:00:00',
        'weekdays' => [1, 2, 3, 4, 5],
        'status' => 'active',
    ]);

    $this->retiredOption = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Legacy Evening Slot',
        'price_amount' => 4500,
        'group_size_max' => 6,
        'start_time' => '18:00:00',
        'status' => 'retired',
    ]);

    $this->otherTour = Tour::create([
        'partner_id' => $this->otherPartner->id,
        'category_id' => $category->id,
        'slug' => 'other-partner-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Milan, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4000,
        'status' => 'published',
    ]);

    $this->otherOption = TourOption::create([
        'tour_id' => $this->otherTour->id,
        'title' => 'Other Partner Option',
        'price_amount' => 4000,
        'group_size_max' => 8,
        'start_time' => '11:00:00',
        'status' => 'active',
    ]);
});

function partnerAuthHeaders(string $token): array
{
    auth()->forgetGuards();

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ];
}

test('unauthenticated requests and cross-partner access are rejected on partner departure endpoints', function () {
    $departure = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => Carbon::now()->addDays(10)->setTime(8, 0, 0)->utc(),
        'timezone' => 'Europe/Rome',
        'local_date' => Carbon::now()->addDays(10)->toDateString(),
        'local_time' => '09:00:00',
        'capacity' => 8,
        'booking_cutoff_hours' => 2,
        'status' => 'active',
    ]);

    $this->getJson("/api/partner/tours/{$this->tour->id}/departures")->assertStatus(401);
    $this->postJson("/api/partner/tours/{$this->tour->id}/departures", [])->assertStatus(401);
    $this->postJson("/api/partner/tours/{$this->tour->id}/departures/generate", [])->assertStatus(401);
    $this->putJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}", [])->assertStatus(401);
    $this->postJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}/cancel", [])->assertStatus(401);

    // Partner B cannot inspect or mutate Partner A's tour or departure
    $this->withHeaders(partnerAuthHeaders($this->otherToken))
        ->getJson("/api/partner/tours/{$this->tour->id}/departures")
        ->assertStatus(404);

    $this->withHeaders(partnerAuthHeaders($this->otherToken))
        ->putJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}", ['capacity' => 12])
        ->assertStatus(404);

    $this->withHeaders(partnerAuthHeaders($this->otherToken))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}/cancel", [])
        ->assertStatus(404);

    // Partner A cannot target Partner B's tour_option_id on Partner A's tour
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures", [
            'tour_option_id' => $this->otherOption->id,
            'local_date' => Carbon::now()->addDays(15)->toDateString(),
            'local_time' => '09:30',
            'timezone' => 'Europe/Rome',
            'capacity' => 8,
        ])
        ->assertStatus(404);
});

test('partner creates single departure with UTC projection and enforces uniqueness, timezone, and retired option guards', function () {
    $targetDate = '2027-06-15';

    // America/New_York in June is EDT (UTC-4): 09:30 EDT === 13:30:00Z
    $created = $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures", [
            'tour_option_id' => $this->option->id,
            'local_date' => $targetDate,
            'local_time' => '09:30',
            'timezone' => 'America/New_York',
            'capacity' => 12,
            'booking_cutoff_hours' => 6,
        ]);

    $created->assertStatus(201)
        ->assertJsonPath('data.tour_option_id', $this->option->id)
        ->assertJsonPath('data.local_date', $targetDate)
        ->assertJsonPath('data.local_time', '09:30:00')
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.capacity', 12)
        ->assertJsonPath('data.booking_cutoff_hours', 6)
        ->assertJsonPath('data.reserved_seats', 0)
        ->assertJsonPath('data.available_spots', 12)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_bookable', true);

    expect(Carbon::parse($created->json('data.starts_at'))->utc()->format('Y-m-d H:i:s'))
        ->toBe('2027-06-15 13:30:00');

    // Duplicate (tour_option_id, starts_at) is rejected cleanly with 422 (never 500)
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures", [
            'tour_option_id' => $this->option->id,
            'local_date' => $targetDate,
            'local_time' => '09:30',
            'timezone' => 'America/New_York',
            'capacity' => 10,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['starts_at']);

    // Retired option is rejected with 422
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures", [
            'tour_option_id' => $this->retiredOption->id,
            'local_date' => $targetDate,
            'local_time' => '18:00',
            'timezone' => 'Europe/Rome',
            'capacity' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    // Invalid IANA timezone and capacity < 1 are rejected
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures", [
            'tour_option_id' => $this->option->id,
            'local_date' => $targetDate,
            'local_time' => '10:00',
            'timezone' => 'Mars/Olympus',
            'capacity' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['timezone', 'capacity']);
});

test('partner generate endpoint respects 180-day horizon, blackouts, idempotency, and never resurrects cancelled departures', function () {
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '09:00:00',
        'start_date' => '2027-04-01',
        'end_date' => '2027-12-31',
        'capacity' => 10,
    ]);

    AvailabilityException::create([
        'tour_id' => $this->tour->id,
        'exception_type' => 'block',
        'date' => '2027-04-06', // Tuesday
        'note' => 'Private buyout',
    ]);

    // Over-horizon request (> 180 days) rejected with 422
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/generate", [
            'start_date' => '2027-04-05',
            'end_date' => '2027-10-03', // 181 days
            'timezone' => 'Europe/Rome',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    // Valid window: Mon 2027-04-05 .. Fri 2027-04-09 (5 weekdays minus Tue blackout = 4)
    $genResponse = $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/generate", [
            'start_date' => '2027-04-05',
            'end_date' => '2027-04-09',
            'timezone' => 'Europe/Rome',
            'tour_option_id' => $this->option->id,
        ]);

    $genResponse->assertStatus(200)
        ->assertJsonPath('meta.generated_count', 4);

    $mondayId = collect($genResponse->json('data'))->firstWhere('local_date', '2027-04-05')['id'];

    // Cancel Monday departure via partner API
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/{$mondayId}/cancel", [])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');

    // Re-running generate skips cancelled Monday departure and never deletes or resurrects it
    $regenResponse = $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/generate", [
            'start_date' => '2027-04-05',
            'end_date' => '2027-04-09',
            'timezone' => 'Europe/Rome',
            'tour_option_id' => $this->option->id,
        ]);

    $regenResponse->assertStatus(200)
        ->assertJsonPath('meta.generated_count', 3);

    expect(TourDeparture::findOrFail($mondayId)->status)->toBe(TourDeparture::STATUS_CANCELLED)
        ->and(TourDeparture::where('tour_id', $this->tour->id)->count())->toBe(4);
});

test('partner update enforces D4 Rule 10 reserved seat floor, evicts public cache, and cancel guards booked departures', function () {
    $date = Carbon::now()->addDays(20)->toDateString();
    $startsAt = Carbon::parse($date . ' 09:00:00', 'Europe/Rome')->utc();

    $departure = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => $startsAt,
        'timezone' => 'Europe/Rome',
        'local_date' => $date,
        'local_time' => '09:00:00',
        'capacity' => 10,
        'booking_cutoff_hours' => 2,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);

    // 3 confirmed + 2 unexpired pending = 5 active reserved; + 4 expired pending (ignored)
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $departure->id,
        'tour_date' => $date,
        'start_time' => '09:00:00',
        'participant_count' => 3,
        'price_per_person' => 5000,
        'total_price' => 15000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => 'idemp-conf-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $departure->id,
        'tour_date' => $date,
        'start_time' => '09:00:00',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'pending_expires_at' => Carbon::now()->addMinutes(15),
        'idempotency_key' => 'idemp-pend-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $departure->id,
        'tour_date' => $date,
        'start_time' => '09:00:00',
        'participant_count' => 4,
        'price_per_person' => 5000,
        'total_price' => 20000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'pending_expires_at' => Carbon::now()->subMinute(),
        'idempotency_key' => 'idemp-exp-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    // Check initial public departure inventory
    $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from={$date}&date_to={$date}&participants=1")
        ->assertStatus(200)
        ->assertJsonPath('data.0.available_spots', 5);

    // Reducing capacity below 5 (even though 3 confirmed, 2 pending) is rejected with 422
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->putJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}", [
            'capacity' => 4,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['capacity']);

    // Increasing/setting capacity to 7 and cutoff to 12 succeeds and immediately reflects publicly
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->putJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}", [
            'capacity' => 7,
            'booking_cutoff_hours' => 12,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.capacity', 7)
        ->assertJsonPath('data.reserved_seats', 5)
        ->assertJsonPath('data.available_spots', 2)
        ->assertJsonPath('data.booking_cutoff_hours', 12);

    $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from={$date}&date_to={$date}&participants=1")
        ->assertStatus(200)
        ->assertJsonPath('data.0.available_spots', 2);

    // Booked departure cannot be cancelled without confirm_booked_cancellation=true
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}/cancel", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Explicit booked cancellation succeeds, preserves booking record, prevents edits, and hides publicly
    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}/cancel", [
            'confirm_booked_cancellation' => true,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');

    expect(Booking::where('tour_departure_id', $departure->id)->where('status', Booking::STATUS_CONFIRMED)->count())
        ->toBe(1);

    $this->withHeaders(partnerAuthHeaders($this->token))
        ->putJson("/api/partner/tours/{$this->tour->id}/departures/{$departure->id}", ['capacity' => 8])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from={$date}&date_to={$date}&participants=1")
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('MaintainDepartureHorizonJob is scheduled daily and maintains bounded departures only for eligible published tours', function () {
    $scheduled = collect(Schedule::events())
        ->first(fn ($event) => $event->description === 'maintain-departure-horizon');

    expect($scheduled)->not->toBeNull()
        ->and($scheduled->expression)->toBe('30 1 * * *');

    $today = now('UTC')->startOfDay();
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '09:00:00',
        'start_date' => $today->toDateString(),
        'end_date' => $today->copy()->addDays(220)->toDateString(),
        'capacity' => 10,
    ]);

    // Draft tour and suspended partner tour must be skipped by horizon maintenance
    $draftTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->tour->category_id,
        'slug' => 'draft-horizon-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Florence, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'draft',
    ]);
    TourOption::create([
        'tour_id' => $draftTour->id,
        'title' => 'Draft Option',
        'price_amount' => 3000,
        'group_size_max' => 8,
        'status' => 'active',
    ]);
    AvailabilityRule::create([
        'tour_id' => $draftTour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '09:00:00',
        'start_date' => $today->toDateString(),
        'capacity' => 8,
    ]);

    $suspendedPartnerUser = User::factory()->partner()->create();
    $suspendedPartner = Partner::create([
        'user_id' => $suspendedPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => false,
    ]);
    $suspendedTour = Tour::create([
        'partner_id' => $suspendedPartner->id,
        'category_id' => $this->tour->category_id,
        'slug' => 'suspended-horizon-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Naples, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'published',
    ]);
    TourOption::create([
        'tour_id' => $suspendedTour->id,
        'title' => 'Suspended Option',
        'price_amount' => 3000,
        'group_size_max' => 8,
        'status' => 'active',
    ]);
    AvailabilityRule::create([
        'tour_id' => $suspendedTour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '09:00:00',
        'start_date' => $today->toDateString(),
        'capacity' => 8,
    ]);

    (new MaintainDepartureHorizonJob)->handle(app(TourDepartureService::class));

    // Only weekday rules within 180-day horizon exist for published eligible tour
    $publishedCount = TourDeparture::where('tour_id', $this->tour->id)->count();
    expect($publishedCount)->toBeGreaterThan(100)
        ->and($publishedCount)->toBeLessThanOrEqual(140)
        ->and(TourDeparture::where('tour_id', $draftTour->id)->count())->toBe(0)
        ->and(TourDeparture::where('tour_id', $suspendedTour->id)->count())->toBe(0);

    // Rerunning job is idempotent
    (new MaintainDepartureHorizonJob)->handle(app(TourDepartureService::class));
    expect(TourDeparture::where('tour_id', $this->tour->id)->count())->toBe($publishedCount);
});

test('completed departures reject updates and cancellations with 422 and availability endpoints return 404 on non-numeric IDs', function () {
    $date = Carbon::now()->addDays(15)->toDateString();
    $completedDeparture = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => Carbon::parse($date . ' 09:00:00', 'Europe/Rome')->utc(),
        'timezone' => 'Europe/Rome',
        'local_date' => $date,
        'local_time' => '09:00:00',
        'capacity' => 8,
        'booking_cutoff_hours' => 2,
        'status' => TourDeparture::STATUS_COMPLETED,
    ]);

    $this->withHeaders(partnerAuthHeaders($this->token))
        ->putJson("/api/partner/tours/{$this->tour->id}/departures/{$completedDeparture->id}", ['capacity' => 10])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $this->withHeaders(partnerAuthHeaders($this->token))
        ->postJson("/api/partner/tours/{$this->tour->id}/departures/{$completedDeparture->id}/cancel", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $this->withHeaders(partnerAuthHeaders($this->token))
        ->putJson("/api/partner/tours/{$this->tour->id}/availability/rules/not-a-number", ['capacity' => 10])
        ->assertStatus(404);

    $this->withHeaders(partnerAuthHeaders($this->token))
        ->deleteJson("/api/partner/tours/{$this->tour->id}/availability/exceptions/not-a-number")
        ->assertStatus(404);
});
