<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\{assertDatabaseHas, postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => User::factory()->partner()->create()->id,
        'category_id' => $this->category->id,
        'slug' => 'create-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);
});

it('creates a booking successfully', function () {
    $idempotencyKey = Str::uuid()->toString();

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'reference',
                'tour',
                'tour_date',
                'participant_count',
                'total_price',
                'status',
                'cancellation_policy',
                'can_cancel',
                'created_at',
            ],
        ])
        ->assertJsonPath('data.status', 'confirmed');

    expect($response->json('data.reference'))->toStartWith('BKO-');

    assertDatabaseHas('bookings', [
        'traveler_id' => $this->traveler->id,
        'status' => 'confirmed',
        'idempotency_key' => $idempotencyKey,
    ]);
});

it('returns existing booking on idempotent retry', function () {
    $token = $this->traveler->createToken('test')->plainTextToken;
    $idempotencyKey = Str::uuid()->toString();

    $first = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $token,
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $first->assertStatus(201);

    $second = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $token,
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $second->assertStatus(200)
        ->assertJsonPath('data.reference', $first->json('data.reference'));

    expect(Booking::count())->toBe(1);
});

it('returns 404 for nonexistent tour', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => 'nonexistent-tour',
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(404);
});

it('returns 404 for draft tour', function () {
    $this->tour->update(['status' => 'draft']);

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(404);
});

it('returns 422 for past date', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2020-01-01',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422);
});

it('returns 422 for participant count below minimum', function () {
    $this->tour->update(['group_size_min' => 3]);

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 1,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422);
});

it('returns 401 for unauthenticated request', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ]);

    $response->assertStatus(401);
});

it('returns 422 when idempotency key header is missing', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
    ]);

    $response->assertStatus(422);
});
