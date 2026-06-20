<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('verifies email with a valid signed url', function () {
    $user = User::factory()->create([
        'email' => 'verify@example.com',
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'auth.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    $response = getJson($url);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Email verified successfully.');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
});

it('returns already verified for previously verified email', function () {
    $user = User::factory()->create([
        'email' => 'already@example.com',
        'email_verified_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        'auth.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    getJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message', 'Email already verified.');
});

it('returns 403 for invalid signature', function () {
    $user = User::factory()->create([
        'email' => 'bad@example.com',
        'email_verified_at' => null,
    ]);

    getJson("/api/public/auth/email/verify/{$user->id}/" . sha1('wrong@example.com'))
        ->assertStatus(403);
});

it('returns 404 for non-existent user', function () {
    getJson('/api/public/auth/email/verify/99999/' . sha1('test@example.com'))
        ->assertStatus(404);
});

it('resends verification email when authenticated and not verified', function () {
    $user = User::factory()->create([
        'email' => 'resend@example.com',
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = postJson('/api/public/auth/resend-verification', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Verification email sent.');
});

it('returns 400 when resending for already verified user', function () {
    $user = User::factory()->create([
        'email' => 'done@example.com',
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    postJson('/api/public/auth/resend-verification', [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(400)
        ->assertJsonPath('message', 'Email already verified.');
});

it('rate limits resend verification to 3 per hour', function () {
    $user = User::factory()->create([
        'email' => 'ratelimit@example.com',
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    for ($i = 0; $i < 3; $i++) {
        postJson('/api/public/auth/resend-verification', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(200);
    }

    postJson('/api/public/auth/resend-verification', [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(429);
});
