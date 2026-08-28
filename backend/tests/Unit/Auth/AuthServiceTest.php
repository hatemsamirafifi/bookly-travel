<?php

declare(strict_types=1);

use App\Domains\Auth\Services\AuthService;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a new guest identity when none exists', function () {
    $service = new AuthService;

    $identity = $service->findOrCreateGuestIdentity('Traveler@example.com', 'Alice');

    expect($identity)->toBeInstanceOf(GuestIdentity::class);
    expect($identity->email)->toBe('traveler@example.com');
    expect($identity->name)->toBe('Alice');
});

it('returns existing guest identity when one exists for the same email', function () {
    $existing = GuestIdentity::create([
        'email' => 'bob@example.com',
        'name' => 'Bob',
        'phone' => '+1234567890',
    ]);

    $service = new AuthService;

    $identity = $service->findOrCreateGuestIdentity('BOB@example.com', 'Bob Updated');

    expect($identity->id)->toBe($existing->id);
    expect($identity->name)->toBe('Bob');
});

it('creates a new identity when existing one is already converted', function () {
    $user = User::factory()->create(['email' => 'carol@example.com']);

    GuestIdentity::create([
        'email' => 'carol@example.com',
        'name' => 'Carol',
        'converted_user_id' => $user->id,
    ]);

    $service = new AuthService;

    $identity = $service->findOrCreateGuestIdentity('carol@example.com', 'Carol');

    expect($identity->converted_user_id)->toBeNull();
    expect($identity->name)->toBe('Carol');
});

it('creates a new identity when existing one is anonymized', function () {
    GuestIdentity::create([
        'email' => 'dave@example.com',
        'name' => 'Dave',
        'anonymized_at' => now(),
    ]);

    $service = new AuthService;

    $identity = $service->findOrCreateGuestIdentity('dave@example.com', 'Dave');

    expect($identity->anonymized_at)->toBeNull();
    expect($identity->name)->toBe('Dave');
});

it('checks for existing registered account by email', function () {
    User::factory()->create(['email' => 'eve@example.com']);

    $service = new AuthService;

    expect($service->checkExistingAccount('eve@example.com'))->toBeTrue();
    expect($service->checkExistingAccount('EVE@example.com'))->toBeTrue();
    expect($service->checkExistingAccount('nobody@example.com'))->toBeFalse();
});
