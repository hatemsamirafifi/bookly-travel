<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->traveler = User::factory()->traveler()->create();
    $this->partner = User::factory()->partner()->create();
    $this->category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'view-reviews-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
});

it('returns paginated visible reviews for a tour', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
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
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'Great tour!',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    event(new ReviewSubmitted($review));

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.review_count', 1);
});

it('does not include hidden reviews in listing', function () {
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

    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 3,
        'status' => 'hidden',
        'locale' => 'en',
    ]);

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('includes flagged reviews in public listing', function () {
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

    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 2,
        'comment' => 'flagged content',
        'status' => 'flagged',
        'locale' => 'en',
    ]);

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('returns empty list for tour with no reviews', function () {
    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.review_count', 0);
});

it('shows aggregate rating correctly', function () {
    $traveler2 = User::factory()->traveler()->create();

    foreach ([[4, null], [5, null]] as $i => [$rating, $comment]) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $i === 0 ? $this->traveler->id : $traveler2->id,
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

        $r = Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $this->tour->id,
            'traveler_id' => $booking->traveler_id,
            'rating' => $rating,
            'status' => 'visible',
            'locale' => 'en',
        ]);

        event(new ReviewSubmitted($r));
    }

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonPath('meta.average_rating', 4.5)
        ->assertJsonPath('meta.review_count', 2);
});

it('shows first name only as reviewer name', function () {
    $traveler = User::factory()->traveler()->create(['name' => 'Marco Rossi']);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
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

    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 5,
        'status' => 'visible',
        'locale' => 'en',
    ]);

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonPath('data.0.reviewer_name', 'Marco');
});

it('shows "Anonymous Traveler" when traveler deleted', function () {
    $traveler = User::factory()->traveler()->create(['name' => 'Delete Me']);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
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

    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $traveler->delete();

    getJson("/api/public/tours/{$this->tour->slug}/reviews")
        ->assertStatus(200)
        ->assertJsonPath('data.0.reviewer_name', 'Anonymous Traveler');
});

it('respects per_page parameter for pagination', function () {
    $traveler2 = User::factory()->traveler()->create();

    foreach ([[4, null], [5, null], [3, null]] as $i => [$rating, $comment]) {
        $travelerId = $i === 0 ? $this->traveler->id : ($i === 1 ? $traveler2->id : $this->traveler->id);
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $travelerId,
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

        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $this->tour->id,
            'traveler_id' => $travelerId,
            'rating' => $rating,
            'status' => 'visible',
            'locale' => 'en',
        ]);
    }

    getJson("/api/public/tours/{$this->tour->slug}/reviews?per_page=2")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});
