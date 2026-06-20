<?php

use App\Models\Booking;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

it('converts a guest to an account with valid data', function () {
    Queue::fake();

    $guest = GuestIdentity::factory()->create([
        'email' => 'convert@example.com',
        'name' => 'Convert User',
    ]);

    $booking = Booking::factory()->create([
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
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

    $booking1 = Booking::factory()->create([
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
    ]);

    $booking2 = Booking::factory()->create([
        'traveler_id' => null,
        'guest_identity_id' => $guest->id,
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
