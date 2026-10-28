<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

// The controller verifies the raw request body through Stripe's
// Webhook::constructEvent(), so tests must sign exactly the bytes postJson
// sends (json_encode of the same array) with the configured webhook secret.
function stripeSignatureHeader(array $payload, ?int $timestamp = null): array
{
    $timestamp ??= time();
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test');

    return ['Stripe-Signature' => "t={$timestamp},v1={$signature}"];
}

function createWebhookPayment(string $intentId): Payment
{
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();
    $category = Category::firstOrCreate(['slug' => 'webhook-cat'], ['name' => 'Webhook Category']);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'webhook-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 10000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'stripe_payment_intent_id' => $intentId,
    ]);

    return Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => $intentId,
        'type' => 'charge',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'pending',
    ]);
}

it('rejects webhook without signature', function () {
    $this->postJson('/api/public/webhooks/stripe', [], [])
        ->assertBadRequest();
});

it('rejects webhook with invalid signature', function () {
    $payload = [
        'id' => 'evt_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => []],
    ];

    $this->postJson('/api/public/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'InvalidSignature',
    ])
        ->assertBadRequest();
});

it('processes payment_intent.succeeded webhook', function () {
    createWebhookPayment('pi_test_webhook');

    $payment = Payment::where('stripe_payment_intent_id', 'pi_test_webhook')->firstOrFail();
    $booking = $payment->booking;

    $payload = [
        'id' => 'evt_test_123',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook',
                'charges' => ['data' => []],
            ],
        ],
    ];

    $this->postJson('/api/public/webhooks/stripe', $payload, stripeSignatureHeader($payload))
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('succeeded');
    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_123')->exists())->toBeTrue();
});

it('deduplicates webhook events', function () {
    $payload = [
        'id' => 'evt_duplicate_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test', 'charges' => ['data' => []]]],
    ];

    // First call
    $this->postJson('/api/public/webhooks/stripe', $payload, stripeSignatureHeader($payload))
        ->assertOk();

    // Second call with same event ID should be deduplicated
    $this->postJson('/api/public/webhooks/stripe', $payload, stripeSignatureHeader($payload))
        ->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_duplicate_test')->count())->toBe(1);
});

it('handles unsupported webhook event types gracefully', function () {
    $payload = [
        'id' => 'evt_unsupported',
        'type' => 'customer.created',
        'data' => ['object' => []],
    ];

    $this->postJson('/api/public/webhooks/stripe', $payload, stripeSignatureHeader($payload))
        ->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_unsupported')->exists())->toBeTrue();
});

it('marks webhook event as processed', function () {
    // The event only reaches `processed` when a handler actually consumed it,
    // so seed the matching payment for the intent the payload references.
    createWebhookPayment('pi_test');

    $payload = [
        'id' => 'evt_processed_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test', 'charges' => ['data' => []]]],
    ];

    $this->postJson('/api/public/webhooks/stripe', $payload, stripeSignatureHeader($payload))
        ->assertOk();

    $event = StripeWebhookEvent::where('stripe_event_id', 'evt_processed_test')->first();
    expect($event->processing_status)->toBe('processed');
    expect($event->processed_at)->not->toBeNull();
});
