<?php

use App\Jobs\SendPasswordResetEmail;
use App\Jobs\SendVerificationEmail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

it('dispatches a password reset email containing a valid reset URL and token', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'reset-content@example.com',
        'email_verified_at' => now(),
    ]);

    // Execute the queued job inline so the mailable is built and sent.
    // (PasswordResetMail implements ShouldQueue, so Mail::fake() records it
    // as queued rather than sent.)
    (new SendPasswordResetEmail($user))->handle();

    Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user) {
        // Addressed to the target user.
        if (! $mail->hasTo($user->email)) {
            return false;
        }

        // Localized subject resolves (no raw translation key leaks).
        if ($mail->envelope()->subject !== 'Reset your Bookly password') {
            return false;
        }

        // Rendered body carries the reset URL for this email address.
        $html = $mail->render();
        if (! str_contains($html, 'email=' . urlencode($user->email))) {
            return false;
        }

        // The embedded token must be a live token for this user.
        if (! preg_match('/token=([A-Za-z0-9]+)/', html_entity_decode($html), $matches)) {
            return false;
        }

        return Password::getRepository()->exists($user, $matches[1]);
    });
});
