<?php

use App\Jobs\SendVerificationEmail;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

it('registers a new traveler successfully', function () {
    Queue::fake();

    $response = postJson('/api/public/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'locale' => 'en',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'locale', 'created_at'],
                'token',
            ],
        ]);

    assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
        'locale' => 'en',
    ]);

    $user = User::where('email', 'john@example.com')->first();

    assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'registration',
    ]);

    Queue::assertPushed(SendVerificationEmail::class);
});

it('fails with a duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = postJson('/api/public/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('fails with a weak password', function () {
    $response = postJson('/api/public/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'weakpass',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('fails when fields are missing', function () {
    $response = postJson('/api/public/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('links guest bookings on registration', function () {
    $guest = GuestIdentity::factory()->create(['email' => 'guest@example.com']);

    postJson('/api/public/auth/register', [
        'name' => 'Guest User',
        'email' => 'guest@example.com',
        'password' => 'Password123!',
    ]);

    $user = User::where('email', 'guest@example.com')->first();

    assertDatabaseHas('guest_identities', [
        'id' => $guest->id,
        'converted_user_id' => $user->id,
    ]);
});

it('sets the locale correctly or defaults to en', function () {
    postJson('/api/public/auth/register', [
        'name' => 'Default Locale',
        'email' => 'default@example.com',
        'password' => 'Password123!',
    ]);

    assertDatabaseHas('users', [
        'email' => 'default@example.com',
        'locale' => 'en',
    ]);

    postJson('/api/public/auth/register', [
        'name' => 'Spanish Locale',
        'email' => 'es@example.com',
        'password' => 'Password123!',
        'locale' => 'es',
    ]);

    assertDatabaseHas('users', [
        'email' => 'es@example.com',
        'locale' => 'es',
    ]);
});

it('normalizes the email address', function () {
    postJson('/api/public/auth/register', [
        'name' => 'Normalized User',
        'email' => ' UPPERcase@Example.COM ',
        'password' => 'Password123!',
    ]);

    assertDatabaseHas('users', [
        'email' => 'uppercase@example.com',
    ]);
});

it('is protected by rate limiting', function () {
    $headers = ['REMOTE_ADDR' => '203.0.113.1'];

    for ($i = 0; $i < 10; $i++) {
        $response = postJson('/api/public/auth/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'Password123!',
        ], $headers);

        $response->assertStatus(201);
    }

    $response = postJson('/api/public/auth/register', [
        'name' => "User 10",
        'email' => "user10@example.com",
        'password' => 'Password123!',
    ], $headers);

    $response->assertStatus(429);
});
