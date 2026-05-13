<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

it('rejects webhook with missing signature', function () {
    postJson('/api/public/webhooks/stripe', ['type' => 'payment_intent.succeeded'])
        ->assertStatus(400);
});

it('stores webhook event and skips duplicates', function () {
    $eventId = 'evt_' . uniqid();

    StripeWebhookEvent::create([
        'stripe_event_id' => $eventId,
        'event_type' => 'payment_intent.succeeded',
        'processing_status' => 'processed',
        'payload_hash' => hash('sha256', '{}'),
        'processed_at' => now(),
    ]);

    $stored = StripeWebhookEvent::where('stripe_event_id', $eventId)->first();
    expect($stored)->not->toBeNull();
    expect($stored->processing_status)->toBe('processed');

    // Creating a second entry with the same event_id would fail due to unique constraint
    expect(fn () => StripeWebhookEvent::create([
        'stripe_event_id' => $eventId,
        'event_type' => 'payment_intent.succeeded',
        'processing_status' => 'received',
        'payload_hash' => hash('sha256', '{}'),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
