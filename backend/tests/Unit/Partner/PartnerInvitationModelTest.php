<?php

use App\Domains\Partner\Models\PartnerInvitation;

test('isExpired returns true when expires_at is in the past and false when in the future', function () {
    $expired = new PartnerInvitation([
        'expires_at' => now()->subDay(),
        'status' => 'pending',
    ]);
    expect($expired->isExpired())->toBeTrue();

    $active = new PartnerInvitation([
        'expires_at' => now()->addDays(7),
        'status' => 'pending',
    ]);
    expect($active->isExpired())->toBeFalse();
});

test('isConsumed returns true when status is consumed', function () {
    $consumed = new PartnerInvitation([
        'status' => 'consumed',
        'expires_at' => now()->addDays(7),
    ]);
    expect($consumed->isConsumed())->toBeTrue();

    $pending = new PartnerInvitation([
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
    expect($pending->isConsumed())->toBeFalse();
});

test('isValid returns true only when status is pending and not expired', function () {
    $valid = new PartnerInvitation([
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
    expect($valid->isValid())->toBeTrue();

    $expired = new PartnerInvitation([
        'status' => 'pending',
        'expires_at' => now()->subMinutes(5),
    ]);
    expect($expired->isValid())->toBeFalse();

    $consumed = new PartnerInvitation([
        'status' => 'consumed',
        'expires_at' => now()->addDays(7),
    ]);
    expect($consumed->isValid())->toBeFalse();
});

test('scopes filter invitations accurately', function () {
    $admin = App\Models\User::factory()->create(['role' => 'admin']);

    PartnerInvitation::create([
        'email' => 'valid@example.com',
        'company_name' => 'Valid Co',
        'token' => str_repeat('a', 64),
        'status' => 'pending',
        'expires_at' => now()->addDays(5),
        'invited_by_admin_id' => $admin->id,
    ]);

    PartnerInvitation::create([
        'email' => 'expired@example.com',
        'company_name' => 'Expired Co',
        'token' => str_repeat('b', 64),
        'status' => 'pending',
        'expires_at' => now()->subDay(),
        'invited_by_admin_id' => $admin->id,
    ]);

    PartnerInvitation::create([
        'email' => 'consumed@example.com',
        'company_name' => 'Consumed Co',
        'token' => str_repeat('c', 64),
        'status' => 'consumed',
        'expires_at' => now()->addDays(5),
        'consumed_at' => now(),
        'invited_by_admin_id' => $admin->id,
    ]);

    expect(PartnerInvitation::pending()->count())->toBe(2);
    expect(PartnerInvitation::valid()->count())->toBe(1);
    expect(PartnerInvitation::valid()->first()->email)->toBe('valid@example.com');
});
