<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->user->id,
        'role' => 'partner',
        'onboarding_status' => 'rejected',
        'is_active' => false,
    ]);

    $this->profile = PartnerProfile::create([
        'partner_id' => $this->partner->id,
        'company_name' => 'Initial Company',
        'contact_email' => $this->user->email,
        'contact_phone' => '+1234567890',
        'business_description' => 'Initial business description.',
        'website' => 'https://initial.example.com',
        'tax_id' => 'TAX-12345',
        'payout_country' => 'IT',
        'rejection_reason' => 'Missing commercial license details.',
    ]);

    $this->token = $this->user->createToken('partner-token', ['partner'])->plainTextToken;
});

it('allows rejected partner to resubmit application with updated profile', function () {
    $payload = [
        'company_name' => 'Updated Company Ltd',
        'contact_email' => 'updated@example.com',
        'contact_phone' => '+3906123456',
        'business_description' => 'Updated full description of tour operations.',
        'website' => 'https://updated.example.com',
        'tax_id' => 'IT987654321',
        'payout_country' => 'IT',
        'business_address' => [
            'street' => 'Via Roma 10',
            'city' => 'Rome',
            'postal_code' => '00100',
            'country' => 'IT',
        ],
    ];

    $response = postJson('/api/partner/onboarding/resubmit', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.onboarding_status', 'pending')
        ->assertJsonPath('data.can_create_tours', false)
        ->assertJsonPath('data.rejection_reason', null);

    // Verify partner state updated in database
    $this->partner->refresh();
    expect($this->partner->onboarding_status)->toBe('pending');
    expect($this->partner->rejection_reason)->toBeNull();

    // Verify profile updated
    $this->profile->refresh();
    expect($this->profile->company_name)->toBe('Updated Company Ltd');
    expect($this->profile->contact_email)->toBe('updated@example.com');
    expect($this->profile->tax_id)->toBe('IT987654321');
    expect($this->profile->rejection_reason)->toBeNull();

    // Verify audit log
    $this->assertDatabaseHas('governance_audit_logs', [
        'action' => 'partner.resubmit',
        'target_type' => 'partner',
        'target_id' => $this->partner->id,
        'actor_type' => $this->user->getMorphClass(),
        'actor_id' => $this->user->id,
    ]);
});

it('rejects resubmission when partner status is not rejected', function () {
    $this->partner->update([
        'onboarding_status' => 'pending',
    ]);

    $payload = [
        'company_name' => 'Updated Company Ltd',
        'contact_email' => 'updated@example.com',
        'contact_phone' => '+3906123456',
        'business_description' => 'Updated full description of tour operations.',
        'website' => 'https://updated.example.com',
        'tax_id' => 'IT987654321',
        'payout_country' => 'IT',
        'business_address' => [
            'street' => 'Via Roma 10',
            'city' => 'Rome',
            'postal_code' => '00100',
            'country' => 'IT',
        ],
    ];

    $response = postJson('/api/partner/onboarding/resubmit', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Only rejected partners can resubmit.');
});

it('validates required fields on resubmission', function () {
    $response = postJson('/api/partner/onboarding/resubmit', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_name', 'contact_email']);
});
