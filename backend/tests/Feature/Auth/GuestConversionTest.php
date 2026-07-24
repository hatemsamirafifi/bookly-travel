<?php

use App\Domains\Booking\Models\Booking;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('converts a guest to an account with valid data', function () {
    Queue::fake();

    $guest = GuestIdentity::factory()->create([
        'email' => 'convert@example.com',
        'name' => 'Convert User',
    ]);

    $tour = makeSearchableTour();
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(5)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $response = postJson('/api/public/auth/guest/convert', [
        'email' => 'convert@example.com',
        'name' => 'Convert User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'booking_reference' => $booking->reference,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token',
            ],
        ]);

    assertDatabaseHas('users', [
        'email' => 'convert@example.com',
        'name' => 'Convert User',
    ]);

    $user = User::where('email', 'convert@example.com')->first();

    assertDatabaseHas('guest_identities', [
        'id' => $guest->id,
        'converted_user_id' => $user->id,
    ]);

    assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'guest_converted',
    ]);
});

it('links all guest bookings with the same email on conversion', function () {
    $guest = GuestIdentity::factory()->create([
        'email' => 'multi@example.com',
        'name' => 'Multi Booking User',
    ]);

    $tour = makeSearchableTour();
    $booking1 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(5)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $booking2 = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(5)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    postJson('/api/public/auth/guest/convert', [
        'email' => 'multi@example.com',
        'name' => 'Multi Booking User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'booking_reference' => $booking1->reference,
    ])->assertStatus(201);

    $user = User::where('email', 'multi@example.com')->first();

    assertDatabaseHas('bookings', [
        'id' => $booking1->id,
        'traveler_id' => $user->id,
    ]);

    assertDatabaseHas('bookings', [
        'id' => $booking2->id,
        'traveler_id' => $user->id,
    ]);
});

it('returns 422 for duplicate email conversion', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $guest = GuestIdentity::factory()->create([
        'email' => 'taken@example.com',
        'name' => 'Taken User',
    ]);

    postJson('/api/public/auth/guest/convert', [
        'email' => 'taken@example.com',
        'name' => 'Taken User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'booking_reference' => 'BK-123',
    ])->assertStatus(422);
});
