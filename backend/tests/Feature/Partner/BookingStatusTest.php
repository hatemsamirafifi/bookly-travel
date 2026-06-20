<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\patchJson;

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

    $this->otherPartnerUser = User::factory()->partner()->create();
    $this->otherPartner = Partner::create([
        'user_id' => $this->otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'status-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay()->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
});

it('marks booking as completed for past date', function () {
    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.previous_status', 'confirmed');
});

it('cannot mark future bookings as completed', function () {
    $this->booking->update(['tour_date' => now()->addDays(10)->toDateString()]);

    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(409);
});

it('requests cancellation for a confirmed booking', function () {
    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'no_show',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'no_show');
});

it('cannot request cancellation for already cancelled booking', function () {
    $this->booking->update(['status' => Booking::STATUS_CANCELLED]);

    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    // Expecting 409 conflict since the booking is not in 'confirmed' state
    $response->assertStatus(409);
});

it('returns 404 for booking belonging to another partner tour', function () {
    $otherTour = Tour::create([
        'partner_id' => $this->otherPartner->id,
        'category_id' => $this->category->id,
        'slug' => 'other-status-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $otherBooking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $otherTour->id,
        'tour_date' => now()->subDay()->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = patchJson('/api/partner/bookings/' . $otherBooking->reference . '/status', [
        'status' => 'completed',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(404);
});

it('returns 401 for unauthenticated request', function () {
    $response = patchJson('/api/partner/bookings/' . $this->booking->reference . '/status', [
        'status' => 'completed',
    ]);

    $response->assertStatus(401);
});
