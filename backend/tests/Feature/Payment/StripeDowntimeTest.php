<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('returns 503 when Stripe API is unreachable during booking', function () {
    $category = Category::firstOrCreate(['slug' => 'downtime'], ['name' => 'Downtime']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'downtime-tour-' . uniqid(),
        'location' => 'Turin, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 6000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    // Set an invalid Stripe key to simulate connection failure
    config(['services.stripe.secret' => 'sk_test_invalid_unreachable_key']);
    config(['services.stripe.key' => 'pk_test_invalid']);

    $response = actingAs($traveler)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => 6000,
        ]);

    $response->assertStatus(503);

    // Verify no orphan booking was created
    $bookingCount = Booking::where('tour_id', $tour->id)->count();
    expect($bookingCount)->toBe(0);
});

it('idempotency retry returns existing booking without new payment intent on stripe failure', function () {
    $category = Category::firstOrCreate(['slug' => 'idem-down'], ['name' => 'Idempotent Downtime']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'idem-down-' . uniqid(),
        'location' => 'Genoa, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.key' => 'pk_test_placeholder']);

    $idempotencyKey = Str::uuid()->toString();

    // First request succeeds
    $first = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 8000,
        ]);

    $first->assertStatus(201);

    $bookingCount = Booking::where('idempotency_key', $idempotencyKey)->count();

    // Even if Stripe were down on retry, the idempotency check (pre-transaction)
    // returns the existing booking before reaching Stripe
    config(['services.stripe.secret' => 'sk_test_invalid']);

    $second = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 8000,
        ]);

    $second->assertStatus(201);
    expect($second->json('data.reference'))->toBe($first->json('data.reference'));
    expect(Booking::where('idempotency_key', $idempotencyKey)->count())->toBe($bookingCount);
});
