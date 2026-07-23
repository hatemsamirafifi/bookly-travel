<?php

use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Listeners\ConfirmBookingOnPayment;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeConfirmTour(): Tour
{
    $category = Category::firstOrCreate(['slug' => 'confirm'], ['name' => 'Confirm']);
    $partner = makePartner();

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'confirm-tour-' . uniqid(),
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

function makeConfirmPendingBooking(Tour $tour): Booking
{
    $traveler = User::factory()->traveler()->create();

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(14)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->addMinutes(15),
    ]);
}

function makeChargePayment(Booking $booking): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_confirm_' . uniqid(),
        'type' => 'charge',
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);
}

it('confirms a pending_payment booking, writes a debit ledger entry + audit, and queues the confirmation email', function () {
    $tour = makeConfirmTour();
    $booking = makeConfirmPendingBooking($tour);
    $payment = makeChargePayment($booking);

    Queue::fake();

    app(ConfirmBookingOnPayment::class)->handle(new PaymentSucceeded($payment, $booking));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
    expect($booking->fresh()->payment_confirmed_at)->not->toBeNull();

    // Idempotent debit ledger entry recorded once.
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'debit')->count())->toBe(1);

    // Audit captures the system-initiated transition.
    $audit = BookingAuditLog::where('booking_id', $booking->id)->where('action', 'payment_confirmed')->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_type)->toBe('system');
    expect($audit->before_state)->toBe(Booking::STATUS_PENDING_PAYMENT);
    expect($audit->after_state)->toBe(Booking::STATUS_CONFIRMED);

    Queue::assertPushed(SendBookingConfirmationEmail::class);
});

it('does not resurrect an expired booking from a late payment_succeeded webhook', function () {
    $tour = makeConfirmTour();
    $booking = makeConfirmPendingBooking($tour);
    $booking->update(['status' => Booking::STATUS_EXPIRED]);
    $payment = makeChargePayment($booking);

    Queue::fake();

    app(ConfirmBookingOnPayment::class)->handle(new PaymentSucceeded($payment, $booking->fresh()));

    // Guard rejects the late webhook — booking stays expired, no overbooking.
    expect($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED);
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->count())->toBe(0);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'payment_confirmed')->count())->toBe(0);
    Queue::assertNotPushed(SendBookingConfirmationEmail::class);
});

it('is idempotent across duplicate payment_succeeded dispatches', function () {
    $tour = makeConfirmTour();
    $booking = makeConfirmPendingBooking($tour);
    $payment = makeChargePayment($booking);

    Queue::fake();

    $listener = app(ConfirmBookingOnPayment::class);
    $listener->handle(new PaymentSucceeded($payment, $booking));
    $listener->handle(new PaymentSucceeded($payment, $booking));

    // One debit, one audit, one email — the second dispatch is a no-op.
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'debit')->count())->toBe(1);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'payment_confirmed')->count())->toBe(1);
    Queue::assertPushed(SendBookingConfirmationEmail::class, 1);
});