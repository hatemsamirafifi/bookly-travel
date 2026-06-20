<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.key' => 'pk_test_placeholder']);
});

it('creates booking in pending_payment status with client_secret', function () {
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'payment-capture-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 7500,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $mock = $this->mock(StripeService::class);
    $mock->shouldReceive('createPaymentIntent')
        ->once()
        ->with(15000, 'EUR', Mockery::any())
        ->andReturn('pi_test_123_secret_abc');

    $response = actingAs($traveler)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => 7500,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('price_changed', false)
        ->assertJsonPath('payment.client_secret', 'pi_test_123_secret_abc');

    $booking = Booking::where('reference', $response->json('data.reference'))->first();
    expect($booking)->not->toBeNull();
    expect($booking->status)->toBe(Booking::STATUS_PENDING_PAYMENT);
    expect($booking->total_price)->toBe(15000);
    expect($booking->pending_expires_at)->not->toBeNull();
    expect($booking->stripe_payment_intent_id)->toBe('pi_test_123');
});

it('returns 409 when tour is sold out', function () {
    $category = Category::firstOrCreate(['slug' => 'test2'], ['name' => 'Test 2']);
    $partner = User::factory()->partner()->create();
    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'sold-out-' . uniqid(),
        'location' => 'Milan, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 2,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    // Book all spots with confirmed booking
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler1->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $response = actingAs($traveler2)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(7)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 5000,
        ]);

    $response->assertStatus(409);
});

it('returns existing booking for duplicate idempotency key', function () {
    $category = Category::firstOrCreate(['slug' => 'test3'], ['name' => 'Test 3']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'idem-dup-' . uniqid(),
        'location' => 'Naples, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 4000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $mock = $this->mock(StripeService::class);
    $mock->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn('pi_idem_123_secret_xyz');

    $idempotencyKey = Str::uuid()->toString();

    $first = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(10)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => 4000,
        ]);

    $first->assertStatus(201);

    $second = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(10)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => 4000,
        ]);

    $second->assertStatus(201);
    expect($second->json('data.reference'))->toBe($first->json('data.reference'));

    $count = Booking::where('idempotency_key', $idempotencyKey)->count();
    expect($count)->toBe(1);
});

it('maintains price at booking time when partner changes tour price', function () {
    $category = Category::firstOrCreate(['slug' => 'test4'], ['name' => 'Test 4']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'price-immut-' . uniqid(),
        'location' => 'Sorrento, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 6,
        'price_amount' => 12000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $mock = $this->mock(StripeService::class);
    $mock->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn('pi_price_123_secret_xyz');

    $response = actingAs($traveler)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(21)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 12000,
        ]);

    $response->assertStatus(201);
    $booking = Booking::where('reference', $response->json('data.reference'))->first();

    // Partner changes the tour price after booking
    $tour->update(['price_amount' => 18000]);

    // Booking still has the original price
    $booking->refresh();
    expect($booking->price_per_person)->toBe(12000);
    expect($booking->total_price)->toBe(12000);
});
