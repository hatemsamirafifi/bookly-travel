<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows edit within 48 hours of creation', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'edit-test-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 3,
        'comment' => 'Original review',
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(1),
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Updated review',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.edited', true);
});

it('shows edited flag after edit', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'edited-flag-' . uniqid(),
        'location' => 'Milan, Italy',
        'duration_minutes' => 60,
        'duration_label' => '1 hour',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 3000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 3000,
        'total_price' => 3000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 3000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(1),
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Edited!',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.edited', true);

    $this->assertNotNull($review->fresh()->edited_at);
});

it('creates audit trail entry on edit', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'audit-edit-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 2,
        'price_per_person' => 4000,
        'total_price' => 8000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 8000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 3,
        'comment' => 'Original',
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(1),
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 4,
            'comment' => 'Better now',
        ])
        ->assertStatus(200);

    $auditEntry = ReviewAuditTrail::where('review_id', $review->id)
        ->where('action', 'edit')
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->old_rating)->toBe(3);
    expect($auditEntry->new_rating)->toBe(4);
});

it('returns 403 for edit after 48 hours', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'expired-edit-' . uniqid(),
        'location' => 'Naples, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 6,
        'price_amount' => 6000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 6000,
        'total_price' => 6000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 6000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(49),
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
        ])
        ->assertStatus(403);
});

it('returns 403 when non-owner tries to edit', function () {
    $traveler = User::factory()->traveler()->create();
    $otherTraveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'nonowner-edit-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 3,
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(1),
    ]);

    actingAs($otherTraveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
        ])
        ->assertStatus(403);
});

it('recalculates aggregate rating on edit', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = User::factory()->partner()->create();
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'agg-edit-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 60,
        'duration_label' => '1 hour',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'price_amount' => 2000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 2000,
        'total_price' => 2000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 2000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 2,
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHours(1),
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 4,
        ])
        ->assertStatus(200);

    $tour->refresh();
    expect((float) $tour->average_rating)->toEqualWithDelta(4.0, 0.01);
});
