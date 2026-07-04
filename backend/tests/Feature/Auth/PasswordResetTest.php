<?php

use App\Jobs\SendPasswordResetEmail;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('sends a reset email for a verified email', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'email_verified_at' => now(),
    ]);

    $response = postJson('/api/public/auth/forgot-password', [
        'email' => 'reset@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'If the email exists, a reset link has been sent.');

    Queue::assertPushed(SendPasswordResetEmail::class);
});

it('sends a verification email for an unverified email instead of reset', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'email_verified_at' => null,
    ]);

    $response = postJson('/api/public/auth/forgot-password', [
        'email' => 'unverified@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'If the email exists, a reset link has been sent.');

    // Should queue verification email, not reset email
    Queue::assertPushed(SendVerificationEmail::class);
});

it('returns the same response for non-existent email to prevent enumeration', function () {
    $response = postJson('/api/public/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'If the email exists, a reset link has been sent.');
});

it('resets password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'valid@example.com',
        'email_verified_at' => now(),
    ]);

    $token = Password::createToken($user);

    $response = postJson('/api/public/auth/reset-password', [
        'email' => 'valid@example.com',
        'token' => $token,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Password reset successfully.');

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
});

it('fails with an invalid token', function () {
    postJson('/api/public/auth/reset-password', [
        'email' => 'invalid@example.com',
        'token' => 'invalid-token',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422);
});

it('fails with expired token', function () {
    $user = User::factory()->create([
        'email' => 'expired@example.com',
        'email_verified_at' => now(),
    ]);

    $token = Password::createToken($user);

    // Simulate token expiry by advancing time
    DB::table('password_reset_tokens')
        ->where('email', 'expired@example.com')
        ->update(['created_at' => now()->subHours(2)]);

    postJson('/api/public/auth/reset-password', [
        'email' => 'expired@example.com',
        'token' => $token,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422);
});

it('validates reset password request fields', function () {
    postJson('/api/public/auth/reset-password', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'token', 'password']);
});
