<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
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
        'slug' => 'review-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;
});

it('returns reviews for partner tours', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 8900,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 5,
        'comment' => 'Amazing experience!',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $response = getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('can filter by has_response', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 8900,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'Great tour with a response.',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    // Create a response for this review
    ReviewResponse::create([
        'review_id' => $review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Thank you for your review!',
    ]);

    // Create a review without a response
    $booking2 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDays(2),
        'participant_count' => 1,
        'price_per_person' => 8900,
        'total_price' => 8900,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    Payment::create([
        'booking_id' => $booking2->id,
        'amount' => 8900,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    Review::create([
        'booking_id' => $booking2->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 3,
        'comment' => 'Review without response.',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    // Filter for reviews with responses
    $response = getJson('/api/partner/reviews?has_response=true', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Filter for reviews without responses
    $noResponse = getJson('/api/partner/reviews?has_response=false', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $noResponse->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('returns 401 for unauthenticated request', function () {
    getJson('/api/partner/reviews')
        ->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    getJson('/api/partner/reviews', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(403);
});
