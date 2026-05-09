<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\{getJson, postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->otherTraveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => User::factory()->partner()->create()->id,
        'category_id' => $this->category->id,
        'slug' => 'traveler-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);

    $this->token = $this->traveler->createToken('test')->plainTextToken;
});

it('lists own bookings ordered by tour_date desc', function () {
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => '2026-07-15',
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/public/my-bookings', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['reference', 'tour', 'tour_date', 'status']],
            'meta',
        ])
        ->assertJsonCount(1, 'data');
});

it('returns 403 when accessing another traveler booking', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->otherTraveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => '2026-07-15',
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/public/my-bookings/' . $booking->reference, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(403);
});

it('shows booking detail with can_cancel computed', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(30)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free cancellation up to 24 hours before the tour start time.',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    $response = getJson('/api/public/my-bookings/' . $booking->reference, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.can_cancel', true)
        ->assertJsonPath('data.status', 'confirmed');
});

it('cancels an eligible booking', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(30)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    $response = postJson('/api/public/my-bookings/' . $booking->reference . '/cancel', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');

    expect(Booking::find($booking->id)->status)->toBe('cancelled');
});

it('returns 422 for already cancelled booking', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(30)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => 'cancelled',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = postJson('/api/public/my-bookings/' . $booking->reference . '/cancel', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422);
});
