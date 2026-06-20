<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->traveler = User::factory()->traveler()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+39 333 1234567',
        'preferred_currency' => 'EUR',
        'marketing_emails' => true,
    ]);

    $this->token = $this->traveler->createToken('test')->plainTextToken;
});

it('returns traveler profile', function () {
    $response = getJson('/api/public/traveler/profile', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'preferred_language',
                'preferred_currency',
                'marketing_emails',
                'avatar_url',
            ],
        ])
        ->assertJsonPath('data.first_name', 'John')
        ->assertJsonPath('data.last_name', 'Doe')
        ->assertJsonPath('data.preferred_currency', 'EUR')
        ->assertJsonPath('data.marketing_emails', true);
});

it('returns 401 without authentication', function () {
    getJson('/api/public/traveler/profile')
        ->assertStatus(401);
});

it('updates traveler profile', function () {
    $response = putJson('/api/public/traveler/profile', [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '+39 333 9876543',
        'preferred_language' => 'it',
        'preferred_currency' => 'USD',
        'marketing_emails' => false,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.first_name', 'Jane')
        ->assertJsonPath('data.last_name', 'Smith')
        ->assertJsonPath('data.phone', '+39 333 9876543')
        ->assertJsonPath('data.preferred_language', 'it')
        ->assertJsonPath('data.preferred_currency', 'USD')
        ->assertJsonPath('data.marketing_emails', false);

    $this->traveler->refresh();
    expect($this->traveler->first_name)->toBe('Jane');
    expect($this->traveler->locale)->toBe('it');
    expect($this->traveler->preferred_currency)->toBe('USD');
    expect($this->traveler->marketing_emails)->toBeFalse();
});

it('fails profile update with missing required fields', function () {
    putJson('/api/public/traveler/profile', [
        'first_name' => '',
        'last_name' => '',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422);
});

it('fails profile update with invalid language', function () {
    putJson('/api/public/traveler/profile', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'preferred_language' => 'fr',
        'preferred_currency' => 'EUR',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422);
});

it('changes password with valid current password', function () {
    $this->traveler->update(['password' => Hash::make('OldPassword123!')]);

    $response = postJson('/api/public/traveler/profile/change-password', [
        'current_password' => 'OldPassword123!',
        'new_password' => 'NewPassword456!',
        'new_password_confirmation' => 'NewPassword456!',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Password updated successfully.');

    $this->traveler->refresh();
    expect(Hash::check('NewPassword456!', $this->traveler->password))->toBeTrue();
});

it('fails password change with incorrect current password', function () {
    $this->traveler->update(['password' => Hash::make('OldPassword123!')]);

    postJson('/api/public/traveler/profile/change-password', [
        'current_password' => 'WrongPassword!',
        'new_password' => 'NewPassword456!',
        'new_password_confirmation' => 'NewPassword456!',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(403);
});

it('fails password change with mismatched confirmation', function () {
    $this->traveler->update(['password' => Hash::make('OldPassword123!')]);

    postJson('/api/public/traveler/profile/change-password', [
        'current_password' => 'OldPassword123!',
        'new_password' => 'NewPassword456!',
        'new_password_confirmation' => 'DifferentPassword!',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422);
});

it('fails password change with short new password', function () {
    $this->traveler->update(['password' => Hash::make('OldPassword123!')]);

    postJson('/api/public/traveler/profile/change-password', [
        'current_password' => 'OldPassword123!',
        'new_password' => 'short',
        'new_password_confirmation' => 'short',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422);
});
