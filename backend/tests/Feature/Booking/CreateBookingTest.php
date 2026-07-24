<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-06-01 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
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
    addAvailabilityRule($this->tour); // F9: tour must operate on the booked date
    addTranslation($this->tour, 'en', 'Tuscany Wine Tasting');

    // Booking creation calls Stripe; mock it so the suite never hits the network.
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->andReturn('pi_test_123_secret_abc');
});

afterEach(function () {
    Carbon::setTestNow();
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
                'pricing',
                'total_price',
                'status',
                'cancellation_policy',
                'can_cancel',
                'cancelled_at',
                'created_at',
            ],
        ])
        ->assertJsonPath('data.status', 'pending_payment')
        // F12 regression: tour title resolves from translations
        ->assertJsonPath('data.tour.title', 'Tuscany Wine Tasting');

    expect($response->json('data.reference'))->toStartWith('BKO-');

    assertDatabaseHas('bookings', [
        'traveler_id' => $this->traveler->id,
        'status' => 'pending_payment',
        'idempotency_key' => $idempotencyKey,
        'tour_date' => '2026-06-15',
        'start_time' => '09:00:00',
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

    // F3: idempotent retry returns the same booking (200), with a non-empty
    // tour title and no duplicate PaymentIntent created.
    $second->assertStatus(200)
        ->assertJsonPath('data.reference', $first->json('data.reference'))
        ->assertJsonPath('data.tour.title', 'Tuscany Wine Tasting');

    expect(Booking::count())->toBe(1);
    // The retry must not have created a second Payment row.
    expect(Payment::where('booking_id', Booking::first()->id)->count())->toBe(1);
});

it('returns 404 for nonexistent tour', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => 'nonexistent-tour',
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
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
        'locale' => 'en',
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
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    // L2: 422 carries the errors structure.
    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['tour_date']]);
});

it('returns 422 for participant count below minimum', function () {
    $this->tour->update(['group_size_min' => 3]);

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 1,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['participant_count']]);
});

it('returns 401 for unauthenticated request', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ]);

    $response->assertStatus(401);
});

it('returns 422 when idempotency key header is missing', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
    ]);

    $response->assertStatus(422);
});

// L1: locale is required (booking-api.md).
it('returns 422 when locale is missing', function () {
    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 2,
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

// F9: a date the tour does not operate on is rejected.
it('returns 422 for a non-operating date', function () {
    // Tour operates Mondays only (2026-06-15 is a Monday). Book a Tuesday.
    $this->tour->availabilityRules()->delete();
    addAvailabilityRule($this->tour, ['days_of_week' => [1], 'start_time' => '14:00:00']);

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-16', // Tuesday
        'participant_count' => 2,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tour_date']);
});

// F5: the start time is snapshotted from the operating rule.
it('snapshots the rule start time onto the booking', function () {
    $this->tour->availabilityRules()->delete();
    addAvailabilityRule($this->tour, ['start_time' => '14:30:00']);

    $response = postJson('/api/public/bookings', [
        'tour_slug' => $this->tour->slug,
        'tour_date' => '2026-06-15',
        'participant_count' => 1,
        'locale' => 'en',
    ], [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    $response->assertStatus(201);

    assertDatabaseHas('bookings', [
        'tour_id' => $this->tour->id,
        'tour_date' => '2026-06-15',
        'start_time' => '14:30:00',
    ]);
});
