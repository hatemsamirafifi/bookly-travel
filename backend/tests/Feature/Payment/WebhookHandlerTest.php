<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

it('rejects webhook without signature', function () {
    Http::fake();

    $payload = json_encode([
        'id' => 'evt_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => []],
    ]);

    postJson('/api/public/webhooks/stripe', [], [])
        ->assertBadRequest();
});

it('rejects webhook with invalid signature', function () {
    Http::fake();

    $payload = json_encode([
        'id' => 'evt_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => []],
    ]);

    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'InvalidSignature',
    ])
        ->assertBadRequest();
});

it('processes payment_intent.succeeded webhook', function () {
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();
    $tour = Tour::factory()->create(['partner_id' => $partner->id, 'status' => 'published']);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'total_price' => 10000,
        'currency' => 'EUR',
        'stripe_payment_intent_id' => 'pi_test_webhook',
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_webhook',
        'type' => 'charge',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'pending',
    ]);

    $payload = json_encode([
        'id' => 'evt_test_123',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook',
                'charges' => ['data' => []],
            ],
        ],
    ]);

    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ])
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('succeeded');
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_123')->exists())->toBeTrue();
});

it('deduplicates webhook events', function () {
    $payload = json_encode([
        'id' => 'evt_duplicate_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test', 'charges' => ['data' => []]]],
    ]);

    // First call
    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ])->assertOk();

    // Second call with same event ID should be deduplicated
    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ])->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_duplicate_test')->count())->toBe(1);
});

it('handles unsupported webhook event types gracefully', function () {
    $payload = json_encode([
        'id' => 'evt_unsupported',
        'type' => 'customer.created',
        'data' => ['object' => []],
    ]);

    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ])
        ->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_unsupported')->exists())->toBeTrue();
});

it('marks webhook event as processed', function () {
    $payload = json_encode([
        'id' => 'evt_processed_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test', 'charges' => ['data' => []]]],
    ]);

    postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ])->assertOk();

    $event = StripeWebhookEvent::where('stripe_event_id', 'evt_processed_test')->first();
    expect($event->processing_status)->toBe('processed');
    expect($event->processed_at)->not->toBeNull();
});
