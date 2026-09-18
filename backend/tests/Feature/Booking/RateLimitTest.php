<?php

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('returns 429 after exceeding booking creation rate limit', function () {
    Carbon::setTestNow('2026-06-01 00:00:00');
    $traveler = User::factory()->traveler()->create();
    $token = $traveler->createToken('test')->plainTextToken;

    // The limiter must reject request 11 before the controller invokes the
    // booking action. This verifies the fast-path boundary deterministically;
    // wall-clock assertions are unreliable in a shared Docker test runner.
    $this->mock(CreateBookingAction::class)
        ->shouldReceive('execute')
        ->times(10)
        ->andReturn([
            'is_retry' => true,
            'data' => [],
            'payment' => null,
            'price_changed' => false,
        ]);

    $uuid = fn (int $i) => '550e8400-e29b-41d4-a716-4466554400' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

    // Make 10 requests (the limit) — these should all be non-429
    for ($i = 1; $i <= 10; $i++) {
        $response = postJson('/api/public/bookings', [
            'tour_slug' => 'tuscany-wine-tasting',
            'tour_date' => '2026-06-15',
            'participant_count' => 2,
            'locale' => 'en',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Idempotency-Key' => $uuid($i),
        ]);

        // May be 201, 200, 404, 409, 422 depending on test data — any non-429 is fine
        expect($response->status())->not->toBe(429);
    }

    // The 11th request must be rejected by the limiter.
    $limited = postJson('/api/public/bookings', [
        'tour_slug' => 'tuscany-wine-tasting',
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $token,
        'Idempotency-Key' => $uuid(11),
    ]);

    // (1) 429 status
    $limited->assertStatus(429);

    // (2) Retry-After header must be present and numeric (SC-010)
    $retryAfter = $limited->headers->get('Retry-After');
    expect($retryAfter)->not->toBeNull('Retry-After header must be present on 429 responses.');
    expect(is_numeric($retryAfter))->toBeTrue('Retry-After header must be a numeric value (seconds).');

    // (3) JSON body must match contract
    $limited->assertJsonStructure(['message', 'retry_after']);
    expect($limited->json('message'))->toContain('Too many booking attempts');

    Carbon::setTestNow();
});

it('rate limit window resets after the expiry period', function () {
    Carbon::setTestNow('2026-06-01 00:00:00');
    $traveler = User::factory()->traveler()->create();
    $token = $traveler->createToken('test')->plainTextToken;

    $uuid = fn (int $i) => '550e8400-e29b-41d4-a716-4466554400' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

    $payload = [
        'tour_slug' => 'tuscany-wine-tasting',
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ];
    $headers = fn (int $i) => [
        'Authorization' => 'Bearer ' . $token,
        'Idempotency-Key' => $uuid($i),
    ];

    // Exhaust the 10/min bucket.
    for ($i = 1; $i <= 10; $i++) {
        postJson('/api/public/bookings', $payload, $headers($i));
    }

    // 11th request is limited while inside the 60s window.
    $limited = postJson('/api/public/bookings', $payload, $headers(11));
    $limited->assertStatus(429);

    // Advance past the per-minute decay window. The array cache store checks
    // expiry against Carbon::now(), so mocked time expires the bucket and the
    // limiter admits the next request again.
    Carbon::setTestNow('2026-06-01 00:01:05');

    $afterReset = postJson('/api/public/bookings', $payload, $headers(12));
    expect($afterReset->status())->not->toBe(429, 'Rate limit window should reset after the decay period elapses.');

    Carbon::setTestNow();
});
