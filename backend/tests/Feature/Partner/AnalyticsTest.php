<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);

    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'analytics-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;
});

function analyticsBooking(string $travelerId, int|string $tourId, string $tourDate, int $totalPrice = 8900): Booking
{
    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $travelerId,
        'tour_id' => $tourId,
        'tour_date' => $tourDate,
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => $totalPrice,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
}

it('returns the nested analytics payload without date filters', function () {
    analyticsBooking($this->traveler->id, $this->tour->id, now()->subDays(5)->toDateString(), 17800);

    $response = getJson('/api/partner/analytics', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'summary' => ['total_bookings', 'total_revenue', 'average_rating', 'conversion_rate'],
            'bookings_over_time',
            'period' => ['from', 'to'],
        ]);

    // The booking falls inside the default 30-day window, so it is counted.
    expect($response->json('summary.total_bookings'))->toBeGreaterThanOrEqual(1);
});

it('filters from the `from` date onward when only `from` is provided', function () {
    // Before the `from` date — must be excluded.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-01-10');
    // After the `from` date — must be included.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-06-10');

    $response = getJson('/api/partner/analytics?from=2026-06-01', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    expect($response->json('summary.total_bookings'))->toBe(1);
});

it('filters up to the `to` date when only `to` is provided', function () {
    // Old booking (well outside the 30-day default) — must still be included
    // because `to`-only applies no lower bound.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-01-10');
    // After the `to` date — must be excluded.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-12-10');

    $response = getJson('/api/partner/analytics?to=2026-06-10', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    expect($response->json('summary.total_bookings'))->toBe(1);
});

it('returns only records inside the range when both `from` and `to` are provided', function () {
    // Before the range — excluded.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-01-10');
    // Inside the range — included.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-06-10', 17800);
    // After the range — excluded.
    analyticsBooking($this->traveler->id, $this->tour->id, '2026-12-10');

    $response = getJson('/api/partner/analytics?from=2026-06-01&to=2026-06-30', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    expect($response->json('summary.total_bookings'))->toBe(1);
    // Revenue reflects only the in-range booking.
    expect($response->json('summary.total_revenue'))->toBe(17800);
});

it('rejects an invalid date filter with a validation error', function () {
    $response = getJson('/api/partner/analytics?from=not-a-date', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422);
});

it('can filter analytics by tour_id', function () {
    $tour2 = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'analytics-tour-2-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    analyticsBooking($this->traveler->id, $this->tour->id, now()->toDateString(), 17800);
    analyticsBooking($this->traveler->id, $tour2->id, now()->toDateString(), 5000);

    $response = getJson('/api/partner/analytics?tour_id=' . $this->tour->id, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    // Only bookings for the specified tour should be counted.
    expect($response->json('summary.total_bookings'))->toBeGreaterThanOrEqual(1);
});

it('returns 401 for unauthenticated request', function () {
    getJson('/api/partner/analytics')
        ->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    getJson('/api/partner/analytics', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(404);
});
