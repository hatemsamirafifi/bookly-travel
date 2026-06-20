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
        'slug' => 'booking-list-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
});

it('returns paginated booking list', function () {
    $response = getJson('/api/partner/bookings', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'aggregates', 'meta']);

    expect($response->json('data'))->toHaveCount(1);
});

it('can filter by status', function () {
    // Create a completed booking
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay()->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/bookings?status=confirmed', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['status'])->toBe('confirmed');
});

it('partner cannot see another partner bookings', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $response = getJson('/api/partner/bookings', [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns 401 for unauthenticated request', function () {
    $response = getJson('/api/partner/bookings');

    $response->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    $response = getJson('/api/partner/bookings', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ]);

    $response->assertStatus(403);
});
