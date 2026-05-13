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
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->traveler = User::factory()->traveler()->create();
    $this->admin = User::factory()->admin()->create();
    $this->partner = User::factory()->partner()->create();
    $this->category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'admin-review-tour-' . uniqid(),
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
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $this->review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'A review for moderation',
        'status' => 'visible',
        'locale' => 'en',
    ]);
});

it('admin sees all reviews across all tours', function () {
    $response = actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/reviews');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters reviews by status', function () {
    $booking2 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $booking2->id,
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    Review::create([
        'booking_id' => $booking2->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 2,
        'status' => 'flagged',
        'locale' => 'en',
    ]);

    $response = actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/reviews?status=flagged');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'flagged');
});

it('filters reviews by tour', function () {
    $otherTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'other-admin-tour-' . uniqid(),
        'location' => 'Milan, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'published',
    ]);

    $response = actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/reviews?tour_id=' . $otherTour->id);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('hide action sets status to hidden', function () {
    $response = actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/reviews/{$this->review->id}/hide", [
            'reason' => 'Inappropriate content',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'hidden');

    $this->review->refresh();
    expect($this->review->status)->toBe('hidden');
});

it('reinstate action sets status to visible', function () {
    $this->review->update(['status' => 'hidden']);

    $response = actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/reviews/{$this->review->id}/reinstate", [
            'reason' => 'Hidden in error',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'visible');

    $this->review->refresh();
    expect($this->review->status)->toBe('visible');
});

it('audit trail records moderation actions', function () {
    actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/reviews/{$this->review->id}/hide", [
            'reason' => 'Contains spam',
        ]);

    $trail = ReviewAuditTrail::where('review_id', $this->review->id)
        ->where('action', 'hide')
        ->first();

    expect($trail)->not->toBeNull()
        ->and($trail->actor_type)->toBe('admin')
        ->and($trail->reason)->toBe('Contains spam');
});

it('returns 403 for non-admin role', function () {
    actingAs($this->traveler, 'sanctum')
        ->getJson('/api/admin/reviews')
        ->assertStatus(403);
});

it('hide recalculates aggregate rating', function () {
    actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/reviews/{$this->review->id}/hide", [
            'reason' => 'Spam review',
        ]);

    $this->tour->refresh();
    expect($this->tour->review_count)->toBe(0)
        ->and($this->tour->average_rating)->toBeNull();
});
