<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns financial summary for partner tours', function () {
    $category = Category::firstOrCreate(['slug' => 'fin-sum'], ['name' => 'Finance Summary']);
    $partner = User::factory()->partner()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'fin-sum-tour-' . uniqid(),
        'location' => 'Lake Como, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 9000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    // Booking 1: confirmed and paid
    $booking1 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler1->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 9000,
        'total_price' => 18000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    Payment::create([
        'booking_id' => $booking1->id,
        'stripe_payment_intent_id' => 'pi_sum_' . uniqid(),
        'type' => 'charge',
        'amount' => 18000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'card_last_four' => '4242',
        'card_brand' => 'visa',
    ]);

    // Booking 2: confirmed and paid, but refunded
    $booking2 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler2->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(14)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 9000,
        'total_price' => 9000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CANCELLED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    Payment::create([
        'booking_id' => $booking2->id,
        'stripe_payment_intent_id' => 'pi_sum2_' . uniqid(),
        'type' => 'charge',
        'amount' => 9000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    Payment::create([
        'booking_id' => $booking2->id,
        'stripe_payment_intent_id' => 'pi_sum2_refund',
        'stripe_refund_id' => 're_test',
        'type' => 'refund',
        'amount' => 9000,
        'currency' => 'EUR',
        'status' => 'refunded',
    ]);

    $response = actingAs($partner)
        ->getJson('/api/partner/financial-summary?tour_slug=' . $tour->slug);

    $response->assertStatus(200)
        ->assertJsonPath('data.total_revenue.amount', 27000)
        ->assertJsonPath('data.total_refunds.amount', 9000)
        ->assertJsonPath('data.net_revenue.amount', 18000)
        ->assertJsonPath('data.booking_count', 2)
        ->assertJsonPath('data.refund_count', 1);
});

it('does not expose raw stripe identifiers in partner response', function () {
    $category = Category::firstOrCreate(['slug' => 'no-pii'], ['name' => 'No PII']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'no-pii-tour-' . uniqid(),
        'location' => 'Verona, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_secret_do_not_leak',
        'type' => 'charge',
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'card_last_four' => '1111',
        'card_brand' => 'amex',
    ]);

    $response = actingAs($partner)
        ->getJson('/api/partner/financial-summary');

    $response->assertStatus(200);

    $body = $response->getContent();

    // FR-013: no raw stripe IDs exposed
    expect($body)->not->toContain('pi_secret_do_not_leak');
    expect($body)->not->toContain('stripe_payment_intent_id');
    expect($body)->not->toContain('card_last_four');
    expect($body)->not->toContain('full_card');
    expect($body)->not->toContain('customer');
});

it('filters by tour_slug and date range', function () {
    $category = Category::firstOrCreate(['slug' => 'filter'], ['name' => 'Filter Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour1 = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'filter-tour-1-' . uniqid(),
        'location' => 'Bologna, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $tour2 = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'filter-tour-2-' . uniqid(),
        'location' => 'Modena, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour1->id,
        'tour_date' => now()->addDays(5)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_filt_' . uniqid(),
        'type' => 'charge',
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    // Query for tour1 only
    $response = actingAs($partner)
        ->getJson('/api/partner/financial-summary?tour_slug=' . $tour1->slug);

    $response->assertStatus(200);
    expect($response->json('data.total_revenue.amount'))->toBe(5000);

    // Query for tour2 (no bookings)
    $response2 = actingAs($partner)
        ->getJson('/api/partner/financial-summary?tour_slug=' . $tour2->slug);

    $response2->assertStatus(200);
    expect($response2->json('data.total_revenue.amount'))->toBe(0);
});

it('returns 401 for unauthenticated requests', function () {
    getJson('/api/partner/financial-summary')->assertStatus(401);
});
