<?php

use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('creates a guest identity when none exists', function () {
    $response = postJson('/api/public/auth/guest/identity', [
        'email' => 'guest@example.com',
        'name' => 'Guest User',
        'phone' => '+1234567890',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.email', 'guest@example.com');

    assertDatabaseHas('guest_identities', [
        'email' => 'guest@example.com',
        'name' => 'Guest User',
        'phone' => '+1234567890',
    ]);
});

it('detects an existing account by email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = postJson('/api/public/auth/guest/identity', [
        'email' => 'existing@example.com',
        'name' => 'Existing User',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('has_account', true);
});

it('creates multiple guest bookings with the same email', function () {
    postJson('/api/public/auth/guest/identity', [
        'email' => 'repeat@example.com',
        'name' => 'Repeat Guest',
    ])->assertStatus(201);

    postJson('/api/public/auth/guest/identity', [
        'email' => 'repeat@example.com',
        'name' => 'Repeat Guest',
    ])->assertStatus(200);

    $count = GuestIdentity::where('email', 'repeat@example.com')->count();
    expect($count)->toBe(1);
});
