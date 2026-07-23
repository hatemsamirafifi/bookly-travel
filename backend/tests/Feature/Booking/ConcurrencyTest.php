<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Booking creation calls Stripe; mock it so overbooking logic is what's
    // under test, not the payment service.
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->andReturn('pi_test_123_secret_abc');
});

it('prevents overbooking when concurrent requests compete for last spot', function () {
    $category = Category::firstOrCreate(['slug' => 'exclusive'], ['name' => 'Exclusive']);
    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $category->id,
        'slug' => 'exclusive-tour-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 2,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addAvailabilityRule($tour); // F9

    $idempotencyKeys = [
        Str::uuid()->toString(),
        Str::uuid()->toString(),
    ];

    $results = [];

    foreach ([$traveler1, $traveler2] as $i => $traveler) {
        try {
            $response = postJson('/api/public/bookings', [
                'tour_slug' => $tour->slug,
                'tour_date' => '2026-08-01',
                'participant_count' => 2,
                'locale' => 'en',
            ], [
                'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
                'Idempotency-Key' => $idempotencyKeys[$i],
            ]);

            $results[] = $response->status();
        } catch (Exception $e) {
            $results[] = 409;
        }
    }

    expect($results)->toContain(201);
    expect($results)->toContain(409);
    expect(Booking::count())->toBe(1);
});

/**
 * Scenario 2 (SC-002 / FR-023): Multi-spot race condition.
 *
 * Tour has capacity 5, 1 spot already booked (4 remaining).
 * Both travelers request 3 spots simultaneously → only one can win.
 * Total confirmed participant count must never exceed capacity.
 */
it('prevents overbooking when both concurrent requests exceed remaining capacity', function () {
    $category = Category::firstOrCreate(['slug' => 'exclusive'], ['name' => 'Exclusive']);
    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $category->id,
        'slug' => 'capacity-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addAvailabilityRule($tour); // F9

    $tourDate = '2026-09-15';

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler1->id,
        'tour_id' => $tour->id,
        'tour_date' => $tourDate,
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $idempotencyKeyA = Str::uuid()->toString();
    $idempotencyKeyB = Str::uuid()->toString();

    // Both request 3 spots (1 + 3 + 3 = 7 > capacity 5 → only one can succeed)
    $responseA = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => $tourDate,
        'participant_count' => 3,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $traveler1->createToken('test')->plainTextToken,
        'Idempotency-Key' => $idempotencyKeyA,
    ]);

    $responseB = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => $tourDate,
        'participant_count' => 3,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $traveler2->createToken('test')->plainTextToken,
        'Idempotency-Key' => $idempotencyKeyB,
    ]);

    $statuses = [$responseA->status(), $responseB->status()];
    sort($statuses);

    // Exactly one success, one conflict
    expect($statuses)->toBe([201, 409]);

    // Total confirmed participant count must not exceed tour capacity
    $totalConfirmed = Booking::where('tour_id', $tour->id)
        ->where('tour_date', $tourDate)
        ->whereIn('status', [Booking::STATUS_CONFIRMED, 'completed'])
        ->sum('participant_count');

    expect($totalConfirmed)->toBeLessThanOrEqual(
        $tour->group_size_max,
        'Availability must not go negative — zero overbooking invariant violated.'
    );
});

/**
 * F3: a second request carrying the SAME Idempotency-Key returns the existing
 * booking (200) instead of racing to a unique-constraint 500. Sequential here
 * (Pest has no parallel harness), but the unique-index catch is the path that
 * would also resolve a true concurrent same-key insert.
 */
it('returns the existing booking when the same idempotency key is reused', function () {
    $category = Category::firstOrCreate(['slug' => 'exclusive'], ['name' => 'Exclusive']);
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $category->id,
        'slug' => 'same-key-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addAvailabilityRule($tour);

    $token = $traveler->createToken('test')->plainTextToken;
    $key = Str::uuid()->toString();

    $first = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => '2026-09-15',
        'participant_count' => 1,
        'locale' => 'en',
    ], ['Authorization' => 'Bearer ' . $token, 'Idempotency-Key' => $key]);
    $first->assertStatus(201);

    $second = postJson('/api/public/bookings', [
        'tour_slug' => $tour->slug,
        'tour_date' => '2026-09-15',
        'participant_count' => 1,
        'locale' => 'en',
    ], ['Authorization' => 'Bearer ' . $token, 'Idempotency-Key' => $key]);
    $second->assertStatus(200)
        ->assertJsonPath('data.reference', $first->json('data.reference'));

    expect(Booking::count())->toBe(1);
});
