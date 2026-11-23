<?php

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $this->traveler = User::factory()->traveler()->create();
    $this->otherTraveler = User::factory()->traveler()->create();

    $category = Category::firstOrCreate(['slug' => 'decline-cat'], ['name' => 'Decline Cat']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'decline-test-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 6,
        'price_amount' => 8000,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Price Drift Decline Tour',
        'description' => 'Tour testing pending booking decline flow.',
    ]);
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '10:00:00',
        'start_date' => Carbon::now()->subDay()->toDateString(),
        'end_date' => Carbon::now()->addDays(60)->toDateString(),
        'capacity' => 6,
    ]);
    $this->option = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Standard Slot',
        'price_amount' => 8000,
        'group_size_max' => 6,
        'start_time' => '10:00:00',
        'status' => 'active',
    ]);
    $this->departure = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => Carbon::now()->addDays(5)->setTime(10, 0, 0)->utc(),
        'timezone' => 'Europe/Rome',
        'local_date' => Carbon::now()->addDays(5)->toDateString(),
        'local_time' => '10:00:00',
        'capacity' => 6,
        'booking_cutoff_hours' => 2,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);

    $this->mock(CreatePaymentIntentAction::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturn('pi_secret_test_' . Str::random(8));
    });
});

it('declines a pending_payment booking, immediately releases departure seats, fails pending payments, and writes an audit entry', function () {
    $this->mock(PaymentGateway::class, function ($mock) {
        $mock->shouldReceive('cancelPaymentIntent')->once()->with('pi_pending_decline_1');
    });

    $result = app(CreateBookingAction::class)->execute(new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 4,
        locale: 'en',
        idempotencyKey: Str::uuid()->toString(),
        travelerId: $this->traveler->id,
        pageLoadPrice: 7000, // simulates drift
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    ));

    expect($result['price_changed'])->toBeTrue();
    $reference = $result['data']['reference'];
    $booking = Booking::where('reference', $reference)->firstOrFail();

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_pending_decline_1',
        'amount' => $booking->total_price,
        'currency' => 'EUR',
        'type' => 'charge',
        'status' => 'pending',
    ]);

    // While pending_payment is active, 4 of 6 seats are held
    expect($this->departure->fresh()->remainingCapacity())->toBe(2);

    // Existing /cancel endpoint still rejects pending_payment bookings
    $this->actingAs($this->traveler)
        ->postJson("/api/public/traveler/bookings/{$reference}/cancel")
        ->assertStatus(422);

    // New /decline endpoint releases the pending seat hold cleanly
    $declineRes = $this->actingAs($this->traveler)
        ->postJson("/api/public/traveler/bookings/{$reference}/decline");

    $declineRes->assertStatus(200)
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('data.status', 'expired');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(6)
        ->and(Payment::where('booking_id', $booking->id)->value('status'))->toBe('failed')
        ->and(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'declined_price_change')->exists())->toBeTrue();

    // Another booking can immediately reserve the released 4 seats
    $second = app(CreateBookingAction::class)->execute(new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 4,
        locale: 'en',
        idempotencyKey: Str::uuid()->toString(),
        travelerId: $this->traveler->id,
        pageLoadPrice: 8000,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    ));

    expect($second['data']['reference'])->not->toBe($reference)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(2);
});

it('enforces authentication, ownership, not-found, and pending_payment-only status checks on decline', function () {
    $pending = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $this->departure->id,
        'tour_date' => $this->departure->local_date,
        'participant_count' => 2,
        'price_per_person' => 8000,
        'total_price' => 16000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'pending_expires_at' => Carbon::now()->addMinutes(15),
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    $this->postJson("/api/public/traveler/bookings/{$pending->reference}/decline")
        ->assertStatus(401);

    $this->actingAs($this->otherTraveler)
        ->postJson("/api/public/traveler/bookings/{$pending->reference}/decline")
        ->assertStatus(403);

    $this->actingAs($this->traveler)
        ->postJson('/api/public/traveler/bookings/BKO-ZZ99XX/decline')
        ->assertStatus(404);

    foreach ([Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED] as $nonPendingStatus) {
        $otherBooking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $this->traveler->id,
            'tour_id' => $this->tour->id,
            'tour_option_id' => $this->option->id,
            'tour_departure_id' => $this->departure->id,
            'tour_date' => $this->departure->local_date,
            'participant_count' => 1,
            'price_per_person' => 8000,
            'total_price' => 8000,
            'currency' => 'EUR',
            'status' => $nonPendingStatus,
            'idempotency_key' => Str::uuid()->toString(),
            'cancellation_policy' => 'Standard',
            'cancellation_window_hours' => 24,
            'locale' => 'en',
        ]);

        $this->actingAs($this->traveler)
            ->postJson("/api/public/traveler/bookings/{$otherBooking->reference}/decline")
            ->assertStatus(422);
    }
});
