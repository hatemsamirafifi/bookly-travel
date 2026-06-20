<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('rejects invalid IBAN on profile update', function () {
    $response = putJson('/api/partner/profile', [
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
        'payout_iban' => 'INVALID_IBAN',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payout_iban']);
});

it('accepts valid IBAN on profile update', function () {
    // IT60X0542811101000000123456 is a valid test IBAN (passes MOD 97)
    $response = putJson('/api/partner/profile', [
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
        'payout_iban' => 'IT60X0542811101000000123456',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    // Verify the profile was saved
    $profile = PartnerProfile::where('partner_id', $this->partner->id)->first();
    expect($profile)->not->toBeNull();
});

it('accepts null IBAN on profile update', function () {
    PartnerProfile::create([
        'partner_id' => $this->partner->id,
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
    ]);

    $response = putJson('/api/partner/profile', [
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
        'payout_iban' => null,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
});

it('encrypts payout fields at rest', function () {
    putJson('/api/partner/profile', [
        'company_name' => 'Secure Tours Co.',
        'contact_email' => 'payout@securetours.com',
        'payout_iban' => 'IT60X0542811101000000123456',
        'payout_account_number' => '1234567890',
        'payout_swift_bic' => 'ITBKITMM',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $profile = PartnerProfile::where('partner_id', $this->partner->id)->first();
    expect($profile)->not->toBeNull();

    // Retrieve raw (encrypted) values from the database to verify encryption
    $rawIban = $profile->getRawOriginal('payout_iban');
    $rawAccountNumber = $profile->getRawOriginal('payout_account_number');
    $rawSwiftBic = $profile->getRawOriginal('payout_swift_bic');

    // Encrypted values should not equal the plain text originals
    expect($rawIban)->not->toBe('IT60X0542811101000000123456');
    expect($rawAccountNumber)->not->toBe('1234567890');
    expect($rawSwiftBic)->not->toBe('ITBKITMM');

    // Encrypted values should be decryptable back to the originals
    expect($profile->getRawPayoutIban())->toBe('IT60X0542811101000000123456');
    expect($profile->getRawPayoutAccountNumber())->toBe('1234567890');
    expect($profile->getRawPayoutSwiftBic())->toBe('ITBKITMM');
});

it('masks payout fields when reading via API', function () {
    putJson('/api/partner/profile', [
        'company_name' => 'Mask Tours Co.',
        'contact_email' => 'mask@tours.com',
        'payout_iban' => 'IT60X0542811101000000123456',
        'payout_account_number' => '1234567890',
        'payout_swift_bic' => 'ITBKITMM',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    // When reading through the model accessor, values should be masked
    $profile = PartnerProfile::where('partner_id', $this->partner->id)->first();

    // IBAN should be masked (all but last 4 chars replaced with *)
    $ibanValue = $profile->payout_iban;
    expect($ibanValue)->toEndWith('3456');
    expect($ibanValue)->not->toBe('IT60X0542811101000000123456');

    // Account number should be masked (last 4 digits with **** prefix)
    $accountValue = $profile->payout_account_number;
    expect($accountValue)->toEndWith('7890');
    expect($accountValue)->not->toBe('1234567890');

    // SWIFT/BIC should be masked (first 3 chars + ***)
    $swiftValue = $profile->payout_swift_bic;
    expect($swiftValue)->toStartWith('ITB');
    expect($swiftValue)->not->toBe('ITBKITMM');
});

it('rejects IBAN failing MOD 97 checksum', function () {
    $response = putJson('/api/partner/profile', [
        'company_name' => 'Test Tours Co.',
        'contact_email' => 'info@testtours.com',
        'payout_iban' => 'IT00X0542811101000000123456', // Modified check digits — invalid
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payout_iban']);
});
