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
use function Pest\Laravel\{postJson, putJson};

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

    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'response-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

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
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'amount' => 8900,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    $this->review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 4,
        'comment' => 'Great tour experience.',
        'status' => 'visible',
        'locale' => 'en',
    ]);
});

it('creates a response to a review', function () {
    $response = postJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => 'Thank you for your feedback!',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('response_text', 'Thank you for your feedback!');

    $this->assertDatabaseHas('review_responses', [
        'review_id' => $this->review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Thank you for your feedback!',
    ]);
});

it('updates an existing response', function () {
    ReviewResponse::create([
        'review_id' => $this->review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Original response.',
    ]);

    $response = putJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => 'Updated response text.',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('response_text', 'Updated response text.');

    $this->assertDatabaseHas('review_responses', [
        'review_id' => $this->review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Updated response text.',
    ]);
});

it('cannot create duplicate response for same review', function () {
    ReviewResponse::create([
        'review_id' => $this->review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'First response.',
    ]);

    $response = postJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => 'Duplicate response.',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(409);
});

it('returns 404 for review not belonging to partner tours', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $response = postJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => 'This should fail.',
    ], [
        'Authorization' => 'Bearer ' . $otherPartnerUser->createToken('test', ['partner'])->plainTextToken,
    ]);

    $response->assertStatus(404);
});

it('returns 401 for unauthenticated request', function () {
    postJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => 'Unauthenticated attempt.',
    ])->assertStatus(401);
});

it('returns 422 for empty response text', function () {
    $response = postJson('/api/partner/reviews/' . $this->review->id . '/responses', [
        'response_text' => '',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422);
});