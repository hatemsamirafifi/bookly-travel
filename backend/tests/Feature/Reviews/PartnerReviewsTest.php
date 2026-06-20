<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->traveler = User::factory()->traveler()->create();
    $this->partner = User::factory()->partner()->create();
    $this->otherPartner = User::factory()->partner()->create();
    $this->category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
});

it('partner sees only their tours reviews', function () {
    $myTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'my-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $otherTour = Tour::create([
        'partner_id' => $this->otherPartner->id,
        'category_id' => $this->category->id,
        'slug' => 'other-tour-' . uniqid(),
        'location' => 'Milan, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $myTour->id,
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
        'tour_id' => $myTour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'My tour review',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $otherBooking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $otherTour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 3000,
        'total_price' => 3000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);

    Payment::create([
        'booking_id' => $otherBooking->id,
        'amount' => 3000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    Review::create([
        'booking_id' => $otherBooking->id,
        'tour_id' => $otherTour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 2,
        'comment' => 'Other tour review',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $response = actingAs($this->partner, 'sanctum')
        ->getJson('/api/partner/reviews');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment', 'My tour review');
});

it('returns aggregate values per tour', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'agg-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 15,
        'price_amount' => 8000,
        'status' => 'published',
    ]);

    $traveler2 = User::factory()->traveler()->create();

    foreach ([[4, 'Good'], [5, 'Great']] as $i => [$rating, $comment]) {
        $travelerId = $i === 0 ? $this->traveler->id : $traveler2->id;
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $travelerId,
            'tour_id' => $tour->id,
            'tour_date' => now()->subDay(),
            'participant_count' => 1,
            'price_per_person' => 8000,
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

        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $tour->id,
            'traveler_id' => $travelerId,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'visible',
            'locale' => 'en',
        ]);
    }

    $tour->update(['average_rating' => 4.5, 'review_count' => 2]);

    $response = actingAs($this->partner, 'sanctum')
        ->getJson('/api/partner/reviews');

    $response->assertStatus(200)
        ->assertJsonPath('meta.tour_summaries.0.review_count', 2);
});

it('filters by tour_id', function () {
    $tourA = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'tour-a-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 6000,
        'status' => 'published',
    ]);

    $tourB = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'tour-b-' . uniqid(),
        'location' => 'Naples, Italy',
        'duration_minutes' => 150,
        'duration_label' => '2.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 12,
        'price_amount' => 7000,
        'status' => 'published',
    ]);

    foreach ([[$tourA, 4], [$tourB, 3]] as [$tour, $rating]) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $this->traveler->id,
            'tour_id' => $tour->id,
            'tour_date' => now()->subDay(),
            'participant_count' => 1,
            'price_per_person' => $tour->price_amount,
            'total_price' => $tour->price_amount,
            'currency' => 'EUR',
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount' => $tour->price_amount,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'type' => 'charge',
        ]);

        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $tour->id,
            'traveler_id' => $this->traveler->id,
            'rating' => $rating,
            'status' => 'visible',
            'locale' => 'en',
        ]);
    }

    $response = actingAs($this->partner, 'sanctum')
        ->getJson('/api/partner/reviews?tour_id=' . $tourA->id);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('returns 401 for unauthenticated', function () {
    getJson('/api/partner/reviews')
        ->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    actingAs($this->traveler, 'sanctum')
        ->getJson('/api/partner/reviews')
        ->assertStatus(403);
});
