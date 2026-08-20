<?php

declare(strict_types=1);

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Mail\PartnerApplicationReceivedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

test('successful partner self-registration creates pending partner, user, profile, queues mail, creates admin in-app notification, and creates NO audit log', function () {
    // Setup an admin with manage_partners permission, and an admin without it
    $adminWithPerm = User::factory()->admin()->create();
    $adminWithPerm->adminPermission()->create(['flags' => ['manage_partners' => true]]);

    $adminWithoutPerm = User::factory()->admin()->create();
    $adminWithoutPerm->adminPermission()->create(['flags' => ['manage_partners' => false]]);

    $payload = [
        'name' => 'John Operator',
        'email' => 'operator@adventuretours.com',
        'password' => 'SecurePass123!@#',
        'password_confirmation' => 'SecurePass123!@#',
        'company_name' => 'Adventure Tours LLC',
        'contact_email' => 'operator@adventuretours.com',
        'contact_phone' => '+15551234567',
        'business_description' => 'We offer world class hiking tours and wildlife experiences.',
        'business_address' => [
            'street' => '123 Mountain Way',
            'city' => 'Denver',
            'postal_code' => '80202',
            'country' => 'US',
        ],
        'website' => 'https://adventuretours.com',
        'tax_id' => 'TAX-12345',
        'payout_country' => 'US',
        'locale' => 'en',
    ];

    $response = $this->postJson('/api/public/auth/partners/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'role'],
                'partner' => ['id', 'onboarding_status', 'is_active'],
                'token',
            ],
            'message',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'operator@adventuretours.com',
        'name' => 'John Operator',
        'role' => 'partner',
    ]);

    $user = User::where('email', 'operator@adventuretours.com')->first();
    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('partners', [
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'pending',
        'is_active' => false,
    ]);

    $partner = Partner::where('user_id', $user->id)->first();
    expect($partner)->not->toBeNull();

    $this->assertDatabaseHas('partner_profiles', [
        'partner_id' => $partner->id,
        'company_name' => 'Adventure Tours LLC',
        'contact_email' => 'operator@adventuretours.com',
        'contact_phone' => '+15551234567',
        'tax_id' => 'TAX-12345',
    ]);

    // Assert PartnerSettings created
    $this->assertDatabaseHas('partner_settings', [
        'partner_id' => $partner->id,
    ]);

    // Assert PartnerApplicationReceivedMail queued
    Mail::assertQueued(PartnerApplicationReceivedMail::class, function (PartnerApplicationReceivedMail $mail) use ($payload) {
        return $mail->hasTo($payload['email']);
    });

    // Assert in-app Notification created for admin with manage_partners permission
    $this->assertDatabaseHas('notifications', [
        'partner_id' => $partner->id,
        'type' => 'partner_application',
    ]);

    $adminNotification = Notification::where('partner_id', $partner->id)->first();
    expect($adminNotification)->not->toBeNull()
        ->and($adminNotification->data['admin_id'])->toBe($adminWithPerm->id)
        ->and($adminNotification->body)->toContain('Adventure Tours LLC');

    // Only 1 notification should be created (only $adminWithPerm has manage_partners)
    expect(Notification::where('partner_id', $partner->id)->count())->toBe(1);

    // Assert NO audit log created for self-registration (self-service, not admin governance)
    expect(GovernanceAuditLog::count())->toBe(0);
});

test('validation errors for missing required fields', function () {
    $response = $this->postJson('/api/public/auth/partners/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name',
            'email',
            'password',
            'company_name',
            'contact_phone',
            'business_description',
            'business_address',
        ]);
});

test('validation error for invalid email format', function () {
    $response = $this->postJson('/api/public/auth/partners/register', [
        'name' => 'John Operator',
        'email' => 'not-an-email',
        'password' => 'SecurePass123!@#',
        'password_confirmation' => 'SecurePass123!@#',
        'company_name' => 'Adventure Tours LLC',
        'contact_phone' => '+15551234567',
        'business_description' => 'Description here.',
        'business_address' => [
            'street' => '123 Way',
            'city' => 'Denver',
            'postal_code' => '80202',
            'country' => 'US',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('validation error for duplicate user email', function () {
    User::factory()->create(['email' => 'existing@bookly.com']);

    $response = $this->postJson('/api/public/auth/partners/register', [
        'name' => 'John Operator',
        'email' => 'existing@bookly.com',
        'password' => 'SecurePass123!@#',
        'password_confirmation' => 'SecurePass123!@#',
        'company_name' => 'Adventure Tours LLC',
        'contact_phone' => '+15551234567',
        'business_description' => 'Description here.',
        'business_address' => [
            'street' => '123 Way',
            'city' => 'Denver',
            'postal_code' => '80202',
            'country' => 'US',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
