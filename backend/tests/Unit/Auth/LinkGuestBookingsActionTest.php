<?php

declare(strict_types=1);

use App\Domains\Auth\Actions\LinkGuestBookingsAction;
use App\Models\AuthAuditLog;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('links no bookings when no guest identities match', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);

    $action = new LinkGuestBookingsAction;
    $count = $action->execute($user);

    expect($count)->toBe(0);
});

it('marks guest identities as converted even without bookings table', function () {
    $user = User::factory()->create(['email' => 'bob@example.com']);

    $guestIdentity = GuestIdentity::create([
        'email' => 'bob@example.com',
        'name' => 'Bob',
    ]);

    $action = new LinkGuestBookingsAction;
    $count = $action->execute($user);

    expect($count)->toBe(0);
    expect($guestIdentity->fresh()->converted_user_id)->toBe($user->id);
});

it('links bookings to user when bookings table exists', function () {
    if (! Schema::hasTable('bookings')) {
        $this->markTestSkipped('Bookings table does not exist yet (spec 007 not migrated).');
    }

    $user = User::factory()->create(['email' => 'carol@example.com']);

    $guestIdentity = GuestIdentity::create([
        'email' => 'carol@example.com',
        'name' => 'Carol',
    ]);

    $bookingId = DB::table('bookings')->insertGetId([
        'reference' => 'BKO-CAROL1',
        'guest_identity_id' => $guestIdentity->id,
        'traveler_id' => null,
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $action = new LinkGuestBookingsAction;
    $count = $action->execute($user);

    expect($count)->toBe(1);
    expect(DB::table('bookings')->where('id', $bookingId)->value('traveler_id'))->toBe($user->id);
});

it('creates an audit log entry when linking guest bookings', function () {
    $user = User::factory()->create(['email' => 'dave@example.com']);

    GuestIdentity::create([
        'email' => 'dave@example.com',
        'name' => 'Dave',
    ]);

    $action = new LinkGuestBookingsAction;
    $action->execute($user, '127.0.0.1', 'TestAgent/1.0');

    $auditLog = AuthAuditLog::where('user_id', $user->id)
        ->where('event_type', 'guest_bookings_linked')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->ip_address)->toBe('127.0.0.1');
    expect($auditLog->user_agent)->toBe('TestAgent/1.0');
});

it('does not re-link already converted guest identities', function () {
    $user = User::factory()->create(['email' => 'eve@example.com']);
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    GuestIdentity::create([
        'email' => 'eve@example.com',
        'name' => 'Eve',
        'converted_user_id' => $otherUser->id,
    ]);

    $action = new LinkGuestBookingsAction;
    $count = $action->execute($user);

    expect($count)->toBe(0);
});
