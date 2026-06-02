<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Domains\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\{actingAs, deleteJson};

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

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'archive-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);
});

it('archives a tour', function () {
    $response = deleteJson('/api/partner/tours/' . $this->tour->id, [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    $this->tour->refresh();
    expect($this->tour->status)->toBe('archived');
});

it('archived tours do not appear in default listing', function () {
    // Archive the tour
    $this->tour->update(['status' => 'archived']);

    // Create an active tour for the same partner
    Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'active-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $response = actingAs($this->partnerUser, 'sanctum')
        ->getJson('/api/partner/tours');

    $response->assertStatus(200);

    // Only the active tour should appear (status filter defaults exclude archived)
    $slugs = collect($response->json('data'))->pluck('slug')->toArray();
    expect($slugs)->not->toContain($this->tour->slug);
});

it('archive tour with active bookings returns appropriate response', function () {
    $traveler = User::factory()->traveler()->create();

    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 7500,
        'total_price' => 15000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = deleteJson('/api/partner/tours/' . $this->tour->id, [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    // The tour is still archived (business rule allows it), but the response confirms it
    $response->assertStatus(200);
    $this->tour->refresh();
    expect($this->tour->status)->toBe('archived');
});

it('partner cannot archive another partner tour returning 404', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $response = deleteJson('/api/partner/tours/' . $this->tour->id, [], [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $response->assertStatus(404);

    // Tour status should remain unchanged
    $this->tour->refresh();
    expect($this->tour->status)->toBe('published');
});