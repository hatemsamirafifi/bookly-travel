<?php

use App\Domains\Admin\Actions\InvitePartnerAction;
use App\Domains\Partner\Models\PartnerInvitation;
use App\Mail\PartnerInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

test('admin can invite a partner and dispatch queued email', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);

    $action = app(InvitePartnerAction::class);
    $invitation = $action->execute('invited@example.com', 'Invited Agency', $admin, 'es');

    expect($invitation)->toBeInstanceOf(PartnerInvitation::class)
        ->and($invitation->email)->toBe('invited@example.com')
        ->and($invitation->company_name)->toBe('Invited Agency')
        ->and($invitation->status)->toBe('pending')
        ->and($invitation->invited_by_admin_id)->toBe($admin->id)
        ->and($invitation->isValid())->toBeTrue();

    Mail::assertQueued(PartnerInvitationMail::class, function ($mail) use ($invitation) {
        return $mail->hasTo('invited@example.com')
            && $mail->invitation->id === $invitation->id;
    });
});

test('public endpoint can retrieve valid invitation details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $invitation = PartnerInvitation::create([
        'email' => 'partner@example.com',
        'company_name' => 'Acme Tours',
        'token' => 'valid-test-token-1234567890',
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
        'invited_by_admin_id' => $admin->id,
    ]);

    $response = $this->getJson("/api/public/auth/partners/invitation/{$invitation->token}");

    $response->assertOk()
        ->assertJson([
            'valid' => true,
            'email' => 'partner@example.com',
            'company_name' => 'Acme Tours',
        ]);
});

test('public endpoint returns 404 for invalid or expired invitation token', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $expired = PartnerInvitation::create([
        'email' => 'expired@example.com',
        'company_name' => 'Expired Tours',
        'token' => 'expired-token-1234567890',
        'status' => 'pending',
        'expires_at' => now()->subDay(),
        'invited_by_admin_id' => $admin->id,
    ]);

    $this->getJson("/api/public/auth/partners/invitation/{$expired->token}")
        ->assertNotFound();

    $this->getJson('/api/public/auth/partners/invitation/non-existent-token')
        ->assertNotFound();
});

test('partner can complete invitation and receive approved partner account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $invitation = PartnerInvitation::create([
        'email' => 'accept@example.com',
        'company_name' => 'Acme Adventures',
        'token' => 'acceptance-token-1234567890',
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
        'invited_by_admin_id' => $admin->id,
    ]);

    $payload = [
        'name' => 'Jane Partner',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'contact_phone' => '+15551234567',
        'business_description' => 'We offer mountain tours and adventures.',
        'payout_country' => 'US',
    ];

    $response = $this->postJson("/api/public/auth/partners/invitation/{$invitation->token}/complete", $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'role'],
            'partner' => ['id', 'company_name', 'status'],
            'token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'accept@example.com',
        'name' => 'Jane Partner',
        'role' => 'partner',
    ]);

    $this->assertDatabaseHas('partners', [
        'onboarding_status' => 'approved',
        'is_active' => true,
        'invited_by_admin' => true,
    ]);

    $this->assertDatabaseHas('partner_profiles', [
        'company_name' => 'Acme Adventures',
    ]);

    $invitation->refresh();
    $this->assertDatabaseHas('partner_settings', [
        'partner_id' => $invitation->partner_id,
    ]);

    expect($invitation->status)->toBe('consumed')
        ->and($invitation->consumed_at)->not->toBeNull()
        ->and($invitation->partner_id)->not->toBeNull();
});

test('cannot complete invitation with expired or already consumed token', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $consumed = PartnerInvitation::create([
        'email' => 'consumed@example.com',
        'company_name' => 'Consumed Agency',
        'token' => 'consumed-token-123',
        'status' => 'consumed',
        'expires_at' => now()->addDays(7),
        'consumed_at' => now(),
        'invited_by_admin_id' => $admin->id,
    ]);

    $payload = [
        'name' => 'John Consumed',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ];

    $this->postJson("/api/public/auth/partners/invitation/{$consumed->token}/complete", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('invite action rejects an email that is already a registered user', function () {
    Mail::fake();

    User::factory()->create(['email' => 'existing@example.com', 'role' => 'traveler']);
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);

    $action = app(InvitePartnerAction::class);

    expect(fn () => $action->execute('existing@example.com', 'Dup Co', $admin))
        ->toThrow(ValidationException::class);
});

test('invite action rejects a duplicate pending invitation for the same email', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
    $action = app(InvitePartnerAction::class);

    $action->execute('dup@example.com', 'First Co', $admin);
    expect(fn () => $action->execute('dup@example.com', 'Second Co', $admin))
        ->toThrow(ValidationException::class);
});

test('invite action logs partner.invite audit action', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
    $action = app(InvitePartnerAction::class);

    $action->execute('audit@example.com', 'Audit Co', $admin);

    $this->assertDatabaseHas('governance_audit_logs', [
        'action' => 'partner.invite',
        'actor_id' => $admin->id,
    ]);
});
