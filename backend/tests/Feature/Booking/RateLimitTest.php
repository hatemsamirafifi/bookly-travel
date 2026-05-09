<?php

use App\Models\User;
use function Pest\Laravel\postJson;

it('returns 429 after exceeding booking creation rate limit', function () {
    $traveler = User::factory()->traveler()->create();
    $token = $traveler->createToken('test')->plainTextToken;

    $uuid = fn (int $i) => '550e8400-e29b-41d4-a716-4466554400' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

    // Make 10 requests (the limit)
    for ($i = 1; $i <= 10; $i++) {
        $response = postJson('/api/public/bookings', [
            'tour_slug' => 'tuscany-wine-tasting',
            'tour_date' => '2026-06-15',
            'participant_count' => 2,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Idempotency-Key' => $uuid($i),
        ]);

        // May be 201, 200, 404, 409, 422 depending on test data — any non-429 is fine
        expect($response->status())->not->toBe(429);
    }

    // The 11th request should be rate limited
    $limited = postJson('/api/public/bookings', [
        'tour_slug' => 'tuscany-wine-tasting',
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $token,
        'Idempotency-Key' => $uuid(11),
    ]);

    $limited->assertStatus(429);
    expect($limited->headers->get('Retry-After'))->not->toBeNull();
});
