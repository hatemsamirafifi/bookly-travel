<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Jobs\ExpirePendingBookingsJob;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function dispatchExpiryJob(): void
{
    // Method-inject the dependencies (matches how Laravel's queue dispatcher
    // would resolve them) so a mock bound via $this->mock(...) is used.
    (new ExpirePendingBookingsJob)->handle(
        app(AuditService::class),
        app(StripeService::class),
    );
}

function makeExpiryTour(string $slugSuffix): Tour
{
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
    $partner = makePartner();

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'expire-' . $slugSuffix . '-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
}

function makePendingBooking(Tour $tour, $expiresAt, ?string $intentId = null): Booking
{
    $traveler = User::factory()->traveler()->create();

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => $expiresAt,
    ]);

    if ($intentId) {
        Payment::create([
            'booking_id' => $booking->id,
            'stripe_payment_intent_id' => $intentId,
            'type' => 'charge',
            'amount' => 10000,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);
    }

    return $booking;
}

it('expires pending payment bookings older than 15 minutes and cancels their PaymentIntents', function () {
    $tour = makeExpiryTour('stale');

    $stale = makePendingBooking($tour, now()->subMinutes(16), 'pi_stale_1');

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('cancelPaymentIntent')->once()->with('pi_stale_1');

    dispatchExpiryJob();

    $stale->refresh();
    expect($stale->status)->toBe(Booking::STATUS_EXPIRED);
});

it('keeps expiry durable when Stripe PaymentIntent cancel fails', function () {
    $tour = makeExpiryTour('cancel-fail');

    $stale = makePendingBooking($tour, now()->subMinutes(16), 'pi_cancel_fail');

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('cancelPaymentIntent')
        ->once()
        ->with('pi_cancel_fail')
        ->andThrow(new Exception('stripe unreachable'));

    // Must not propagate — expiry stays durable even if Stripe is down.
    dispatchExpiryJob();

    $stale->refresh();
    expect($stale->status)->toBe(Booking::STATUS_EXPIRED);
});

it('does not expire bookings that are still within the 15-minute window', function () {
    $tour = makeExpiryTour('fresh');

    // F18: a future expiry keeps the booking pending. (A past value like
    // subMinutes(10) would be <= now() and the job would expire it, contradicting
    // the assertion.)
    $booking = makePendingBooking($tour, now()->addMinutes(5));

    $this->mock(StripeService::class)->shouldNotReceive('cancelPaymentIntent');

    dispatchExpiryJob();

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_PENDING_PAYMENT);
});

it('does not cancel a PaymentIntent for bookings without one', function () {
    $tour = makeExpiryTour('no-intent');

    // Stale booking but with NO charge Payment row — nothing to cancel.
    makePendingBooking($tour, now()->subMinutes(16));

    $this->mock(StripeService::class)->shouldNotReceive('cancelPaymentIntent');

    dispatchExpiryJob();

    expect(Booking::where('tour_id', $tour->id)->value('status'))->toBe(Booking::STATUS_EXPIRED);
});
