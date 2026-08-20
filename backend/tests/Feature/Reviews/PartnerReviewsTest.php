<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\ReviewResponse;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->traveler = User::factory()->traveler()->create();
    $this->partner = makePartner();
    $this->otherPartner = makePartner();
    $this->category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    // Sanctum token for the partner user (used by the contract-shape tests
    // below; mirrors ReviewListTest's auth approach).
    $this->token = $this->partner->user->createToken('test', ['partner'])->plainTextToken;
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

    $response = getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

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

    $response = getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

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

    $response = getJson('/api/partner/reviews?tour_id=' . $tourA->id, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('returns 401 for unauthenticated', function () {
    getJson('/api/partner/reviews')
        ->assertStatus(401);
});

it('returns 404 for non-partner role', function () {
    $travelerToken = $this->traveler->createToken('test')->plainTextToken;

    getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(404);
});

it('exposes the contract shape and omits internal ids (no PII leak)', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'contract-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    addTranslation($tour, 'en', 'Amalfi Coast Boat Tour');

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $tour->id,
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
        'tour_id' => $tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'Contract check',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['id', 'tour_slug', 'tour_title', 'reviewer_name', 'rating', 'comment', 'status', 'created_at']],
            'meta' => ['tour_summaries', 'current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('data.0.tour_slug', $tour->slug)
        ->assertJsonPath('data.0.tour_title', 'Amalfi Coast Boat Tour')
        ->assertJsonPath('data.0.reviewer_name', Str::before($this->traveler->name, ' '))
        // No internal IDs / PII columns must leak through the resource.
        ->assertJsonMissingPath('data.0.traveler_id')
        ->assertJsonMissingPath('data.0.booking_id')
        ->assertJsonMissingPath('data.0.tour_id')
        ->assertJsonMissingPath('data.0.locale')
        ->assertJsonMissingPath('data.0.edited_at');
});

it('includes tour_summaries with slug, title and aggregates', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'summaries-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4000,
        'status' => 'published',
    ]);
    addTranslation($tour, 'en', 'Florence Walking Tour');

    $traveler2 = User::factory()->traveler()->create();
    foreach ([[5, $this->traveler->id], [4, $traveler2->id]] as [$rating, $travelerId]) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $travelerId,
            'tour_id' => $tour->id,
            'tour_date' => now()->subDay(),
            'participant_count' => 1,
            'price_per_person' => 4000,
            'total_price' => 4000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_COMPLETED,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 4000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'type' => 'charge',
        ]);
        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $tour->id,
            'traveler_id' => $travelerId,
            'rating' => $rating,
            'status' => 'visible',
            'locale' => 'en',
        ]);
    }

    $response = getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('meta.tour_summaries.0.tour_slug', $tour->slug)
        ->assertJsonPath('meta.tour_summaries.0.tour_title', 'Florence Walking Tour')
        ->assertJsonPath('meta.tour_summaries.0.review_count', 2)
        ->assertJsonPath('meta.tour_summaries.0.average_rating', 4.5);
});

it('includes the response when present and omits it when absent', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'response-' . uniqid(),
        'location' => 'Naples, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    addTranslation($tour, 'en', 'Naples Pizza Tour');

    // Reviewed + responded.
    $bookingA = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);
    Payment::create([
        'booking_id' => $bookingA->id,
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);
    $reviewA = Review::create([
        'booking_id' => $bookingA->id,
        'tour_id' => $tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 5,
        'comment' => 'With response',
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subHour(),
    ]);
    ReviewResponse::create([
        'review_id' => $reviewA->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Thanks!',
    ]);

    // Reviewed, no response.
    $bookingB = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDays(2),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
    ]);
    Payment::create([
        'booking_id' => $bookingB->id,
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);
    Review::create([
        'booking_id' => $bookingB->id,
        'tour_id' => $tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'No response',
        'status' => 'visible',
        'locale' => 'en',
        'created_at' => now()->subDays(2),
    ]);

    $response = getJson('/api/partner/reviews?sort=newest', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)->assertJsonCount(2, 'data');

    // Newest first → reviewA (responded) is data.0.
    $response->assertJsonPath('data.0.response.response_text', 'Thanks!');
    expect(array_key_exists('response', $response->json('data.1')))->toBeTrue()
        ->and($response->json('data.1.response'))->toBeNull();
});
