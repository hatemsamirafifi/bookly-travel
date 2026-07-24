<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->partner = makePartner();
    $this->otherPartner = makePartner();
    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'partner-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);

    $this->token = $this->partner->user->createToken('test')->plainTextToken;

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

it('partner sees only own tour bookings', function () {
    $otherTour = Tour::create([
        'partner_id' => $this->otherPartner->id,
        'category_id' => $this->category->id,
        'slug' => 'partner-other-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $otherTour->id,
        'tour_date' => '2026-08-01',
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/bookings', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['tour']['slug'])->toBe($this->tour->slug);
});

it('includes aggregates in response', function () {
    $response = getJson('/api/partner/bookings', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'aggregates' => ['total_bookings', 'by_status', 'by_tour'],
            'meta',
        ]);
});

it('transitions booking to completed on past date', function () {
    $booking = $this->booking;
    $booking->update(['tour_date' => now()->subDay()->toDateString()]);

    $response = patchJson('/api/partner/bookings/' . $booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.previous_status', 'confirmed');
});

it('returns 409 for future date transition', function () {
    $this->booking->update(['tour_date' => now()->addDays(10)->toDateString()]);

    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(409);
});

it('returns 403 for another partner tour', function () {
    $this->tour->update(['partner_id' => $this->otherPartner->id]);

    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(403);
});

it('creates audit entry on status transition', function () {
    $this->booking->update(['tour_date' => now()->subDay()->toDateString()]);

    patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $this->assertDatabaseHas('booking_audit_logs', [
        'booking_id' => $this->booking->id,
        'actor_type' => 'partner',
        'action' => 'completed',
        'before_state' => 'confirmed',
        'after_state' => 'completed',
    ]);
});
