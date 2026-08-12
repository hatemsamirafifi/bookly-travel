<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Listeners\ExpireBookingOnPaymentFailure;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeFailTour(): Tour
{
    $category = Category::firstOrCreate(['slug' => 'failpay'], ['name' => 'Fail Pay']);
    $partner = makePartner();

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'failpay-tour-' . uniqid(),
        'location' => 'Naples, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
}

function makePendingForFail(Tour $tour): Booking
{
    $traveler = User::factory()->traveler()->create();

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(10)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 4000,
        'total_price' => 4000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->addMinutes(15),
    ]);
}

function makeFailedPayment(Booking $booking): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_fail_' . uniqid(),
        'type' => 'charge',
        'amount' => 4000,
        'currency' => 'EUR',
        'status' => 'failed',
    ]);
}

it('expires a pending_payment booking on payment failure with a system-initiated audit', function () {
    $tour = makeFailTour();
    $booking = makePendingForFail($tour);
    $payment = makeFailedPayment($booking);

    app(ExpireBookingOnPaymentFailure::class)->handle(new PaymentFailed($payment, $booking));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED);

    $audit = BookingAuditLog::where('booking_id', $booking->id)->where('action', 'booking.status_changed')->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_type)->toBe('system');
    expect($audit->actor_id)->toBeNull();
    expect($audit->before_state)->toBe(Booking::STATUS_PENDING_PAYMENT);
    expect($audit->after_state)->toBe(Booking::STATUS_EXPIRED);
    expect($audit->metadata['reason'])->toBe('payment_failed');
});

it('does not overwrite a confirmed booking from a late payment_failed webhook', function () {
    $tour = makeFailTour();
    $booking = makePendingForFail($tour);
    $booking->update(['status' => Booking::STATUS_CONFIRMED, 'payment_confirmed_at' => now()]);
    $payment = makeFailedPayment($booking);

    app(ExpireBookingOnPaymentFailure::class)->handle(new PaymentFailed($payment, $booking->fresh()));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'booking.status_changed')->count())->toBe(0);
});

it('does not write a second audit when the booking is already expired', function () {
    $tour = makeFailTour();
    $booking = makePendingForFail($tour);
    $booking->update(['status' => Booking::STATUS_EXPIRED]);
    $payment = makeFailedPayment($booking);

    app(ExpireBookingOnPaymentFailure::class)->handle(new PaymentFailed($payment, $booking->fresh()));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'booking.status_changed')->count())->toBe(0);
});
