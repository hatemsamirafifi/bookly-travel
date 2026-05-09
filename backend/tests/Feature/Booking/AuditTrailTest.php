<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\{getJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->admin = User::factory()->admin()->create();
    $this->traveler = User::factory()->traveler()->create();
    $this->partner = User::factory()->partner()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'audit-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);

    $this->idempotencyKey = Str::uuid()->toString();

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => $this->idempotencyKey,
        'locale' => 'en',
    ]);

    BookingAuditLog::create([
        'booking_id' => $this->booking->id,
        'actor_type' => 'system',
        'actor_id' => null,
        'action' => 'created',
        'before_state' => null,
        'after_state' => 'confirmed',
        'metadata' => ['idempotency_key' => $this->idempotencyKey],
    ]);

    BookingAuditLog::create([
        'booking_id' => $this->booking->id,
        'actor_type' => 'partner',
        'actor_id' => $this->partner->id,
        'action' => 'completed',
        'before_state' => 'confirmed',
        'after_state' => 'completed',
    ]);

    $this->token = $this->admin->createToken('test')->plainTextToken;
});

it('returns full chronological audit trail for a booking', function () {
    $response = getJson('/api/admin/audit/bookings/' . $this->booking->reference, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'booking_reference',
                'entries',
                'linked_financial_events',
            ],
        ])
        ->assertJsonCount(2, 'data.entries');

    expect($response->json('data.entries')[0]['action'])->toBe('created');
    expect($response->json('data.entries')[1]['action'])->toBe('completed');
});

it('filters audit entries by actor type', function () {
    $response = getJson('/api/admin/audit/bookings?actor_type=partner', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    $entries = $response->json('data');
    expect(count($entries))->toBeGreaterThanOrEqual(1);

    foreach ($entries as $entry) {
        expect($entry['actor_type'])->toBe('partner');
    }
});

it('filters audit entries by action', function () {
    $response = getJson('/api/admin/audit/bookings?action=created', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    $entries = $response->json('data');
    foreach ($entries as $entry) {
        expect($entry['action'])->toBe('created');
    }
});

it('returns 403 for non-admin access', function () {
    $travelerToken = $this->traveler->createToken('test')->plainTextToken;

    $response = getJson('/api/admin/audit/bookings/' . $this->booking->reference, [
        'Authorization' => 'Bearer ' . $travelerToken,
    ]);

    $response->assertStatus(403);
});

it('returns 404 for nonexistent booking reference', function () {
    $response = getJson('/api/admin/audit/bookings/BKO-NONEXIST', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(404);
});
