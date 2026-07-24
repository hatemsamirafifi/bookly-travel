<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('changes password with correct current password', function () {
    $user = User::factory()->create([
        'email' => 'change@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = postJson('/api/public/account/change-password', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Password changed successfully.');

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
});

it('fails with incorrect current password', function () {
    $user = User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    postJson('/api/public/account/change-password', [
        'current_password' => 'WrongPassword!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Current password is incorrect.');
});

it('fails with weak new password', function () {
    $user = User::factory()->create([
        'email' => 'weak@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    postJson('/api/public/account/change-password', [
        'current_password' => 'OldPassword123!',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('requires authentication for change password', function () {
    postJson('/api/public/account/change-password', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(401);
});
