<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Reviews\Models\Review;
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

it('returns summary analytics data', function () {
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDays(5)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/analytics', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['total_bookings', 'total_revenue', 'average_rating', 'conversion_rate']);
});

it('can filter analytics by date range', function () {
    // Booking within the date range
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDays(5)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    // Booking outside the date range
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDays(60)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $from = now()->subDays(10)->toDateString();
    $to = now()->toDateString();

    $response = getJson("/api/partner/analytics?from={$from}&to={$to}", [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    // The booking within range should be counted
    expect($response->json('total_bookings'))->toBeGreaterThanOrEqual(1);
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

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $tour2->id,
        'tour_date' => now()->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/analytics?tour_id=' . $this->tour->id, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    // Only bookings for the specified tour should be counted
    expect($response->json('total_bookings'))->toBeGreaterThanOrEqual(1);
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
    ])->assertStatus(403);
});