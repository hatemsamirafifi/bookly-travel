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
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;
});

it('returns profile for authenticated partner', function () {
    PartnerProfile::create([
        'partner_id' => $this->partner->id,
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
    ]);

    $response = getJson('/api/partner/profile', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['profile', 'settings'])
        ->assertJsonPath('profile.company_name', 'Test Tours Co.')
        ->assertJsonPath('profile.contact_email', 'info@testtours.com');
});

it('updates profile with valid data', function () {
    PartnerProfile::create([
        'partner_id' => $this->partner->id,
        'company_name' => 'Old Company Name',
        'contact_email' => 'old@testtours.com',
    ]);

    $response = putJson('/api/partner/profile', [
        'company_name' => 'Updated Tours Co.',
        'contact_email' => 'updated@testtours.com',
        'business_description' => 'We offer amazing tours across Italy.',
        'contact_phone' => '+39 555 123456',
        'website' => 'https://updatedtours.com',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('company_name', 'Updated Tours Co.')
        ->assertJsonPath('contact_email', 'updated@testtours.com');

    $this->assertDatabaseHas('partner_profiles', [
        'partner_id' => $this->partner->id,
        'company_name' => 'Updated Tours Co.',
        'contact_email' => 'updated@testtours.com',
    ]);
});

it('validates required fields on profile update', function () {
    $response = putJson('/api/partner/profile', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_name', 'contact_email']);
});

it('validates email format on profile update', function () {
    $response = putJson('/api/partner/profile', [
        'company_name' => 'Test Co.',
        'contact_email' => 'not-an-email',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['contact_email']);
});

it('returns 401 for unauthenticated request', function () {
    getJson('/api/partner/profile')
        ->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    getJson('/api/partner/profile', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(403);
});
