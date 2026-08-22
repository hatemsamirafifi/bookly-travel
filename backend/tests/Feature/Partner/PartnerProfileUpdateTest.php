<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $this->profile = PartnerProfile::create([
        'partner_id' => $this->partner->id,
        'company_name' => 'Original Travel Co',
        'contact_email' => $this->partnerUser->email,
        'contact_phone' => '+39 06 1234567',
        'business_description' => 'Original tour operations across Rome.',
        'payout_country' => 'IT',
    ]);

    $this->token = $this->partnerUser->createToken('test-partner', ['partner'])->plainTextToken;
});

it('returns partner profile data with show endpoint', function () {
    $response = getJson('/api/partner/profile', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('profile.company_name', 'Original Travel Co')
        ->assertJsonPath('profile.contact_email', $this->partnerUser->email)
        ->assertJsonPath('profile.payout_country', 'IT');
});

it('updates partner profile successfully with valid data', function () {
    $response = putJson('/api/partner/profile', [
        'company_name' => 'Updated Italian Expeditions',
        'contact_email' => 'contact@italianexpeditions.it',
        'contact_phone' => '+39 06 7654321',
        'business_description' => 'We curate luxury cultural experiences across Tuscany and Rome.',
        'website' => 'https://italianexpeditions.it',
        'tax_id' => 'IT12345678901',
        'payout_holder_name' => 'Italian Expeditions SRL',
        'payout_bank_name' => 'Banca Intesa',
        'payout_account_number' => 'ACC998877',
        'payout_country' => 'IT',
        'business_address' => [
            'street' => 'Piazza Navona 1',
            'city' => 'Rome',
            'postal_code' => '00186',
            'country' => 'IT',
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('company_name', 'Updated Italian Expeditions')
        ->assertJsonPath('contact_email', 'contact@italianexpeditions.it');

    $this->profile->refresh();
    expect($this->profile->company_name)->toBe('Updated Italian Expeditions');
    expect($this->profile->contact_email)->toBe('contact@italianexpeditions.it');
    expect($this->profile->website)->toBe('https://italianexpeditions.it');
    expect($this->profile->tax_id)->toBe('IT12345678901');
});

it('validates mandatory fields on profile update', function () {
    $response = putJson('/api/partner/profile', [
        'company_name' => '',
        'contact_email' => 'invalid-email-address',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_name', 'contact_email']);
});
