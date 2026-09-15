<?php

use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\DeterministicPaymentGateway;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['services.payment.gateway' => 'deterministic']);
    app()->forgetInstance(PaymentGateway::class);
    Queue::fake();
});

it('completes the authenticated booking payment path exactly once', function () {
    $traveler = User::factory()->traveler()->create();
    $tour = makeSearchableTour(slug: 'deterministic-checkout');
    addTranslation($tour, 'en', 'Deterministic Checkout');
    addAvailabilityRule($tour);
    $idempotencyKey = Str::uuid()->toString();

    $checkout = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => $tour->price_amount,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', Booking::STATUS_PENDING_PAYMENT)
        ->assertJsonPath('payment.gateway', 'deterministic')
        ->assertJsonPath('payment.stripe_publishable_key', null);

    $reference = $checkout->json('data.reference');
    $clientSecret = $checkout->json('payment.client_secret');

    actingAs($traveler)
        ->postJson("/api/public/bookings/{$reference}/deterministic-payment/confirm", [
            'client_secret' => $clientSecret,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED);

    // A repeated browser submit is a successful no-op, with no duplicate
    // ledger, audit, or notification job.
    actingAs($traveler)
        ->postJson("/api/public/bookings/{$reference}/deterministic-payment/confirm", [
            'client_secret' => $clientSecret,
        ])
        ->assertOk();

    $booking = Booking::where('reference', $reference)->firstOrFail();
    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->payment_confirmed_at)->not->toBeNull()
        ->and($payment->status)->toBe('succeeded')
        ->and($payment->card_brand)->toBe('deterministic-test')
        ->and(FinancialLedgerEntry::where('booking_id', $booking->id)->count())->toBe(1)
        ->and(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'payment_confirmed')->count())->toBe(1);
    Queue::assertPushed(SendBookingConfirmationEmail::class, 1);
});

it('keeps payment confirmation owner scoped and secret bound', function () {
    $owner = User::factory()->traveler()->create();
    $other = User::factory()->traveler()->create();
    $tour = makeSearchableTour(slug: 'owner-scoped-checkout');
    addAvailabilityRule($tour);

    $checkout = actingAs($owner)
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(15)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => $tour->price_amount,
        ])->assertCreated();

    $endpoint = '/api/public/bookings/' . $checkout->json('data.reference') . '/deterministic-payment/confirm';

    actingAs($other)->postJson($endpoint, [
        'client_secret' => $checkout->json('payment.client_secret'),
    ])->assertNotFound();

    actingAs($owner)->postJson($endpoint, [
        'client_secret' => 'wrong-secret',
    ])->assertNotFound();
});

it('derives stable intent references without hardcoded credentials', function () {
    $gateway = app(DeterministicPaymentGateway::class);

    $first = $gateway->createPaymentIntent(5000, 'EUR', 'stable-key');
    $retry = $gateway->createPaymentIntent(5000, 'EUR', 'stable-key');
    $different = $gateway->createPaymentIntent(5000, 'EUR', 'different-key');

    expect($retry)->toBe($first)
        ->and($different)->not->toBe($first)
        ->and($first)->toStartWith('pi_test_');
});

it('does not expose the confirmation adapter unless explicitly configured', function () {
    config(['services.payment.gateway' => 'stripe']);

    actingAs(User::factory()->traveler()->create())
        ->postJson('/api/public/bookings/UNKNOWN/deterministic-payment/confirm', [
            'client_secret' => 'irrelevant',
        ])
        ->assertNotFound();
});
