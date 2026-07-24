<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Events\ReviewFlagged;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows edit within 48 hours of creation', function () {
    $traveler = User::factory()->traveler()->create();
    $partner = makePartner();
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
    $partner = makePartner();
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
    $partner = makePartner();
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
    $partner = makePartner();
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
    $partner = makePartner();
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
    $partner = makePartner();
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

it('does not reset the 48h edit window on edit (FR-011 regression)', function () {
    // Anchor the clock so the "now()-based" window is deterministic.
    Carbon::setTestNow(now());
    [$traveler, $review] = createReviewForEditTest(['created_at' => now()->subHours(47)]);

    // First edit 1h before the window closes — succeeds and stamps edited_at = now.
    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", ['rating' => 4, 'comment' => 'First edit'])
        ->assertStatus(200);
    expect($review->fresh()->edited_at)->not->toBeNull();

    // Advance the clock past created_at + 48h. edited_at is only ~0h old, so the
    // OLD (buggy) logic anchored to edited_at would still allow the edit. The
    // fixed logic anchors to created_at and must reject it.
    Carbon::setTestNow(now()->addHours(3));
    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", ['rating' => 5, 'comment' => 'Second edit'])
        ->assertStatus(403);

    Carbon::setTestNow();
});

it('flags an edited review whose new comment contains profanity', function () {
    Event::fake([ReviewFlagged::class]);
    [$traveler, $review] = createReviewForEditTest();

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'This tour was shit',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'flagged');

    expect($review->fresh()->status)->toBe('flagged');
    Event::assertDispatched(ReviewFlagged::class);

    $audit = ReviewAuditTrail::where('review_id', $review->id)->where('action', 'edit')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_comment)->toBe('This tour was shit');
});

it('clears the flag when an edit removes the profanity', function () {
    [$traveler, $review] = createReviewForEditTest([
        'status' => 'flagged',
        'comment' => 'shit tour',
    ]);

    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Actually a lovely tour',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'visible');

    expect($review->fresh()->status)->toBe('visible');
});

it('does not un-hide an admin-suppressed review via edit', function () {
    [$traveler, $review] = createReviewForEditTest(['status' => 'hidden', 'comment' => 'Bad']);

    // A profane edit on a hidden review must NOT flip it to flagged (which would
    // re-publish it in the public list); the hidden status is preserved.
    actingAs($traveler)
        ->putJson("/api/public/reviews/{$review->id}", [
            'rating' => 4,
            'comment' => 'This is shit',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'hidden');

    expect($review->fresh()->status)->toBe('hidden');
});

it('rolls back the review update if the audit-trail write fails', function () {
    [$traveler, $review] = createReviewForEditTest();
    $originalRating = $review->rating;
    $originalComment = $review->comment;

    $events = app('events');
    $events->listen('eloquent.creating: ' . ReviewAuditTrail::class, function () {
        throw new RuntimeException('audit insert failed');
    });

    try {
        actingAs($traveler)
            ->putJson("/api/public/reviews/{$review->id}", [
                'rating' => 5,
                'comment' => 'Should not persist',
            ])
            ->assertStatus(500);
    } finally {
        $events->forget('eloquent.creating: ' . ReviewAuditTrail::class);
    }

    // The update must have been rolled back with the failed audit insert.
    expect($review->fresh()->rating)->toBe($originalRating)
        ->and($review->fresh()->comment)->toBe($originalComment);
});
